<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

use WP_CLI;

/**
 * `wp hostinger-redirects` -- the way the first import is meant to be done:
 *
 *   wp hostinger-redirects status            what is configured, what ran last
 *   wp hostinger-redirects sync --dry-run    what the first run would change
 *   wp hostinger-redirects sync              do it
 *   wp hostinger-redirects sync --force      apply held-back deletions too
 *   wp hostinger-redirects purge             remove everything it created
 *
 * The module imports on its own on the first cron tick after it is configured,
 * so this is for seeing the plan before that happens -- and for the deletions
 * the safety cap deliberately refuses to send unattended.
 */
final class Cli
{
    public static function boot(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI || ! class_exists(WP_CLI::class)) {
            return;
        }

        WP_CLI::add_command('hostinger-redirects sync', [self::class, 'sync'], [
            'shortdesc' => 'Reconciles Hostinger\'s server-level redirects with the plain 301s in Redirection.',
            'synopsis' => [
                [
                    'type' => 'flag',
                    'name' => 'dry-run',
                    'description' => 'Print the plan without writing anything.',
                    'optional' => true,
                ],
                [
                    'type' => 'flag',
                    'name' => 'force',
                    'description' => 'Apply deletions past the safety cap.',
                    'optional' => true,
                ],
            ],
        ]);

        WP_CLI::add_command('hostinger-redirects status', [self::class, 'status'], [
            'shortdesc' => 'Shows the configuration and the last run.',
        ]);

        WP_CLI::add_command('hostinger-redirects purge', [self::class, 'purge'], [
            'shortdesc' => 'Removes every redirect this module created from Hostinger.',
            'synopsis' => [
                [
                    'type' => 'flag',
                    'name' => 'yes',
                    'description' => 'Skip the confirmation.',
                    'optional' => true,
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, mixed>  $assoc
     */
    public static function sync(array $args, array $assoc): void
    {
        unset($args);

        $dryRun = (bool) ($assoc['dry-run'] ?? false);
        $report = Sync::run($dryRun, (bool) ($assoc['force'] ?? false));

        self::printReport($report);

        if (in_array($report['status'], ['error', 'disabled', 'no-redirection-table'], true)) {
            WP_CLI::warning((string) $report['message']);

            return;
        }

        WP_CLI::success($dryRun ? 'Dry run complete; nothing was written.' : 'Sync complete.');
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, mixed>  $assoc
     */
    public static function status(array $args = [], array $assoc = []): void
    {
        unset($args, $assoc);

        WP_CLI::log('Enabled:  ' . (Config::isEnabled() ? 'yes' : 'no — ' . Sync::disabledReason()));
        WP_CLI::log('Domain:   ' . (Config::domain() ?: '—'));
        WP_CLI::log('Username: ' . (Config::username() ?: 'not set (looked up from the API)'));
        WP_CLI::log('Token:    ' . (Config::token() === '' ? 'missing' : 'set'));
        WP_CLI::log('Mirrored: ' . count(Sync::ledger()));
        WP_CLI::log('');

        $report = Sync::lastReport();

        if ($report === []) {
            WP_CLI::log('The mirror has not run yet.');

            return;
        }

        WP_CLI::log('Last run: ' . (string) ($report['time'] ?? '—') . ' — ' . (string) ($report['status'] ?? '—'));
        self::printReport($report);
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, mixed>  $assoc
     */
    public static function purge(array $args, array $assoc): void
    {
        unset($args);

        WP_CLI::confirm('Remove every redirect this module created from Hostinger?', $assoc);

        $report = Sync::purge();

        WP_CLI::log(sprintf('Deleted: %d', count((array) ($report['deleted'] ?? []))));

        foreach ((array) ($report['failed'] ?? []) as $failure) {
            $failure = (array) $failure;
            WP_CLI::warning(sprintf(
                '%s: %s',
                (string) ($failure['from'] ?? ''),
                (string) ($failure['error'] ?? ''),
            ));
        }

        WP_CLI::success('Purge complete.');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function printReport(array $report): void
    {
        if (! empty($report['message'])) {
            WP_CLI::log('Note: ' . (string) $report['message']);
        }

        WP_CLI::log(sprintf(
            'Rows: %d  eligible: %d  on Hostinger: %d',
            (int) ($report['rows'] ?? 0),
            (int) ($report['eligible'] ?? 0),
            (int) ($report['remote'] ?? 0),
        ));

        foreach ((array) ($report['created'] ?? []) as $from => $created) {
            // A dry run reports `from => to`; a real one a list of pairs.
            if (is_array($created)) {
                WP_CLI::log(sprintf('  + %s -> %s', (string) ($created['from'] ?? ''), (string) ($created['to'] ?? '')));

                continue;
            }

            WP_CLI::log(sprintf('  + %s -> %s', (string) $from, (string) $created));
        }

        foreach ((array) ($report['deleted'] ?? []) as $from) {
            WP_CLI::log(sprintf('  - %s', (string) $from));
        }

        foreach ((array) ($report['held_deletes'] ?? []) as $from) {
            WP_CLI::log(sprintf('  ! held back: %s', (string) $from));
        }

        foreach ((array) ($report['conflicts'] ?? []) as $conflict) {
            $conflict = (array) $conflict;
            WP_CLI::log(sprintf(
                '  ~ %s is already redirected to %s in hPanel; left alone',
                (string) ($conflict['from'] ?? ''),
                (string) ($conflict['theirs'] ?? ''),
            ));
        }

        foreach ((array) ($report['failed'] ?? []) as $failure) {
            $failure = (array) $failure;
            WP_CLI::warning(sprintf(
                '%s (%s): %s',
                (string) ($failure['from'] ?? ''),
                (string) ($failure['action'] ?? ''),
                (string) ($failure['error'] ?? ''),
            ));
        }

        $skipped = (array) ($report['skipped'] ?? []);

        if ($skipped !== []) {
            WP_CLI::log(sprintf('Left to PHP: %d', count($skipped)));
        }
    }
}
