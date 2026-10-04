<?php

declare(strict_types=1);

namespace App\Shop;

use WC_Cart;
use WC_Product;

/**
 * Quantity discount and minimum order for the loose-stem product
 * (production snippet #63).
 *
 * The original hardcoded product 616, a minimum of 7 and two tiers inside the
 * snippet body, and printed its own <style> block into wp_head. Here the
 * numbers are configuration and the notice is a Blade partial on the product
 * page, so the shop can retune it without touching PHP.
 */
final class WholesaleDiscount
{
    public const PRODUCT_ID = 616;

    public const MIN_QUANTITY = 7;

    /**
     * Quantity threshold => discount fraction, highest threshold first.
     *
     * @var array<int, float>
     */
    public const TIERS = [
        50 => 0.10,
        25 => 0.05,
    ];

    public const BASE_PRICE_KEY = 'kb_base_price';

    public static function boot(): void
    {
        add_filter('woocommerce_quantity_input_args', [self::class, 'quantityArgs'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validate'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [self::class, 'rememberBasePrice'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [self::class, 'applyDiscount'], 20);
    }

    public static function appliesTo(int $productId): bool
    {
        return $productId === self::productId();
    }

    /**
     * @return array<int, float>
     */
    public static function tiers(): array
    {
        /**
         * @param  array<int, float>  $tiers  Quantity threshold => discount fraction.
         */
        $tiers = (array) apply_filters('sage/shop/wholesale_tiers', self::TIERS);

        krsort($tiers);

        return $tiers;
    }

    public static function productId(): int
    {
        return (int) apply_filters('sage/shop/wholesale_product_id', self::PRODUCT_ID);
    }

    public static function minimumQuantity(): int
    {
        return (int) apply_filters('sage/shop/wholesale_min_quantity', self::MIN_QUANTITY);
    }

    /**
     * @param  mixed  $args
     * @param  mixed  $product
     * @return mixed
     */
    public static function quantityArgs($args, $product)
    {
        if (! is_array($args) || ! $product instanceof WC_Product || ! self::appliesTo($product->get_id())) {
            return $args;
        }

        $min = self::minimumQuantity();
        $args['min_value'] = $min;

        // Only seed the field where nothing was typed -- the cart passes the
        // real quantity through here too.
        if (empty($args['input_value']) || (int) $args['input_value'] < $min) {
            $args['input_value'] = $min;
        }

        return $args;
    }

    public static function validate(mixed $passed, mixed $productId, mixed $quantity): mixed
    {
        if (! self::appliesTo((int) $productId)) {
            return $passed;
        }

        $min = self::minimumQuantity();

        if ((int) $quantity < $min) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: minimum number of stems. */
                    __('The minimum order quantity for this product is %d pcs.', 'sage-front'),
                    $min,
                ),
                'error',
            );

            return false;
        }

        return $passed;
    }

    /**
     * @param  mixed  $cartItemData
     * @param  mixed  $productId
     * @return mixed
     */
    public static function rememberBasePrice($cartItemData, $productId)
    {
        if (! is_array($cartItemData) || ! self::appliesTo((int) $productId)) {
            return $cartItemData;
        }

        $product = wc_get_product((int) $productId);

        if ($product instanceof WC_Product) {
            // Captured once, so recalculating the cart never discounts an
            // already discounted price.
            $cartItemData[self::BASE_PRICE_KEY] = (float) $product->get_price();
        }

        return $cartItemData;
    }

    public static function applyDiscount(mixed $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        if (! $cart instanceof WC_Cart || $cart->is_empty()) {
            return;
        }

        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;

            if (! $product instanceof WC_Product || ! self::appliesTo($product->get_id())) {
                continue;
            }

            $quantity = (int) ($cartItem['quantity'] ?? 0);
            $base = (float) ($cartItem[self::BASE_PRICE_KEY] ?? $product->get_price());
            $discount = self::discountFor($quantity);

            $product->set_price($base * (1 - $discount));
        }
    }

    public static function discountFor(int $quantity): float
    {
        foreach (self::tiers() as $threshold => $discount) {
            if ($quantity >= (int) $threshold) {
                return (float) $discount;
            }
        }

        return 0.0;
    }
}
