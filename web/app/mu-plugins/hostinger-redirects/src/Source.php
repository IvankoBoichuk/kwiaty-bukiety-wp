<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

/**
 * Redirection's table, read-only.
 *
 * The rows are read rather than the plugin's classes called, for two reasons:
 * a cron run must not depend on Redirection having booted its admin side, and
 * the coarse filter belongs in SQL on a table that can hold thousands of rows
 * after a migration.
 *
 * The four conditions are the ones a web server can honour at all:
 *
 *   status      = 'enabled'   -- a disabled row answers nothing
 *   action_type = 'url'       -- not pass-through, 404, 410 or random
 *   match_type  = 'url'       -- not login, referrer, agent, cookie, role...
 *   regex       = 0           -- a pattern is not a path
 *
 * `position` decides which row Redirection answers with when two match, so the
 * read keeps that order and Map::build() lets the first one win.
 */
final class Source
{
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'redirection_items';
    }

    /**
     * False when the plugin was never installed, or its tables were dropped.
     * A run that cannot see the table does nothing at all -- in particular it
     * does not read "no redirects" as "delete everything".
     */
    public static function exists(): bool
    {
        global $wpdb;

        $table = self::table();

        return (string) $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table),
        ) === $table;
    }

    /**
     * Every row in the table, eligible or not. Used by the report to say how
     * much of the set reached the server, and as the brake that tells an empty
     * result apart from an unreadable one.
     */
    public static function total(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL -- the table name is built from $wpdb->prefix.
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . self::table() . '`');
    }

    /**
     * The rows a server-level redirect could be made of.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL -- the table name is built from $wpdb->prefix.
        $rows = $wpdb->get_results(
            'SELECT id, url, action_data, action_code, position'
            . ' FROM `' . self::table() . '`'
            . " WHERE status = 'enabled'"
            . " AND action_type = 'url'"
            . " AND match_type = 'url'"
            . ' AND regex = 0'
            . ' ORDER BY position ASC, id ASC',
            ARRAY_A,
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * What the server should hold, as `from => to`, with the rows that did not
     * qualify and why.
     *
     * @return array{map: array<string, string>, skipped: array<int, array{id: int, url: string, reason: string}>}
     */
    public static function desired(): array
    {
        return Map::build(self::rows(), (string) home_url('/'), Config::allowedCodes());
    }
}
