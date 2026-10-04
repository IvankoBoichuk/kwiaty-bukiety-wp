<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

/**
 * Where the module reads its settings, and when it is allowed to write.
 *
 * The token is a credential for the whole hosting account, so it lives in the
 * environment next to the database password and never in an option a plugin or
 * an editor could read back. Everything else is derived: the hosting username
 * comes from the API itself, the domain from WP_HOME.
 *
 * The environment gate matters more than it looks. A copy of the production
 * database on dev or staging carries the same `redirection_items` rows and, if
 * it also carried the token, its first sync would rewrite the live server's
 * redirects from whatever state that copy is in. So writing is off unless the
 * environment is production, or HOSTINGER_REDIRECTS_ENABLED says otherwise on
 * purpose.
 */
final class Config
{
    /**
     * The `from` values this module created, as `from => to`. Everything the
     * reconcile may delete comes from here, so a redirect added by hand in
     * hPanel is never touched.
     */
    public const OPTION_LEDGER = 'kb_hostinger_redirects_managed';

    /**
     * The last run's report, shown on the Tools page and by `wp
     * hostinger-redirects status`. Its absence is also what marks the module
     * as never having run, which is what triggers the first full sync.
     */
    public const OPTION_REPORT = 'kb_hostinger_redirects_report';

    /**
     * The hosting username discovered from the API, cached so every run does
     * not spend a request looking it up again.
     */
    public const OPTION_USERNAME = 'kb_hostinger_redirects_username';

    public static function token(): string
    {
        return self::value('HOSTINGER_API_TOKEN');
    }

    /**
     * The hosting account the domain sits in. Set it explicitly to skip the
     * lookup, which is the only reason the module needs the websites endpoint.
     */
    public static function username(): string
    {
        $configured = self::value('HOSTINGER_ACCOUNT');

        if ($configured !== '') {
            return $configured;
        }

        return (string) get_option(self::OPTION_USERNAME, '');
    }

    public static function rememberUsername(string $username): void
    {
        update_option(self::OPTION_USERNAME, $username, false);
    }

    /**
     * The website the redirects belong to, as Hostinger knows it: the bare
     * host, no scheme and no `www.` unless the hosting entry itself is the
     * `www` one, which it is not on this account.
     */
    public static function domain(): string
    {
        $configured = self::value('HOSTINGER_SITE_DOMAIN');

        if ($configured !== '') {
            return Url::host($configured);
        }

        return Url::host((string) home_url('/'));
    }

    /**
     * True when a run may call the API at all. Everything else in the module
     * checks this once, at the top of the run, rather than guarding each call.
     */
    public static function isEnabled(): bool
    {
        if (self::token() === '') {
            return false;
        }

        $explicit = strtolower(self::value('HOSTINGER_REDIRECTS_ENABLED'));

        if ($explicit !== '') {
            return in_array($explicit, ['1', 'true', 'yes', 'on'], true);
        }

        return wp_get_environment_type() === 'production';
    }

    /**
     * Only permanent redirects go to the server. Hostinger's endpoint takes a
     * `from` and a `to` and nothing else, so the status code it answers with
     * is not ours to choose -- its redirects are permanent ones. Mirroring a
     * 302 as a 301 would turn a temporary redirect into one browsers cache
     * forever, which is why the default list holds 301 alone.
     *
     * @return array<int, int>
     */
    public static function allowedCodes(): array
    {
        $codes = apply_filters('kb_hostinger_redirects_allowed_codes', [301]);

        return array_values(array_filter(array_map('intval', (array) $codes)));
    }

    /**
     * How many creates and deletes a single run may send. The first run on a
     * site with a migration's worth of redirects would otherwise fire hundreds
     * of requests in one go; it stops at the cap and queues itself again
     * instead, so the set fills in over a few minutes.
     */
    public static function writesPerRun(): int
    {
        return max(1, (int) apply_filters('kb_hostinger_redirects_writes_per_run', 100));
    }

    /**
     * How many deletions a run may send without a human saying yes. The cap
     * is what stands between an unexpectedly empty read of Redirection's
     * table -- a schema change, a half-restored database -- and the whole
     * mirror being torn down by a cron job.
     */
    public static function deleteCap(): int
    {
        return max(0, (int) apply_filters('kb_hostinger_redirects_delete_cap', 25));
    }

    /**
     * Reads a setting from a constant first -- which is where Bedrock's
     * config/application.php puts it -- then from the environment, for a
     * deploy that exports the variable without defining the constant.
     */
    private static function value(string $name): string
    {
        if (defined($name)) {
            $defined = constant($name);

            // CONVERT_BOOL in application.php turns `true` in .env into a real
            // boolean, so the flag arrives here as one.
            if (is_bool($defined)) {
                return $defined ? '1' : '0';
            }

            return trim((string) $defined);
        }

        $env = getenv($name);

        if (is_string($env) && $env !== '') {
            return trim($env);
        }

        return trim((string) ($_ENV[$name] ?? $_SERVER[$name] ?? ''));
    }
}
