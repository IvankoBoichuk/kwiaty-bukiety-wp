<?php

declare(strict_types=1);

namespace App\SEO;

use App\Modules\LocalLinking\LocalPageRepository;
use App\Modules\LocalLinking\Tables;

/**
 * The "Kwiaciarnie w Polsce" hub: the [kb_kwiaciarnie_pl] shortcode that lists
 * the 16 voivodeships (production snippet #45) and the JSON-LD graph for that
 * category page (snippet #49).
 *
 * Both used to repeat the same 16 entries, the first as markup and the second
 * as a hand-written JSON literal full of production URLs. They are generated
 * from one list here, and every link resolves through get_term_link(), so the
 * category permalink base is whatever this install actually uses.
 */
final class CityHub
{
    public const SHORTCODE = 'kb_kwiaciarnie_pl';

    public const HUB_SLUG = 'kwiaciarnie-w-polsce';

    /**
     * Slug => name. The slugs match bi_wojewodztwo.slug exactly; the list is
     * kept in code because it is a fixed administrative division and the hub
     * has to render even before the bi_* tables are imported.
     *
     * @var array<string, string>
     */
    public const VOIVODESHIPS = [
        'kwiaciarnia-dolnoslaskie' => 'Dolnośląskie',
        'kwiaciarnia-kujawsko-pomorskie' => 'Kujawsko-Pomorskie',
        'kwiaciarnia-lubelskie' => 'Lubelskie',
        'kwiaciarnia-lubuskie' => 'Lubuskie',
        'kwiaciarnia-lodzkie' => 'Łódzkie',
        'kwiaciarnia-malopolskie' => 'Małopolskie',
        'kwiaciarnia-mazowieckie' => 'Mazowieckie',
        'kwiaciarnia-opolskie' => 'Opolskie',
        'kwiaciarnia-podkarpackie' => 'Podkarpackie',
        'kwiaciarnia-podlaskie' => 'Podlaskie',
        'kwiaciarnia-pomorskie' => 'Pomorskie',
        'kwiaciarnia-slaskie' => 'Śląskie',
        'kwiaciarnia-swietokrzyskie' => 'Świętokrzyskie',
        'kwiaciarnia-warminsko-mazurskie' => 'Warmińsko-Mazurskie',
        'kwiaciarnia-wielkopolskie' => 'Wielkopolskie',
        'kwiaciarnia-zachodniopomorskie' => 'Zachodniopomorskie',
    ];

    public static function boot(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'renderShortcode']);
        add_action('wp_head', [self::class, 'renderSchema'], 20);
    }

    /**
     * @param  array<string, mixed>|string  $attributes
     */
    public static function renderShortcode($attributes = []): string
    {
        unset($attributes);

        $items = self::items();

        if ($items === []) {
            return '';
        }

        return view('seo.city-hub', ['voivodeships' => $items])->render();
    }

    public static function renderSchema(): void
    {
        if (! function_exists('is_product_category') || ! is_product_category(self::HUB_SLUG)) {
            return;
        }

        $items = self::items();

        if ($items === []) {
            return;
        }

        // WooCommerce already prints a BreadcrumbList for this page; snippet #49
        // added a second one. Only the ItemList is emitted here.
        $graph = [
            [
                '@type' => 'ItemList',
                'name' => __('Województwa', 'sage-front'),
                'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
                'numberOfItems' => count($items),
                'itemListElement' => array_values(array_map(
                    static fn(int $position, array $item): array => [
                        '@type' => 'ListItem',
                        'position' => $position + 1,
                        'name' => $item['name'],
                        'url' => $item['url'],
                    ],
                    array_keys($items),
                    $items,
                )),
            ],
        ];

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode(
                ['@context' => 'https://schema.org', '@graph' => $graph],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * The voivodeships that actually have a category on this install.
     *
     * Names come from bi_wojewodztwo when the tables are present, so an edit
     * there is reflected; the built-in list supplies both the order and the
     * fallback names.
     *
     * @return array<int, array{name: string, slug: string, url: string}>
     */
    public static function items(): array
    {
        $names = self::VOIVODESHIPS;

        if (Tables::exists()) {
            foreach (app(LocalPageRepository::class)->wojewodztwa() as $row) {
                $slug = (string) ($row->slug ?? '');
                $name = (string) ($row->name ?? '');

                if ($slug !== '' && $name !== '' && isset($names[$slug])) {
                    $names[$slug] = $name;
                }
            }
        }

        $items = [];

        foreach ($names as $slug => $name) {
            $term = get_term_by('slug', $slug, 'product_cat');

            if (! $term instanceof \WP_Term) {
                continue;
            }

            $url = get_term_link($term);

            if (! is_string($url) || $url === '') {
                continue;
            }

            $items[] = ['name' => $name, 'slug' => $slug, 'url' => $url];
        }

        return $items;
    }
}
