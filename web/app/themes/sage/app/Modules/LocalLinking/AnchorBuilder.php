<?php

declare(strict_types=1);

namespace App\Modules\LocalLinking;

/**
 * Anchor texts for the city cross-links.
 *
 * These strings are indexed, so the formula is kept bit-for-bit identical to
 * the production plugin (inc/db-helper.php::build_anchor_for_city): the same
 * city must keep the same anchor after the migration, or 1 879 local pages
 * change their internal anchor text at once.
 */
final class AnchorBuilder
{
    public const BRAND = 'Kwiaty-Bukiety';

    /**
     * @var array<int, string>
     */
    protected const TEMPLATES = [
        'kwiaciarnia %s',
        'poczta kwiatowa %s',
        'kwiaty do %s',
        '%s – kwiaty %s',
    ];

    /**
     * The production plugin always passed 0 as the current city id, so the hash
     * only ever depended on the linked city. Reproduced here deliberately.
     */
    public static function forCity(string $city, int $cityId, string $brand = self::BRAND): string
    {
        $hash = crc32(min(0, $cityId) . '-' . max(0, $cityId));
        $template = self::TEMPLATES[$hash % count(self::TEMPLATES)];

        if ($template === '%s – kwiaty %s') {
            return sprintf($template, $brand, $city);
        }

        return sprintf($template, $city);
    }

    /**
     * The branded form, used when a block would otherwise repeat an anchor.
     */
    public static function branded(string $city, string $brand = self::BRAND): string
    {
        return $brand . ' – kwiaty ' . $city;
    }

    /**
     * Turns repository rows into [{url, anchor}], collapsing a repeated anchor
     * onto the branded variant the way the original loop did.
     *
     * @param  array<int, object>  $rows
     * @return array<int, array{url: string, anchor: string}>
     */
    public static function links(array $rows, string $brand = self::BRAND): array
    {
        $used = [];
        $links = [];

        foreach ($rows as $row) {
            $city = (string) ($row->name ?? '');
            $url = self::url($row);

            if ($city === '' || $url === '') {
                continue;
            }

            $anchor = self::forCity($city, (int) ($row->miasto_id ?? $row->id ?? 0), $brand);

            if (isset($used[$anchor])) {
                $anchor = self::branded($city, $brand);
            }

            $used[$anchor] = true;
            $links[] = ['url' => $url, 'anchor' => $anchor];
        }

        return $links;
    }

    /**
     * Prefers the live permalink over the `url` column, which still holds
     * absolute production URLs and would send visitors off this install.
     */
    protected static function url(object $row): string
    {
        $termId = (int) ($row->term_id ?? 0);

        if ($termId > 0) {
            $link = get_term_link($termId, 'product_cat');

            if (is_string($link) && $link !== '') {
                return $link;
            }
        }

        return (string) ($row->url ?? '');
    }
}
