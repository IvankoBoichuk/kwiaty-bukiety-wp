<?php

namespace App\Api;

use WP_REST_Request;
use WP_REST_Response;

class Healthcheck
{
    protected const ROUTE_NAMESPACE = 'sage/v1';

    protected const ROUTE = '/healthcheck';

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

        // Anyone can call this, so it says only that the site is up. The
        // environment and theme name told a caller whether they had found
        // staging and what to aim at.
        return new WP_REST_Response([
            'status' => 'ok',
            'timestamp' => gmdate('c'),
        ]);
    }
}
