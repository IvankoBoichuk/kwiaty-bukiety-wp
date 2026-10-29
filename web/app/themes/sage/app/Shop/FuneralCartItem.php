<?php

declare(strict_types=1);

namespace App\Shop;

use App\Catalog\ProductData;
use WC_Cart;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Carries the wreath's own fields -- the deceased's name and the ribbon
 * message -- on its cart line and order line, and charges for the ribbon.
 *
 * The product page added the ribbon price to its "Cena łączna" but never sent
 * it, and the two fields only reached a session shared by the whole cart. So
 * the cart came out 20 zł short of the product page, showed neither field, and
 * the order filed the ribbon text as a card message.
 *
 * The ribbon price is captured when the wreath goes into the cart, so a price
 * changed in the settings applies to new carts and never reprices one that is
 * already open.
 */
final class FuneralCartItem
{
    /**
     * Cart line keys.
     */
    public const DECEASED_KEY = 'kb_deceased_full_name';

    public const RIBBON_KEY = 'kb_ribbon_message';

    public const RIBBON_PRICE_KEY = 'kb_ribbon_price';

    /**
     * Order line meta keys. Stable names rather than translated labels, so the
     * partner e-mail and the sheet find them whatever the admin's language;
     * the label is put on at display time.
     */
    public const DECEASED_META = 'deceased_full_name';

    public const RIBBON_META = 'ribbon_message';

    public static function boot(): void
    {
        add_filter('woocommerce_add_cart_item_data', [self::class, 'remember'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [self::class, 'applyRibbonPrice'], 10);
        add_filter('woocommerce_get_item_data', [self::class, 'itemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'saveToOrderItem'], 10, 3);
        add_filter('woocommerce_order_item_display_meta_key', [self::class, 'metaLabel'], 10, 2);
    }

    /**
     * @param  mixed  $cartItemData
     * @param  mixed  $productId  The parent, for a variation.
     * @return mixed
     */
    public static function remember($cartItemData, $productId, $variationId = 0)
    {
        unset($variationId);

        if (! is_array($cartItemData) || ! empty($cartItemData['is_sage_addition'])) {
            return $cartItemData;
        }

        $product = wc_get_product((int) $productId);

        if (! $product instanceof WC_Product || ! (new ProductData($product))->isFuneral()) {
            return $cartItemData;
        }

        $payload = PurchaseRequest::payload();

        if ($payload['deceased_full_name'] !== '') {
            $cartItemData[self::DECEASED_KEY] = $payload['deceased_full_name'];
        }

        if (trim($payload['card_message']) !== '') {
            $cartItemData[self::RIBBON_KEY] = trim($payload['card_message']);
            $cartItemData[self::RIBBON_PRICE_KEY] = FuneralOptions::ribbonPrice();
        }

        return $cartItemData;
    }

    /**
     * Sets the wreath's price to its catalogue price plus the ribbon. Read
     * fresh each time rather than added on, since the totals are calculated
     * more than once per request.
     */
    public static function applyRibbonPrice(mixed $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        if (! $cart instanceof WC_Cart) {
            return;
        }

        foreach ($cart->get_cart() as $cartItem) {
            $ribbonPrice = (float) ($cartItem[self::RIBBON_PRICE_KEY] ?? 0);
            $product = $cartItem['data'] ?? null;

            if ($ribbonPrice <= 0 || ! $product instanceof WC_Product) {
                continue;
            }

            $catalogue = wc_get_product($product->get_id());

            if (! $catalogue instanceof WC_Product) {
                continue;
            }

            $product->set_price((float) $catalogue->get_price() + $ribbonPrice);
        }
    }

    /**
     * What the cart and checkout show under the wreath.
     *
     * @param  mixed  $itemData
     * @param  mixed  $cartItem
     * @return mixed
     */
    public static function itemData($itemData, $cartItem)
    {
        if (! is_array($itemData) || ! is_array($cartItem)) {
            return $itemData;
        }

        $deceased = (string) ($cartItem[self::DECEASED_KEY] ?? '');
        $ribbon = (string) ($cartItem[self::RIBBON_KEY] ?? '');

        if ($deceased !== '') {
            $itemData[] = [
                'key' => __('Deceased\'s full name', 'sage-front'),
                'value' => $deceased,
            ];
        }

        if ($ribbon !== '') {
            $ribbonPrice = (float) ($cartItem[self::RIBBON_PRICE_KEY] ?? 0);

            $itemData[] = [
                'key' => $ribbonPrice > 0
                    ? sprintf(
                        /* translators: %s: ribbon price, e.g. "20 zł". */
                        __('Ribbon message (+%s)', 'sage-front'),
                        FuneralOptions::formatPrice($ribbonPrice),
                    )
                    : __('Ribbon message', 'sage-front'),
                'value' => $ribbon,
            ];
        }

        return $itemData;
    }

    public static function saveToOrderItem(mixed $item, mixed $cartItemKey, mixed $values): void
    {
        unset($cartItemKey);

        if (! $item instanceof WC_Order_Item_Product || ! is_array($values)) {
            return;
        }

        if (($values[self::DECEASED_KEY] ?? '') !== '') {
            $item->add_meta_data(self::DECEASED_META, wc_clean((string) $values[self::DECEASED_KEY]), true);
        }

        if (($values[self::RIBBON_KEY] ?? '') !== '') {
            $item->add_meta_data(
                self::RIBBON_META,
                sanitize_textarea_field((string) $values[self::RIBBON_KEY]),
                true,
            );
        }
    }

    /**
     * The order editor and the e-mails print the meta key as the label.
     */
    public static function metaLabel(mixed $displayKey, mixed $meta): mixed
    {
        $key = is_object($meta) ? (string) ($meta->key ?? '') : '';

        return match ($key) {
            self::DECEASED_META => __('Deceased\'s full name', 'sage-front'),
            self::RIBBON_META => __('Ribbon message', 'sage-front'),
            default => $displayKey,
        };
    }

    /**
     * Whether any wreath line carries its fields on itself, in which case the
     * order-level copies of them would only repeat it -- and file the ribbon
     * as a card message.
     */
    public static function cartCarriesFields(): bool
    {
        if (! (function_exists('WC') && WC()->cart instanceof WC_Cart)) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cartItem) {
            if (isset($cartItem[self::DECEASED_KEY]) || isset($cartItem[self::RIBBON_KEY])) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Deceased's full name: …; Ribbon message: …" for an order line, or ''
     * when it carries neither. The sheet appends this to the product name.
     */
    public static function orderItemNote(WC_Order_Item_Product $item): string
    {
        $parts = [];

        foreach ([
            self::DECEASED_META => __('Deceased\'s full name', 'sage-front'),
            self::RIBBON_META => __('Ribbon message', 'sage-front'),
        ] as $key => $label) {
            $value = trim((string) $item->get_meta($key, true));

            if ($value !== '') {
                $parts[] = $label . ': ' . preg_replace('/\s+/', ' ', $value);
            }
        }

        return implode('; ', $parts);
    }
}
