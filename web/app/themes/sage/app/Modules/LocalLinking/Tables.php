<?php

declare(strict_types=1);

namespace App\Modules\LocalLinking;

/**
 * Names of the five `bi_*` tables the local-page linking runs on.
 *
 * The tables are not owned by the theme: they arrive with the production
 * database dump and outlive any theme switch, so nothing here creates or drops
 * them. Everything that reads them goes through exists() first, because a fresh
 * install has no `bi_*` tables at all and the module has to stay silent rather
 * than fatal.
 */
final class Tables
{
    public const WOJEWODZTWO = 'bi_wojewodztwo';

    public const POWIAT = 'bi_powiat';

    public const MIASTO = 'bi_miasto';

    public const LOCAL_PAGE = 'bi_local_page';

    public const CITY_LINK = 'bi_city_link';

    /**
     * The tables a front-end request actually touches.
     */
    public const REQUIRED = [
        self::WOJEWODZTWO,
        self::MIASTO,
        self::LOCAL_PAGE,
        self::CITY_LINK,
    ];

    protected static ?bool $exists = null;

    public static function name(string $table): string
    {
        global $wpdb;

        return $wpdb->prefix . $table;
    }

    /**
     * True when every table the module reads is present.
     *
     * Resolved once per request: SHOW TABLES is cheap, but this sits on every
     * product category view.
     */
    public static function exists(): bool
    {
        if (self::$exists !== null) {
            return self::$exists;
        }

        global $wpdb;

        foreach (self::REQUIRED as $table) {
            $name = self::name($table);

            $found = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name)),
            );

            if ($found !== $name) {
                return self::$exists = false;
            }
        }

        return self::$exists = true;
    }

    /**
     * Test seam: forces the next exists() call to query again.
     */
    public static function flush(): void
    {
        self::$exists = null;
    }
}
