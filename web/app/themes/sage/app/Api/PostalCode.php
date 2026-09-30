<?php

namespace App\Api;

use App\Models\PostalCode as PostalCodeModel;
use WP_REST_Request;
use WP_REST_Response;

class PostalCode
{
    protected const ROUTE_NAMESPACE = 'sage/v1';

    protected const ROUTE_BY_POSTAL_CODE = '/postal-codes/by-postal-code';

    protected const ROUTE_BY_SETTLEMENT = '/postal-codes/by-settlement';

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE_BY_POSTAL_CODE, [
            'methods' => 'GET',
            'callback' => [self::class, 'findByPostalCode'],
            'permission_callback' => '__return_true',
            'args' => [
                'postal_code' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE_BY_SETTLEMENT, [
            'methods' => 'GET',
            'callback' => [self::class, 'findBySettlement'],
            'permission_callback' => '__return_true',
            'args' => [
                'settlement' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'required' => false,
                    'sanitize_callback' => 'absint',
                    'default' => 20,
                ],
            ],
        ]);
    }

    /**
     * Columns every lookup returns.
     *
     * @var list<string>
     */
    protected const COLUMNS = [
        'postal_code',
        'settlement',
        'street',
        'house_numbers',
        'municipality',
        'county',
        'province',
    ];

    protected const MAX_RESULTS = 100;

    protected const CACHE_GROUP = 'sage_postal_codes';

    protected const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    public static function findByPostalCode(WP_REST_Request $request): WP_REST_Response
    {
        $postalCode = trim((string) $request->get_param('postal_code'));

        if ($postalCode === '') {
            return new WP_REST_Response([
                'message' => 'postal_code is required.',
            ], 400);
        }

        return self::respond("code:{$postalCode}", static fn() => PostalCodeModel::query()
            ->select(self::COLUMNS)
            ->where('postal_code', $postalCode)
            ->orderBy('settlement')
            ->orderBy('street')
            ->limit(self::MAX_RESULTS)
            ->get());
    }

    public static function findBySettlement(WP_REST_Request $request): WP_REST_Response
    {
        $settlement = trim((string) $request->get_param('settlement'));
        $limit = min(max((int) $request->get_param('limit'), 1), self::MAX_RESULTS);

        if ($settlement === '') {
            return new WP_REST_Response([
                'message' => 'settlement is required.',
            ], 400);
        }

        $prefix = self::escapeLike($settlement) . '%';

        return self::respond("settlement:{$settlement}:{$limit}", static fn() => PostalCodeModel::query()
            ->select(self::COLUMNS)
            ->where('settlement', 'like', $prefix)
            ->orderBy('settlement')
            ->orderBy('postal_code')
            ->limit($limit)
            ->get());
    }

    /**
     * Escape the LIKE wildcards in a user-supplied prefix.
     *
     * Unescaped, a bare "%" collapses the prefix match into LIKE '%%' and
     * scans the whole table -- which this endpoint hands to anyone, on every
     * keystroke of the checkout autocomplete.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * The autocomplete fires per keystroke over a table of ~118k rows, so the
     * same handful of prefixes gets asked for constantly. Cache the response.
     */
    protected static function respond(string $key, callable $query): WP_REST_Response
    {
        $cacheKey = md5($key);
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $results = $query();
        $payload = [
            'data' => $results->all(),
            'count' => $results->count(),
        ];

        wp_cache_set($cacheKey, $payload, self::CACHE_GROUP, self::CACHE_TTL);

        return new WP_REST_Response($payload);
    }
}
