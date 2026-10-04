<?php

declare(strict_types=1);

namespace App\Shop;

use WP_Error;

/**
 * Closes the REST users endpoint to anonymous callers (production snippet #86).
 *
 * /wp-json/wp/v2/users otherwise enumerates every account name on the site.
 */
final class RestApiGuard
{
    public static function boot(): void
    {
        add_filter('rest_pre_dispatch', [self::class, 'blockUserEnumeration'], 10, 3);
    }

    public static function blockUserEnumeration(mixed $result, mixed $server, mixed $request): mixed
    {
        if (! $request instanceof \WP_REST_Request || is_user_logged_in()) {
            return $result;
        }

        if (preg_match('#^/wp/v2/users(?:/\d+)?/?$#', (string) $request->get_route()) !== 1) {
            return $result;
        }

        return new WP_Error(
            'rest_forbidden_users',
            __('Access to the user list is forbidden.', 'sage-front'),
            ['status' => 403],
        );
    }
}
