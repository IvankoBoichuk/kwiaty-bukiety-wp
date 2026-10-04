<?php

declare(strict_types=1);

namespace App\Api;

use WP_REST_Request;
use WP_REST_Response;

/**
 * The cart badge, which the page cache cannot carry.
 *
 * The header and the mobile bottom bar sit in every page, so the badge was
 * rendered into HTML that LiteSpeed then serves to everybody: the home page
 * was generated with an empty cart and kept saying 0 after an add-to-cart,
 * and a page generated while someone had items would have shown that count to
 * the next visitor. The templates therefore render 0 and the count is read
 * from here instead, once per page view.
 *
 * @see \App\Api\DeliverySchedule for the same split on the product page.
 */
class CartCount
{
    protected const ROUTE_NAMESPACE = 'sage/v1';

    protected const ROUTE = '/cart-count';

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
         * LiteSpeed caches REST GETs by default, which here would park one
         * customer's cart count in front of everybody else's.
         *
         * @see https://docs.litespeedtech.com/lscache/lscwp/api/
         */
        do_action('litespeed_control_set_nocache', 'per-request cart count');

        $response = new WP_REST_Response([
            'count' => self::count(),
            'timestamp' => gmdate('c'),
        ]);

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    /**
     * WooCommerce builds the session and the cart for front-end requests only,
     * so a REST request has to ask for them. wc_load_cart() is WooCommerce's
     * own entry point for that and is a no-op once the cart exists.
     */
    protected static function count(): int
    {
        if (! function_exists('WC')) {
            return 0;
        }

        if (WC()->cart === null && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (WC()->cart === null) {
            return 0;
        }

        return (int) WC()->cart->get_cart_contents_count();
    }
}
