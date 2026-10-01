<?php

declare(strict_types=1);

namespace App\Modules\LocalLinking;

/**
 * Internal linking between the city landing pages.
 *
 * On a city product category this prints three blocks -- neighbouring cities,
 * popular cities in the same voivodeship, and a link back to the voivodeship --
 * which is what ties 1 879 local pages together for search.
 *
 * Migrated from the "Перелінковка локальних сторінок" plugin. The data lives in
 * the `bi_*` tables, which the theme only reads; with those tables absent the
 * whole module is a no-op.
 */
final class LocalLinking
{
    public static function boot(): void
    {
        // The same hook the plugin used. archive-product.blade.php fires it
        // outside its container, so the rendered sections carry their own.
        add_action('woocommerce_after_main_content', [self::class, 'render'], 20);
    }

    public static function render(): void
    {
        $data = self::sections();

        if ($data === null) {
            return;
        }

        echo view('woocommerce.local-linking.sections', $data)->render();
    }

    /**
     * @return array{woj: string, near: array<int, array{url: string, anchor: string}>, popular: array<int, array{url: string, anchor: string}>, backlink: array{href: string, woj: string}}|null
     */
    public static function sections(): ?array
    {
        if (! function_exists('is_product_category') || ! is_product_category()) {
            return null;
        }

        if (! Tables::exists()) {
            return null;
        }

        $termId = get_queried_object_id();
        $repository = app(LocalPageRepository::class);

        if (! $repository->isCityCategory($termId)) {
            return null;
        }

        $wojewodztwo = $repository->wojewodztwoForTerm($termId);
        $wojName = is_object($wojewodztwo) ? (string) ($wojewodztwo->name ?? '') : '';

        $sections = [
            'woj' => $wojName,
            'near' => AnchorBuilder::links($repository->neighbours($termId)),
            'popular' => AnchorBuilder::links($repository->popularInWojewodztwo($termId)),
            'backlink' => [
                'href' => self::wojewodztwoUrl($wojewodztwo),
                'woj' => $wojName,
            ],
        ];

        if ($sections['near'] === [] && $sections['popular'] === [] && $sections['backlink']['href'] === '') {
            return null;
        }

        return $sections;
    }

    /**
     * The voivodeship landing page. The plugin hardcoded the production domain;
     * here the slug is resolved against the actual product_cat term.
     */
    protected static function wojewodztwoUrl(?object $wojewodztwo): string
    {
        $slug = is_object($wojewodztwo) ? (string) ($wojewodztwo->slug ?? '') : '';

        if ($slug === '') {
            return '';
        }

        $term = get_term_by('slug', $slug, 'product_cat');

        if (! $term instanceof \WP_Term) {
            return '';
        }

        $link = get_term_link($term);

        return is_string($link) ? $link : '';
    }
}
