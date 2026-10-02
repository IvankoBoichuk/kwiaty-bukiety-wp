<?php

declare(strict_types=1);

namespace App\SEO;

/**
 * og:image for product categories (production snippet #70).
 *
 * The category's own WooCommerce image wins; with none set, whatever Yoast
 * resolved stays. The original hardcoded a brand image URL on the production
 * domain, which would 404 anywhere else -- that fallback belongs in Yoast's
 * own "Default social image" setting, which already has a UI, so it is not
 * reimplemented here.
 */
final class OpenGraph
{
    public static function boot(): void
    {
        add_filter('wpseo_opengraph_image', [self::class, 'categoryImage']);
    }

    public static function categoryImage(mixed $image): mixed
    {
        if (! function_exists('is_product_category') || ! is_product_category()) {
            return $image;
        }

        $term = get_queried_object();

        if (! $term instanceof \WP_Term) {
            return $image;
        }

        $thumbnailId = (int) get_term_meta($term->term_id, 'thumbnail_id', true);

        if ($thumbnailId <= 0) {
            return $image;
        }

        $url = wp_get_attachment_image_url($thumbnailId, 'full');

        return is_string($url) && $url !== '' ? $url : $image;
    }
}
