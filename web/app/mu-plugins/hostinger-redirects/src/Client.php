<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

use WP_Error;

/**
 * The three redirect endpoints of the Hostinger hosting API, plus the websites
 * lookup that finds the account the domain lives in.
 *
 *   GET    /api/hosting/v1/accounts/{username}/websites/{domain}/redirects
 *   POST   /api/hosting/v1/accounts/{username}/websites/{domain}/redirects  {from, to}
 *   DELETE /api/hosting/v1/accounts/{username}/websites/{domain}/redirects  {from}
 *
 * There is no update: a changed target is a delete followed by a create, which
 * is why Plan emits both. Authentication is a bearer token for the whole
 * account, so every call here is one a mistake would be expensive on -- hence
 * the narrow surface and the explicit error codes.
 *
 * @see https://docs.hostinger.com/api-reference/endpoints/hosting/redirects
 */
final class Client
{
    public const BASE_URL = 'https://developers.hostinger.com';

    public const TIMEOUT = 20;

    /**
     * The list endpoint paginates. 100 a page with a hard page cap keeps a
     * surprise -- a malformed response that never shrinks, say -- from looping
     * forever inside a cron run.
     */
    private const PER_PAGE = 100;

    private const MAX_PAGES = 50;

    /**
     * `from` exactly as the list endpoint spelled it, keyed by its normalised
     * form. Delete wants the original string back, and the two differ whenever
     * their copy carries a trailing slash or a full URL where ours is a path.
     *
     * @var array<string, string>
     */
    private array $rawSources = [];

    public function __construct(
        private readonly string $token,
        private readonly string $username,
        private readonly string $domain,
    ) {}

    /**
     * Every redirect the website holds, as `from => to`, with both sides
     * normalised the way Url::source() normalises what gets sent -- otherwise
     * a `/old-page/` on their side and a `/old-page` on ours would look like a
     * difference on every run.
     *
     * @return array<string, string>|WP_Error
     */
    public function all(): array|WP_Error
    {
        $redirects = [];
        $pagesSeen = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->request('GET', $this->redirectsPath(), [
                'page' => $page,
                'per_page' => self::PER_PAGE,
            ]);

            if ($response instanceof WP_Error) {
                return $response;
            }

            $entries = $this->entriesOf($response);

            if ($entries === []) {
                break;
            }

            // An endpoint that ignores `page` would otherwise hand back the
            // same redirects until MAX_PAGES. Stopping on a repeat also stops
            // a short page from being read as the last one, which guessing by
            // page size got wrong whenever the server chose its own size.
            $fingerprint = md5((string) wp_json_encode($entries));

            if (isset($pagesSeen[$fingerprint])) {
                break;
            }

            $pagesSeen[$fingerprint] = true;

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $raw = (string) ($entry['from'] ?? '');
                $from = Url::source($raw, $this->domain);
                $to = (string) ($entry['to'] ?? '');

                if ($from === '' || $to === '') {
                    continue;
                }

                $redirects[$from] = $to;
                $this->rawSources[$from] = $raw;
            }
        }

        return $redirects;
    }

    /**
     * @return true|WP_Error
     */
    public function create(string $from, string $to): bool|WP_Error
    {
        $response = $this->request('POST', $this->redirectsPath(), [], [
            'from' => $from,
            'to' => $to,
        ]);

        return $response instanceof WP_Error ? $response : true;
    }

    /**
     * The endpoint identifies a redirect by its source and wants that source
     * exactly as the list gave it, so a `from` that came from a list in this
     * same run is sent back in its original spelling rather than ours.
     *
     * @return true|WP_Error
     */
    public function delete(string $from): bool|WP_Error
    {
        $wire = $this->rawSources[$from] ?? $from;
        $response = $this->request('DELETE', $this->redirectsPath(), [], ['from' => $wire]);

        if ($response instanceof WP_Error) {
            // Already gone is the state we wanted.
            return $response->get_error_code() === 'kb_hostinger_not_found' ? true : $response;
        }

        return true;
    }

    /**
     * The hosting username that owns a domain. The redirect endpoints need it
     * in their path and nothing in WordPress knows it, so it is looked up once
     * and cached in an option.
     *
     * @return string|WP_Error
     */
    public static function discoverUsername(string $token, string $domain): string|WP_Error
    {
        $client = new self($token, '', $domain);

        $response = $client->request('GET', '/api/hosting/v1/websites', [
            'domain' => $domain,
            'per_page' => self::PER_PAGE,
        ]);

        if ($response instanceof WP_Error) {
            return $response;
        }

        foreach ($client->entriesOf($response) as $website) {
            if (! is_array($website)) {
                continue;
            }

            // `domain` is matched as a substring by the endpoint, so the page
            // can hold neighbours; only an exact host is this site.
            if (Url::host((string) ($website['domain'] ?? '')) !== $domain) {
                continue;
            }

            $username = trim((string) ($website['username'] ?? ''));

            if ($username !== '') {
                return $username;
            }
        }

        return new WP_Error(
            'kb_hostinger_no_website',
            sprintf(
                'The API lists no CloudLinux website with a username for %s. Set HOSTINGER_ACCOUNT to skip the lookup.',
                $domain,
            ),
        );
    }

    private function redirectsPath(): string
    {
        return sprintf(
            '/api/hosting/v1/accounts/%s/websites/%s/redirects',
            rawurlencode($this->username),
            rawurlencode($this->domain),
        );
    }

    /**
     * Both endpoints answer either a bare list or one wrapped in `data`, with
     * pagination alongside it. This takes whichever is there and leaves the
     * rest alone.
     *
     * @return array<int, mixed>
     */
    private function entriesOf(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return array_values($payload['data']);
        }

        return array_is_list($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return mixed|WP_Error  The decoded response body.
     */
    private function request(string $method, string $path, array $query = [], array $body = []): mixed
    {
        if ($this->token === '') {
            return new WP_Error('kb_hostinger_no_token', 'HOSTINGER_API_TOKEN is not set.');
        }

        $url = self::BASE_URL . $path;

        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        $args = [
            'method' => $method,
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if ($body !== []) {
            $args['body'] = (string) wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('kb_hostinger_request_failed', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = $raw === '' ? null : json_decode($raw, true);

        if ($code >= 200 && $code < 300) {
            return $decoded;
        }

        return new WP_Error(
            $this->errorCodeFor($code),
            sprintf('HTTP %d from %s %s: %s', $code, $method, $path, $this->messageOf($decoded, $raw)),
            ['status' => $code],
        );
    }

    /**
     * The failures the caller reacts to differently: a rate limit stops the
     * run and comes back later, a missing redirect on delete is a success, and
     * a rejected token is worth saying out loud rather than retrying.
     */
    private function errorCodeFor(int $status): string
    {
        return match (true) {
            $status === 401 || $status === 403 => 'kb_hostinger_unauthorised',
            $status === 404 => 'kb_hostinger_not_found',
            $status === 422 => 'kb_hostinger_rejected',
            $status === 429 => 'kb_hostinger_rate_limited',
            default => 'kb_hostinger_http_error',
        };
    }

    private function messageOf(mixed $decoded, string $raw): string
    {
        if (is_array($decoded)) {
            $message = (string) ($decoded['message'] ?? '');

            if ($message !== '') {
                return $message;
            }
        }

        // Keep an unexpected body short; it ends up in an option and a notice.
        return substr(trim(wp_strip_all_tags($raw)), 0, 300);
    }
}
