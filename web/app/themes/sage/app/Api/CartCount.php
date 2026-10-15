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
        add_filter('rest_authentication_errors', [self::class, 'authenticateByCookie'], 99);
    }

    /**
     * Keeps a logged-in customer logged in on this route.
     *
     * WordPress only trusts the login cookie on a REST request that also sends
     * an X-WP-Nonce, and drops to user 0 otherwise (rest_cookie_check_errors,
     * priority 100). The badge fetch sends none -- the page it runs on may be
     * a cached copy -- so the request arrived as a guest holding a logged-in
     * user's session cookie, which WooCommerce reads as "logged out" and
     * answers by destroying the session: the count came back 0 and the cart
     * the customer had just filled was emptied. Answering first leaves the
     * user the login cookie resolved to, the way the Store API does for its
     * own routes (Automattic\WooCommerce\StoreApi\Authentication).
     *
     * The route is a read-only GET that returns a number, so skipping the
     * nonce's CSRF check gives a cross-site page nothing to act on.
     *
     * @param  \WP_Error|null|true  $result
     * @return \WP_Error|null|true
     */
    public static function authenticateByCookie($result)
    {
        if ($result !== null || ! self::isRequestToRoute()) {
            return $result;
        }

        return true;
    }

    protected static function isRequestToRoute(): bool
    {
        $route = '/' . rest_get_url_prefix() . '/' . self::ROUTE_NAMESPACE . self::ROUTE;
        $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        if (untrailingslashit($path) === $route) {
            return true;
        }

        // Plain permalinks: /?rest_route=/sage/v1/cart-count
        return isset($_GET['rest_route'])
            && untrailingslashit((string) wp_unslash($_GET['rest_route'])) === '/' . self::ROUTE_NAMESPACE . self::ROUTE;
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
