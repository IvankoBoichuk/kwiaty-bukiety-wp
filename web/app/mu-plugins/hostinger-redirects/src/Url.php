<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

/**
 * The URL shapes the two sides disagree on.
 *
 * Redirection stores a source as a path (`/old-page`), sometimes with a query
 * string, sometimes with a trailing slash, sometimes as a full URL after an
 * import. Hostinger's endpoint takes a `from` on a website it already knows by
 * domain, so a path is what it is given -- and whatever the list endpoint
 * echoes back has to compare equal to it, or every run would see a difference
 * and delete and recreate the same redirect forever.
 *
 * So both sides are put through source() before they are compared, and nothing
 * here touches WordPress: these are the parts worth unit testing.
 */
final class Url
{
    /**
     * The bare host of a URL, lowercased, port and userinfo dropped.
     */
    public static function host(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        // parse_url() needs a scheme to read a host rather than a path.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        return strtolower($host);
    }

    /**
     * A source path in the one form both sides are compared in: a single
     * leading slash, no trailing slash, no scheme or host, no query or
     * fragment, percent-encoding left exactly as it was stored.
     *
     * Returns an empty string for anything a web server cannot match on its
     * own -- a query string, a wildcard, a regex, or a URL pointing at another
     * host. The caller treats that as "leave it to Redirection".
     */
    public static function source(string $url, string $siteHost = ''): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        // A full URL only qualifies when it is this site's. An imported
        // redirect pointing at another host is not ours to serve.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            $host = self::host($url);

            if ($siteHost !== '' && $host !== '' && $host !== $siteHost && $host !== 'www.' . $siteHost) {
                return '';
            }

            $url = (string) parse_url($url, PHP_URL_PATH);
        }

        // Redirection's own match ignores the query string unless the redirect
        // asks for it, and the server cannot express either case, so a source
        // carrying one stays in PHP.
        if (str_contains($url, '?') || str_contains($url, '#')) {
            return '';
        }

        if ($url === '') {
            return '';
        }

        // `*` is Redirection's wildcard and the rest are regex metacharacters
        // that reach the table even with `regex` unset, e.g. after an import.
        if (preg_match('#[*^$()\[\]{}+\\\\]#', $url) === 1) {
            return '';
        }

        $path = '/' . ltrim($url, '/');
        $path = rtrim($path, '/');

        // The home page is not a redirect source; a server-level one there
        // would take the site down.
        return $path === '' ? '' : $path;
    }

    /**
     * A target as an absolute URL, which is what `to` wants. A relative target
     * -- how Redirection stores an on-site destination -- is resolved against
     * the site's home URL.
     *
     * Returns an empty string when the result is not a usable http(s) URL.
     */
    public static function target(string $url, string $home): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            return in_array($scheme, ['http', 'https'], true) ? $url : '';
        }

        // A protocol-relative target keeps the visitor's scheme; the server
        // needs one spelled out, and https is the only one this site answers.
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (! str_starts_with($url, '/')) {
            return '';
        }

        return rtrim($home, '/') . $url;
    }
}
