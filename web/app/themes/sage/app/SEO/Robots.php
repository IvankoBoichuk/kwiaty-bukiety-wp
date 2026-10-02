<?php

declare(strict_types=1);

namespace App\SEO;

/**
 * Indexing rules carried over from production Code Snippets #8, #9 and #11.
 *
 * Everything goes through Yoast's `wpseo_robots` filter, so a single meta tag
 * is produced. Snippet #44 did the same job as #11 by printing a second
 * `<meta name="robots">` into wp_head, which left two conflicting tags on the
 * page -- it is deliberately not migrated.
 */
final class Robots
{
    public static function boot(): void
    {
        add_filter('wpseo_robots', [self::class, 'filter']);
        add_filter('woocommerce_archive_description', [self::class, 'hideDescriptionOnPagedArchives'], 20);
    }

    public static function filter(mixed $robots): mixed
    {
        // #8 -- add-to-cart permalinks are duplicates of the product page.
        if (! empty($_GET['add-to-cart'])) {
            return 'noindex, nofollow';
        }

        // #9 -- page 2+ of a product category: keep the crawl, drop the index.
        if (function_exists('is_product_category') && is_product_category() && is_paged()) {
            return 'noindex, follow';
        }

        // #11 -- the catch-all blog category.
        if (is_category('bez-kategorii')) {
            return 'noindex, follow';
        }

        return $robots;
    }

    /**
     * #9 -- the category SEO copy belongs to page 1 only; repeating it on every
     * paged URL is what made them duplicates in the first place.
     */
    public static function hideDescriptionOnPagedArchives(mixed $description): mixed
    {
        if (function_exists('is_product_category') && is_product_category() && is_paged()) {
            return '';
        }

        return $description;
    }
}
