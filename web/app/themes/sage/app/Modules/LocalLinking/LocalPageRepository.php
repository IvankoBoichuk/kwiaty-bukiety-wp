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

    public const SEARCH_LIMIT = 10;

    protected const INDEX_CACHE_GROUP = 'sage_city_index';

    protected const INDEX_CACHE_KEY = 'cities';

    protected const INDEX_CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * The nine Polish letters that carry a diacritic, lower-case only --
     * normalize() lower-cases before it folds. ó is in the list although
     * remove_accents() handles it: the point is that the fold is the same with
     * or without WordPress.
     *
     * @var array<string, string>
     */
    protected const DIACRITICS = [
        'ą' => 'a',
        'ć' => 'c',
        'ę' => 'e',
        'ł' => 'l',
        'ń' => 'n',
        'ó' => 'o',
        'ś' => 's',
        'ź' => 'z',
        'ż' => 'z',
    ];

    /**
     * @var array<int, array{normalized: string, name: string, voivodeship: string, term_id: int}>|null
     */
    protected static ?array $cityIndex = null;

    /**
     * False until resolved; null when intl is missing.
     */
    protected static \Collator|false|null $collator = false;

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

    /**
     * Normalizes a city name or a search query to one comparable form.
     *
     * Polish city names are written with diacritics and searched without them:
     * nobody types "Piła" or "Łódź" into an autocomplete, they type "pila" and
     * "lodz". The nine Polish letters are folded by the map below rather than
     * by remove_accents() alone, because remove_accents() is WordPress: it
     * reaches it through seems_utf8() and get_locale(), so what it does to ł
     * depends on how the string arrived and which locale is loaded. The map
     * does not, which is also what lets the ranking be unit-tested without
     * booting WordPress. remove_accents() still runs after it, as a net for
     * everything that is not Polish (a pasted "Köln", a name carrying a stray
     * combining mark); over text the map already folded it is a no-op.
     *
     * The result is lower-case and ASCII for every name in bi_miasto, which is
     * what lets the matching below use byte offsets.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        if ($value === '') {
            return '';
        }

        $value = strtr($value, self::DIACRITICS);

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        // "Nowy  Sącz" and "Nowy Sącz" are the same query; a single space also
        // keeps the word-boundary test below to one character.
        return (string) preg_replace('/\s+/u', ' ', $value);
    }

    /**
     * Cities matching a query, best match first.
     *
     * Ranked in three bands -- the name starts with the query, a word inside
     * the name starts with it, the query appears anywhere -- so "gora" offers
     * Góra, then Jelenia Góra, then Twardogóra. The production endpoint had no
     * ranking at all: it returned the first twenty rows in CSV order.
     *
     * The filtering runs in PHP over the cached index rather than as a SQL
     * LIKE. 1 742 rows is nothing to scan, and a LIKE could not see past the
     * diacritics without a collation that also folds ł.
     *
     * @return array<int, array{name: string, voivodeship: string, term_id: int}>
     */
    public function searchCities(string $query, int $limit = self::SEARCH_LIMIT): array
    {
        return self::rankMatches($this->cityIndex(), $query, $limit);
    }

    /**
     * The matching and ordering of searchCities(), over an index passed in.
     *
     * Split out so the ranking can be exercised without a database: it is the
     * part with the rules in it.
     *
     * @param  array<int, array{normalized: string, name: string, voivodeship: string, term_id: int}>  $index
     * @return array<int, array{name: string, voivodeship: string, term_id: int}>
     */
    public static function rankMatches(
        array $index,
        string $query,
        int $limit = self::SEARCH_LIMIT,
    ): array {
        $query = self::normalize($query);

        if ($query === '' || $limit < 1) {
            return [];
        }

        $matches = [];

        foreach ($index as $row) {
            $position = strpos($row['normalized'], $query);

            if ($position === false) {
                continue;
            }

            $matches[] = [
                'rank' => self::rank($row['normalized'], $position),
                'row' => $row,
            ];
        }

        if ($matches === []) {
            return [];
        }

        $collator = self::collator();

        usort($matches, static function (array $a, array $b) use ($collator): int {
            if ($a['rank'] !== $b['rank']) {
                return $a['rank'] <=> $b['rank'];
            }

            $comparison = $collator instanceof \Collator
                ? (int) $collator->compare($a['row']['name'], $b['row']['name'])
                : $a['row']['normalized'] <=> $b['row']['normalized'];

            // Nothing in bi_miasto shares a name today, but the fallback
            // comparison is over the ASCII fold, where "Łodz" and "Lodz" would
            // tie. The term id settles it so a cached response cannot differ
            // from the one that built it.
            return $comparison !== 0
                ? $comparison
                : $a['row']['term_id'] <=> $b['row']['term_id'];
        });

        return array_map(
            static fn(array $match): array => [
                'name' => $match['row']['name'],
                'voivodeship' => $match['row']['voivodeship'],
                'term_id' => $match['row']['term_id'],
            ],
            array_slice($matches, 0, $limit),
        );
    }

    /**
     * Every city that has a landing page, with its name pre-normalized.
     *
     * @return array<int, array{normalized: string, name: string, voivodeship: string, term_id: int}>
     */
    public function cityIndex(): array
    {
        if (self::$cityIndex !== null) {
            return self::$cityIndex;
        }

        $cached = wp_cache_get(self::INDEX_CACHE_KEY, self::INDEX_CACHE_GROUP);

        if (is_array($cached)) {
            return self::$cityIndex = $cached;
        }

        $index = $this->buildCityIndex();

        wp_cache_set(
            self::INDEX_CACHE_KEY,
            $index,
            self::INDEX_CACHE_GROUP,
            self::INDEX_CACHE_TTL,
        );

        return self::$cityIndex = $index;
    }

    /**
     * Drops the cached index.
     *
     * The bi_* tables have no write path in the theme -- they arrive with a
     * database import and are read-only here -- so nothing invalidates this on
     * its own. Call it from whatever loads them next, or flush the object cache
     * after an import.
     */
    public static function flushCityIndex(): void
    {
        self::$cityIndex = null;

        Tables::flush();

        wp_cache_delete(self::INDEX_CACHE_KEY, self::INDEX_CACHE_GROUP);
    }

    /**
     * @return array<int, array{normalized: string, name: string, voivodeship: string, term_id: int}>
     */
    protected function buildCityIndex(): array
    {
        if (! Tables::exists()) {
            return [];
        }

        global $wpdb;

        $page = Tables::name(Tables::LOCAL_PAGE);
        $city = Tables::name(Tables::MIASTO);
        $woj = Tables::name(Tables::WOJEWODZTWO);

        // LEFT JOIN on the voivodeship: a city whose wojewodztwo_id points
        // nowhere still has a page worth offering, it just has no label.
        $rows = $wpdb->get_results(
            "SELECT m.name AS name, w.name AS voivodeship, lp.term_id
             FROM {$page} lp
             INNER JOIN {$city} m ON m.id = lp.miasto_id
             LEFT JOIN {$woj} w ON w.id = m.wojewodztwo_id
             WHERE lp.term_id IS NOT NULL AND lp.term_id > 0
             ORDER BY m.name ASC",
        );

        $index = [];

        foreach ($rows ?: [] as $row) {
            $name = trim((string) ($row->name ?? ''));
            $termId = (int) ($row->term_id ?? 0);

            if ($name === '' || $termId <= 0) {
                continue;
            }

            $index[] = [
                'normalized' => self::normalize($name),
                'name' => $name,
                'voivodeship' => (string) ($row->voivodeship ?? ''),
                'term_id' => $termId,
            ];
        }

        return $index;
    }

    /**
     * 0 when the name starts with the query, 1 when a word inside it does,
     * 2 when the query only appears somewhere in the middle.
     *
     * Byte offsets are safe here: normalize() folds every name in bi_miasto to
     * ASCII, and for anything it could not fold a continuation byte is neither
     * a space nor a hyphen, so the match lands in the last band.
     */
    protected static function rank(string $normalized, int $position): int
    {
        if ($position === 0) {
            return 0;
        }

        $preceding = $normalized[$position - 1];

        return $preceding === ' ' || $preceding === '-' ? 1 : 2;
    }

    /**
     * Polish collation for the within-band ordering, when intl is available.
     *
     * Without it the band falls back to the normalized name, which orders the
     * ASCII fold instead of the Polish alphabet -- close enough that the lists
     * read the same, and the only difference anyone would notice is where an
     * ł-city sits among the l-cities.
     */
    protected static function collator(): ?\Collator
    {
        if (self::$collator !== false) {
            return self::$collator;
        }

        if (! class_exists(\Collator::class)) {
            return self::$collator = null;
        }

        $collator = collator_create('pl_PL');

        return self::$collator = $collator instanceof \Collator ? $collator : null;
    }
}
