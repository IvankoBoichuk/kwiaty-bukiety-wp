<?php

declare(strict_types=1);

namespace App\Shop;

use WC_Product;

/**
 * What the funeral product page sells on top of the wreath: the add-on
 * products offered under it and the price of the ribbon message.
 *
 * Both were hardcoded -- the candle as product 1835 in Catalog\Settings and the
 * ribbon as 20 PLN in the product script, which the cart never charged. They
 * are site settings here (Products -> Funeral settings), so the shop can change
 * them without a deploy.
 */
final class FuneralOptions
{
    public const OPTION_NAME = 'sage_funeral_options';

    public const DEFAULT_RIBBON_PRICE = 20.0;

    /**
     * @return array{addition_ids: array<int, int>, ribbon_price: float}
     */
    public static function defaultOptions(): array
    {
        return [
            'addition_ids' => [],
            'ribbon_price' => self::DEFAULT_RIBBON_PRICE,
        ];
    }

    /**
     * @return array{addition_ids: array<int, int>, ribbon_price: float}
     */
    public static function settings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        return [
            'addition_ids' => self::ids($stored['addition_ids'] ?? []),
            'ribbon_price' => isset($stored['ribbon_price']) && is_numeric($stored['ribbon_price'])
                ? max(0.0, (float) $stored['ribbon_price'])
                : self::DEFAULT_RIBBON_PRICE,
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function additionIds(): array
    {
        return self::ids(apply_filters(
            'sage/shop/funeral_addition_ids',
            self::settings()['addition_ids'],
        ));
    }

    /**
     * The add-ons that can still be bought: a product deleted or unpublished
     * since it was picked is left out rather than offered and then refused.
     *
     * @return array<int, WC_Product>
     */
    public static function additionProducts(): array
    {
        return array_values(array_filter(
            array_map('wc_get_product', self::additionIds()),
            static fn(mixed $product): bool => $product instanceof WC_Product
                && $product->get_status() === 'publish'
                && $product->is_purchasable(),
        ));
    }

    public static function ribbonPrice(): float
    {
        return max(0.0, (float) apply_filters(
            'sage/shop/funeral_ribbon_price',
            self::settings()['ribbon_price'],
        ));
    }

    /**
     * "20 zł" -- plain text for labels, without the decimals a whole price
     * does not need.
     */
    public static function formatPrice(float $price): string
    {
        $decimals = floor($price) === $price ? 0 : wc_get_price_decimals();

        return html_entity_decode(
            wp_strip_all_tags(wc_price($price, ['decimals' => $decimals])),
            ENT_QUOTES,
            'UTF-8',
        );
    }

    /**
     * @return array<int, int>
     */
    protected static function ids(mixed $value): array
    {
        return array_values(array_unique(array_filter(
            array_map('absint', (array) $value),
        )));
    }
}
