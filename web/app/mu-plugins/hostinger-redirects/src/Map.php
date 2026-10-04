<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

/**
 * Which of Redirection's rows a web server can answer, as `from => to`.
 *
 * A row qualifies only when every part of it is plain: an enabled URL-to-URL
 * redirect, no regex, a status code from the allowed list, a source path a
 * server can match literally and a target that resolves to an absolute http(s)
 * URL. Source() and target() decide the last two.
 *
 * Everything that does not qualify is reported rather than dropped silently --
 * "why is this one still slow" is the first question anyone asks of a module
 * like this.
 */
final class Map
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  Rows from Source::rows().
     * @param  string  $home  home_url('/'), used to resolve relative targets.
     * @param  array<int, int>  $allowedCodes
     * @return array{map: array<string, string>, skipped: array<int, array{id: int, url: string, reason: string}>}
     */
    public static function build(array $rows, string $home, array $allowedCodes): array
    {
        $siteHost = Url::host($home);
        $map = [];
        $skipped = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $url = (string) ($row['url'] ?? '');
            $code = (int) ($row['action_code'] ?? 0);

            $skip = static function (string $reason) use (&$skipped, $id, $url): void {
                $skipped[] = ['id' => $id, 'url' => $url, 'reason' => $reason];
            };

            if (! in_array($code, $allowedCodes, true)) {
                $skip(sprintf('status %d is not mirrored', $code));

                continue;
            }

            $from = Url::source($url, $siteHost);

            if ($from === '') {
                $skip('the source is not a plain path on this site');

                continue;
            }

            $to = Url::target(self::targetOf($row), $home);

            if ($to === '') {
                $skip('the target is not an absolute http(s) URL');

                continue;
            }

            // A server-level redirect to the path it redirects from is an
            // infinite loop with no PHP left to break it.
            if (Url::source($to, $siteHost) === $from) {
                $skip('the target is the source');

                continue;
            }

            if (isset($map[$from])) {
                // Redirection answers with the first match by `position`, and
                // Source::rows() reads in that order, so the first row wins
                // here too.
                $skip('another redirect already covers this source');

                continue;
            }

            /**
             * Last word on a single row, for the cases this module cannot know
             * about -- a path some other plugin answers, say.
             */
            if (! apply_filters('kb_hostinger_redirects_eligible', true, $row, $from, $to)) {
                $skip('excluded by kb_hostinger_redirects_eligible');

                continue;
            }

            $map[$from] = $to;
        }

        return ['map' => $map, 'skipped' => $skipped];
    }

    /**
     * `action_data` holds the target. Redirection writes it as a bare string
     * for a URL redirect, but an imported or older row can carry the JSON
     * object its other action types use.
     *
     * @param  array<string, mixed>  $row
     */
    private static function targetOf(array $row): string
    {
        $data = (string) ($row['action_data'] ?? '');

        if (! str_starts_with(ltrim($data), '{')) {
            return $data;
        }

        $decoded = json_decode($data, true);

        return is_array($decoded) ? (string) ($decoded['url'] ?? '') : '';
    }
}
