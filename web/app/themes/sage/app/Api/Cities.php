<?php

declare(strict_types=1);

namespace App\Api;

use App\Modules\LocalLinking\LocalPageRepository;
use App\Modules\LocalLinking\Tables;
use WP_REST_Request;
use WP_REST_Response;

/**
 * City autocomplete for the "Kwiaciarnie w Polsce" hub (production snippet
 * #48).
 *
 * The snippet read wp-content/uploads/kb-cities.csv -- all 1 750 lines of it,
 * on every keystroke, with the production domain baked into every URL -- and
 * matched the raw substring, so "pila" found nothing and "lodz" found nothing.
 * This reads the bi_* tables the rest of the local-page linking already runs
 * on, folds the diacritics on both sides of the comparison, and resolves each
 * link through get_term_link().
 *
 * @see LocalPageRepository::searchCities()
 */
final class Cities
{
    protected const ROUTE_NAMESPACE = 'sage/v1';

    protected const ROUTE = '/cities';

    /**
     * Below this, every query matches half of Poland. The front end holds the
     * same threshold so the request is not even sent.
     */
    protected const MIN_QUERY_LENGTH = 2;

    protected const DEFAULT_LIMIT = LocalPageRepository::SEARCH_LIMIT;

    protected const MAX_LIMIT = 20;

    /**
     * Rows asked for beyond the limit, to cover the ones whose term has since
     * been deleted: bi_local_page.term_id is a plain column, and a row that
     * lost its category resolves to a WP_Error and drops out below.
     */
    protected const LINK_FAILURE_MARGIN = 5;

    protected const CACHE_GROUP = 'sage_cities';

    protected const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
            'methods' => 'GET',
            'callback' => [self::class, 'search'],
            'permission_callback' => [self::class, 'permission_callback'],
            'args' => [
                'search' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                    'description' => 'City name, with or without Polish diacritics.',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => self::DEFAULT_LIMIT,
                    'description' => 'Maximum number of suggestions (1-20, default 10).',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * The hub page is public and so is every category it links to, so this is
     * open -- spelled out rather than left to '__return_true', to say that the
     * endpoint exposes nothing the sitemap does not.
     */
    public static function permission_callback(WP_REST_Request $request): bool
    {
        unset($request);

        return true;
    }

    public static function search(WP_REST_Request $request): WP_REST_Response
    {
        $limit = min(max((int) $request->get_param('limit'), 1), self::MAX_LIMIT);
        $search = trim((string) $request->get_param('search'));

        if (mb_strlen($search) < self::MIN_QUERY_LENGTH) {
            return new WP_REST_Response(self::payload([]));
        }

        /*
         * An absent bi_* set is a fresh install, not a failure: the hub still
         * renders, the search just has nothing to offer. A 500 here would also
         * put a red line in the console of every page carrying the field.
         */
        if (! Tables::exists()) {
            return new WP_REST_Response(self::payload([]));
        }

        $query = LocalPageRepository::normalize($search);

        if ($query === '') {
            return new WP_REST_Response(self::payload([]));
        }

        /*
         * Keyed by the normalized query, so "Piła", "pila" and "PIŁA" are one
         * cache entry rather than three. The responses are tiny and the same
         * few prefixes get typed over and over.
         */
        $cacheKey = md5("{$query}:{$limit}");
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $payload = self::payload(self::suggestions($query, $limit));

        wp_cache_set($cacheKey, $payload, self::CACHE_GROUP, self::CACHE_TTL);

        return new WP_REST_Response($payload);
    }

    /**
     * @return array<int, array{city: string, voivodeship: string, url: string}>
     */
    protected static function suggestions(string $query, int $limit): array
    {
        $rows = app(LocalPageRepository::class)
            ->searchCities($query, $limit + self::LINK_FAILURE_MARGIN);

        $suggestions = [];

        foreach ($rows as $row) {
            $url = get_term_link($row['term_id'], 'product_cat');

            if (! is_string($url) || $url === '') {
                continue;
            }

            $suggestions[] = [
                'city' => $row['name'],
                'voivodeship' => $row['voivodeship'],
                'url' => $url,
            ];

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<int, array{city: string, voivodeship: string, url: string}>  $suggestions
     * @return array{data: array<int, array{city: string, voivodeship: string, url: string}>, count: int}
     */
    protected static function payload(array $suggestions): array
    {
        return [
            'data' => $suggestions,
            'count' => count($suggestions),
        ];
    }
}
