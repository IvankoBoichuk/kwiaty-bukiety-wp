<?php

declare(strict_types=1);

namespace App\Modules\LocalLinking;

/**
 * Read-only access to the local-page tables.
 *
 * Ported from the production plugin's inc/db-helper.php. The queries are kept
 * equivalent -- including the CRC32 fallback ordering, which is what keeps the
 * "popular" block stable between requests -- with one deliberate change: every
 * row also carries lp.term_id, so links can be built from get_term_link()
 * instead of the absolute production URL stored in lp.url.
 */
class LocalPageRepository
{
    public const NEIGHBOUR_LIMIT = 12;

    public const POPULAR_MIN = 8;

    public const POPULAR_MAX = 12;

    /**
     * Is this term one of the city landing pages?
     */
    public function isCityCategory(int $termId): bool
    {
        if ($termId <= 0 || ! Tables::exists()) {
            return false;
        }

        global $wpdb;

        $page = Tables::name(Tables::LOCAL_PAGE);

        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$page} WHERE term_id = %d", $termId),
        );

        return (int) $count > 0;
    }

    /**
     * The voivodeship a city page belongs to.
     */
    public function wojewodztwoForTerm(int $termId): ?object
    {
        if ($termId <= 0 || ! Tables::exists()) {
            return null;
        }

        global $wpdb;

        $page = Tables::name(Tables::LOCAL_PAGE);
        $city = Tables::name(Tables::MIASTO);
        $woj = Tables::name(Tables::WOJEWODZTWO);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT w.*
             FROM {$page} lp
             INNER JOIN {$city} m ON lp.miasto_id = m.id
             INNER JOIN {$woj} w ON m.wojewodztwo_id = w.id
             WHERE lp.term_id = %d
             LIMIT 1",
            $termId,
        ));

        return $row ?: null;
    }

    /**
     * Neighbouring cities, read from the undirected pair table.
     *
     * @return array<int, object>
     */
    public function neighbours(int $termId, int $limit = self::NEIGHBOUR_LIMIT): array
    {
        if ($termId <= 0 || ! Tables::exists()) {
            return [];
        }

        global $wpdb;

        $page = Tables::name(Tables::LOCAL_PAGE);
        $city = Tables::name(Tables::MIASTO);
        $link = Tables::name(Tables::CITY_LINK);

        $cityId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT miasto_id FROM {$page} WHERE term_id = %d LIMIT 1",
            $termId,
        ));

        if ($cityId <= 0) {
            return [];
        }

        $neighbourIds = $wpdb->get_col($wpdb->prepare(
            "SELECT CASE WHEN l.city_a = %d THEN l.city_b ELSE l.city_a END AS neighbor_id
             FROM {$link} l
             WHERE l.city_a = %d OR l.city_b = %d
             LIMIT %d",
            $cityId,
            $cityId,
            $cityId,
            $limit,
        ));

        $neighbourIds = array_values(array_filter(array_map('intval', (array) $neighbourIds)));

        if ($neighbourIds === []) {
            return [];
        }

        // Safe to interpolate: every value went through intval() above.
        $placeholders = implode(',', $neighbourIds);

        $rows = $wpdb->get_results(
            "SELECT m.id AS miasto_id, m.name, lp.url, lp.term_id
             FROM {$city} m
             JOIN {$page} lp ON lp.miasto_id = m.id
             WHERE m.id IN ({$placeholders})",
        );

        return $rows ?: [];
    }

    /**
     * Popular cities in the same voivodeship: the ones an editor ordered via
     * the `popular_order` term meta first, then a deterministic fill-up.
     *
     * @return array<int, object>
     */
    public function popularInWojewodztwo(
        int $termId,
        int $min = self::POPULAR_MIN,
        int $max = self::POPULAR_MAX,
    ): array {
        if ($termId <= 0 || ! Tables::exists()) {
            return [];
        }

        global $wpdb;

        $page = Tables::name(Tables::LOCAL_PAGE);
        $city = Tables::name(Tables::MIASTO);

        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT m.id AS miasto_id, m.wojewodztwo_id
             FROM {$page} lp
             JOIN {$city} m ON m.id = lp.miasto_id
             WHERE lp.term_id = %d
             LIMIT 1",
            $termId,
        ));

        if (! $current) {
            return [];
        }

        $ordered = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id AS miasto_id, m.name, lp.url, t.term_id, tm.meta_value+0 AS popular_order
             FROM {$city} m
             JOIN {$page} lp ON lp.miasto_id = m.id
             JOIN {$wpdb->terms} t ON t.term_id = lp.term_id
             LEFT JOIN {$wpdb->termmeta} tm ON tm.term_id = t.term_id AND tm.meta_key = 'popular_order'
             WHERE m.wojewodztwo_id = %d
               AND m.id <> %d
               AND tm.meta_id IS NOT NULL
             ORDER BY popular_order ASC, m.name ASC
             LIMIT %d",
            $current->wojewodztwo_id,
            $current->miasto_id,
            $max,
        ));

        $result = $ordered ?: [];

        if (count($result) >= $min) {
            return $result;
        }

        $fallback = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id AS miasto_id, m.name, lp.url, t.term_id, NULL AS popular_order
             FROM {$city} m
             JOIN {$page} lp ON lp.miasto_id = m.id
             JOIN {$wpdb->terms} t ON t.term_id = lp.term_id
             LEFT JOIN {$wpdb->termmeta} tm ON tm.term_id = t.term_id AND tm.meta_key = 'popular_order'
             WHERE m.wojewodztwo_id = %d
               AND m.id <> %d
               AND tm.meta_id IS NULL
             ORDER BY CRC32(CONCAT(m.id, '-popular')) ASC, m.name ASC
             LIMIT %d",
            $current->wojewodztwo_id,
            $current->miasto_id,
            $max - count($result),
        ));

        return array_merge($result, $fallback ?: []);
    }

    /**
     * Every voivodeship, for the "Kwiaciarnie w Polsce" hub.
     *
     * @return array<int, object>
     */
    public function wojewodztwa(): array
    {
        if (! Tables::exists()) {
            return [];
        }

        global $wpdb;

        $woj = Tables::name(Tables::WOJEWODZTWO);

        $rows = $wpdb->get_results("SELECT * FROM {$woj} ORDER BY name ASC");

        return $rows ?: [];
    }
}
