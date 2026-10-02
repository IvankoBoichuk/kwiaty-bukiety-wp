<?php

declare(strict_types=1);

namespace App\Shop;

use WP_Query;

/**
 * Stable ordering for the shop and category archives (production snippet #7).
 *
 * Date alone leaves products imported in the same second in an arbitrary order,
 * which reshuffles pagination between requests; the id breaks the tie.
 */
final class CatalogOrder
{
    public static function boot(): void
    {
        add_action('pre_get_posts', [self::class, 'apply']);
    }

    public static function apply(mixed $query): void
    {
        if (! $query instanceof WP_Query || is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (! function_exists('is_shop') || (! is_shop() && ! is_product_category())) {
            return;
        }

        $query->set('orderby', ['date' => 'DESC', 'ID' => 'DESC']);
    }
}
