<?php

declare(strict_types=1);

namespace App\Catalog;

use WP_Term;

/**
 * The tile grid a product category can print above its product loop, stored as
 * the `kb_category_tiles` term meta.
 *
 * It replaces the production ACF repeater `kategoryi_repeater`, which is what
 * renders the twelve flower tiles on "Rodzaje kwiatow" there. Those tiles are
 * not the term's children -- on production and here alike the flower categories
 * sit at the root of the taxonomy -- so the list is curated per category rather
 * than queried, exactly as it was.
 *
 * A row is a category, and only a category is required: the link comes from
 * get_term_link(), and the picture and the caption fall back to the category's
 * own thumbnail and name. Production typed each link by hand into the repeater,
 * which is how one of its tiles ended up pointing at a product on the staging
 * domain; picking the term instead makes that class of row impossible.
 *
 * @phpstan-type Tile array{category: int, image: int, name: string}
 * @phpstan-type ResolvedTile array{link: string, name: string, image: int}
 */
final class CategoryTiles
{
    public const META_KEY = 'kb_category_tiles';

    public const TAXONOMY = 'product_cat';

    /**
     * The stored rows of a term, as the editor saved them.
     *
     * @return array<int, array{category: int, image: int, name: string}>
     */
    public static function forTerm(int $termId): array
    {
        if ($termId <= 0) {
            return [];
        }

        return self::normalize(get_term_meta($termId, self::META_KEY, true));
    }

    /**
     * The rows of the category being viewed, ready to render.
     *
     * @return array<int, array{link: string, name: string, image: int}>
     */
    public static function forCurrentTerm(): array
    {
        $term = get_queried_object();

        return $term instanceof WP_Term ? self::resolve(self::forTerm($term->term_id)) : [];
    }

    /**
     * A row is nothing without the category it points at; the picture and the
     * caption are optional overrides of what that category already carries.
     *
     * @return array<int, array{category: int, image: int, name: string}>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tiles = [];

        foreach ($value as $tile) {
            if (! is_array($tile)) {
                continue;
            }

            $category = max(0, (int) ($tile['category'] ?? 0));

            if ($category === 0) {
                continue;
            }

            $tiles[] = [
                'category' => $category,
                'image' => max(0, (int) ($tile['image'] ?? 0)),
                'name' => trim(wp_strip_all_tags((string) ($tile['name'] ?? ''))),
            ];
        }

        return $tiles;
    }

    /**
     * @param  array<int, array{category: int, image: int, name: string}>  $tiles
     */
    public static function save(int $termId, array $tiles): bool
    {
        $tiles = self::normalize($tiles);

        if ($tiles === []) {
            return (bool) delete_term_meta($termId, self::META_KEY);
        }

        return (bool) update_term_meta($termId, self::META_KEY, $tiles);
    }

    /**
     * Turns stored rows into what the view prints, filling each blank from the
     * category and dropping the rows whose category has since been deleted --
     * a tile with no destination is worse than no tile.
     *
     * @param  array<int, array{category: int, image: int, name: string}>  $tiles
     * @return array<int, array{link: string, name: string, image: int}>
     */
    public static function resolve(array $tiles): array
    {
        $resolved = [];

        foreach ($tiles as $tile) {
            $term = get_term((int) ($tile['category'] ?? 0), self::TAXONOMY);

            if (! $term instanceof WP_Term) {
                continue;
            }

            $link = get_term_link($term);

            if (! is_string($link)) {
                continue;
            }

            $name = (string) ($tile['name'] ?? '');

            $resolved[] = [
                'link' => $link,
                'name' => $name !== '' ? $name : $term->name,
                'image' => self::imageId($tile, $term),
            ];
        }

        return $resolved;
    }

    /**
     * The attachment a tile renders: its own picture, else the thumbnail of its
     * category.
     *
     * @param  array{category: int, image: int, name: string}  $tile
     */
    public static function imageId(array $tile, ?WP_Term $term = null): int
    {
        $imageId = max(0, (int) ($tile['image'] ?? 0));

        if ($imageId > 0 && wp_attachment_is_image($imageId)) {
            return $imageId;
        }

        if (! $term instanceof WP_Term) {
            $term = get_term((int) ($tile['category'] ?? 0), self::TAXONOMY);
        }

        if (! $term instanceof WP_Term) {
            return 0;
        }

        $thumbnailId = (int) get_term_meta($term->term_id, 'thumbnail_id', true);

        return $thumbnailId > 0 && wp_attachment_is_image($thumbnailId) ? $thumbnailId : 0;
    }
}
