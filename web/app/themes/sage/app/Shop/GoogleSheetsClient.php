<?php

declare(strict_types=1);

namespace App\Shop;

use RuntimeException;

/**
 * The handful of Google Sheets API v4 calls the order sync needs, signed in as
 * a service account.
 *
 * Deliberately not `google/apiclient`: that package pulls in every Google API
 * there is to make four HTTP requests. The service-account flow is one RS256
 * JWT swapped for an access token, which `openssl_sign()` covers.
 *
 * Every failure -- transport, HTTP status, an unusable answer -- surfaces as a
 * RuntimeException, so the caller has one thing to catch and retry.
 */
final class GoogleSheetsClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public const API = 'https://sheets.googleapis.com/v4/spreadsheets/';

    public const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /**
     * A token lives an hour; it is dropped this much earlier so a request
     * never sets out with one that expires on the way.
     */
    public const TOKEN_MARGIN = 300;

    public const TIMEOUT = 15;

    /**
     * @param  array{client_email: string, private_key: string, token_uri?: string}  $credentials
     */
    public function __construct(
        private array $credentials,
        private string $spreadsheetId,
    ) {}

    /**
     * The service-account key, from the GOOGLE_SHEETS_CREDENTIALS constant:
     * either the JSON itself or a path to the file Google hands out. Null when
     * it is missing or unusable, which is the off switch.
     *
     * @return array{client_email: string, private_key: string, token_uri?: string}|null
     */
    public static function credentials(): ?array
    {
        $raw = trim((string) apply_filters(
            'sage/shop/sheets_credentials',
            defined('GOOGLE_SHEETS_CREDENTIALS') ? (string) constant('GOOGLE_SHEETS_CREDENTIALS') : '',
        ));

        if ($raw === '') {
            return null;
        }

        if (! str_starts_with($raw, '{')) {
            $raw = is_readable($raw) ? (string) file_get_contents($raw) : '';
        }

        $credentials = json_decode($raw, true);

        if (! is_array($credentials)
            || ! is_string($credentials['client_email'] ?? null)
            || ! is_string($credentials['private_key'] ?? null)) {
            return null;
        }

        return $credentials;
    }

    /**
     * Column A from the second row down, as strings, so a row number is the
     * index plus two. An empty cell comes back as an empty string.
     *
     * @return array<int, string>
     */
    public function idColumn(string $tab): array
    {
        $body = $this->request('GET', self::API . rawurlencode($this->spreadsheetId)
            . '/values/' . rawurlencode(self::a1($tab, 'A2:A'))
            . '?majorDimension=COLUMNS&valueRenderOption=UNFORMATTED_VALUE');

        return array_map(
            static fn(mixed $cell): string => is_float($cell) && floor($cell) === $cell
                ? (string) (int) $cell
                : (string) $cell,
            $body['values'][0] ?? [],
        );
    }

    /**
     * Whether the row holds nothing in any column, including the ones people
     * fill by hand.
     */
    public function rowIsEmpty(string $tab, int $row): bool
    {
        $body = $this->request('GET', self::API . rawurlencode($this->spreadsheetId)
            . '/values/' . rawurlencode(self::a1($tab, $row . ':' . $row)));

        foreach ($body['values'][0] ?? [] as $cell) {
            if ((string) $cell !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes one row starting at column A, parsed as if typed in -- which is
     * what Apps Script's setValues() did, so numbers and dates land the same.
     *
     * @param  array<int, string|int>  $values
     */
    public function writeRow(string $tab, int $row, array $values): void
    {
        $range = self::a1($tab, sprintf('A%1$d:%2$s%1$d', $row, self::column(count($values))));

        $this->request('PUT', self::API . rawurlencode($this->spreadsheetId)
            . '/values/' . rawurlencode($range) . '?valueInputOption=USER_ENTERED', [
                'range' => $range,
                'majorDimension' => 'ROWS',
                'values' => [$values],
            ]);
    }

    /**
     * Opens an empty row at $row, pushing everything from it down. Formatting
     * and validation come from the row below, not from the header above.
     */
    public function insertRow(string $tab, int $row): void
    {
        $this->batchUpdate([[
            'insertDimension' => [
                'range' => $this->rows($tab, $row),
                'inheritFromBefore' => false,
            ],
        ]]);
    }

    public function deleteRow(string $tab, int $row): void
    {
        $this->batchUpdate([[
            'deleteDimension' => ['range' => $this->rows($tab, $row)],
        ]]);
    }

    /**
     * Row insertions and deletions address a tab by its numeric id, not its
     * name. It never changes for a tab, so a day's cache saves a request per
     * order; a renamed tab simply misses and is looked up again.
     */
    public function sheetId(string $tab): int
    {
        $key = 'kb_sheets_gid_' . md5($this->spreadsheetId . "\0" . $tab);
        $cached = get_transient($key);

        if (is_numeric($cached)) {
            return (int) $cached;
        }

        $body = $this->request('GET', self::API . rawurlencode($this->spreadsheetId)
            . '?fields=' . rawurlencode('sheets.properties(sheetId,title)'));

        foreach ($body['sheets'] ?? [] as $sheet) {
            if (($sheet['properties']['title'] ?? null) === $tab) {
                $id = (int) $sheet['properties']['sheetId'];
                set_transient($key, $id, DAY_IN_SECONDS);

                return $id;
            }
        }

        throw new RuntimeException(sprintf('No tab named "%s" in the spreadsheet.', $tab));
    }

    /**
     * A cached access token, or a fresh one for a newly signed JWT.
     */
    public function accessToken(): string
    {
        $key = $this->tokenKey();
        $cached = get_transient($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = wp_remote_post($this->credentials['token_uri'] ?? self::TOKEN_URI, [
            'timeout' => self::TIMEOUT,
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt(),
            ],
        ]);

        $body = self::decode($response, 'Token request');
        $token = (string) ($body['access_token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('Token request returned no access token.');
        }

        $ttl = (int) ($body['expires_in'] ?? 3600) - self::TOKEN_MARGIN;
        set_transient($key, $token, max(60, $ttl));

        return $token;
    }

    /**
     * The signed assertion Google trades for an access token.
     */
    public function jwt(): string
    {
        $now = time();

        $segments = [
            self::base64Url((string) wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) wp_json_encode([
                'iss' => $this->credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $this->credentials['token_uri'] ?? self::TOKEN_URI,
                'iat' => $now,
                'exp' => $now + HOUR_IN_SECONDS,
            ])),
        ];

        $signature = '';

        if (! openssl_sign(implode('.', $segments), $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The service-account private key could not sign the token request.');
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    /**
     * A1 notation with the tab quoted, since "Zamówienia" is not a bare name.
     */
    public static function a1(string $tab, string $cells): string
    {
        return "'" . str_replace("'", "''", $tab) . "'!" . $cells;
    }

    /**
     * 1 -> A, 8 -> H, 27 -> AA.
     */
    public static function column(int $number): string
    {
        $letters = '';

        while ($number > 0) {
            $number--;
            $letters = chr(65 + $number % 26) . $letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    /**
     * @param  array<int, array<string, mixed>>  $requests
     */
    protected function batchUpdate(array $requests): void
    {
        $this->request('POST', self::API . rawurlencode($this->spreadsheetId) . ':batchUpdate', [
            'requests' => $requests,
        ]);
    }

    /**
     * One row as a GridRange; $row is 1-based, as the sheet numbers it.
     *
     * @return array{sheetId: int, dimension: string, startIndex: int, endIndex: int}
     */
    protected function rows(string $tab, int $row): array
    {
        return [
            'sheetId' => $this->sheetId($tab),
            'dimension' => 'ROWS',
            'startIndex' => $row - 1,
            'endIndex' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    protected function request(string $method, string $url, ?array $json = null): array
    {
        $args = [
            'method' => $method,
            'timeout' => self::TIMEOUT,
            'headers' => ['Authorization' => 'Bearer ' . $this->accessToken()],
        ];

        if ($json !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = (string) wp_json_encode($json);
        }

        $response = wp_remote_request($url, $args);

        // A revoked or rotated key fails here, not at the token endpoint;
        // forgetting the token makes the retry sign a new one.
        if (! is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 401) {
            delete_transient($this->tokenKey());
        }

        return self::decode($response, $method . ' ' . strtok($url, '?'));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function decode(mixed $response, string $what): array
    {
        if (is_wp_error($response)) {
            throw new RuntimeException($what . ': ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $body = is_array($body) ? $body : [];

        if ($code < 200 || $code >= 300) {
            $message = $body['error']['message'] ?? $body['error_description'] ?? $body['error'] ?? '';

            throw new RuntimeException(trim(sprintf('%s: HTTP %d %s', $what, $code, is_string($message) ? $message : '')));
        }

        return $body;
    }

    protected function tokenKey(): string
    {
        return 'kb_sheets_token_' . md5($this->credentials['client_email']);
    }

    protected static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
