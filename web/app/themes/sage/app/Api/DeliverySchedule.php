<?php

declare(strict_types=1);

namespace App\Api;

use App\Support\DeliveryTimer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The two things on a product page that go stale in the page cache.
 *
 * The single-product HTML is cached for a week, and it carries both the
 * delivery calendar (today's date, today's remaining slots, the 60-day upper
 * bound) and a `wc_store_api` nonce, which WordPress only accepts for 24
 * hours. A page served from the cache therefore offered "Dziś" with the date it
 * was generated on, and its first add-to-cart answered 403 Nonce is invalid.
 *
 * Both are per-request values, so they are fetched from here instead -- a REST
 * route is not page cached, and LiteSpeed is told explicitly not to try.
 */
class DeliverySchedule
{
    protected const ROUTE_NAMESPACE = 'sage/v1';

    protected const ROUTE = '/delivery-schedule';

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
            'methods' => 'GET',
            'callback' => [self::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        /**
         * LiteSpeed caches REST GETs by default, which would park one
         * customer's nonce in front of everybody else's.
         *
         * @see https://docs.litespeedtech.com/lscache/lscwp/api/
         */
        do_action('litespeed_control_set_nocache', 'per-request delivery schedule and nonce');

        $response = new WP_REST_Response([
            'schedule' => app(DeliveryTimer::class)->purchaseOptions(),
            // Minted per request, exactly as the template used to mint it.
            'storeApiNonce' => (string) wp_create_nonce('wc_store_api'),
            'timestamp' => gmdate('c'),
        ]);

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
