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

    public static function findByPostalCode(WP_REST_Request $request): WP_REST_Response
    {
        $postalCode = trim((string) $request->get_param('postal_code'));

        if ($postalCode === '') {
            return new WP_REST_Response([
                'message' => 'postal_code is required.',
            ], 400);
        }

        $results = PostalCodeModel::query()
            ->select([
                'postal_code',
                'settlement',
                'street',
                'house_numbers',
                'municipality',
                'county',
                'province',
            ])
            ->where('postal_code', $postalCode)
            ->orderBy('settlement')
            ->orderBy('street')
            ->get();

        return new WP_REST_Response([
            'data' => $results,
            'count' => $results->count(),
        ]);
    }

    public static function findBySettlement(WP_REST_Request $request): WP_REST_Response
    {
        $settlement = trim((string) $request->get_param('settlement'));
        $limit = min(max((int) $request->get_param('limit'), 1), 100);

        if ($settlement === '') {
            return new WP_REST_Response([
                'message' => 'settlement is required.',
            ], 400);
        }

        $results = PostalCodeModel::query()
            ->select([
                'postal_code',
                'settlement',
                'street',
                'house_numbers',
                'municipality',
                'county',
                'province',
            ])
            ->where('settlement', 'like', $settlement . '%')
            ->orderBy('settlement')
            ->orderBy('postal_code')
            ->limit($limit)
            ->get();

        return new WP_REST_Response([
            'data' => $results,
            'count' => $results->count(),
        ]);
    }
}
