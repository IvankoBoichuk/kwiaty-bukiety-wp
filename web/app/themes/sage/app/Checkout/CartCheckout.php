<?php

namespace App\Checkout;

use WC_Cart;
use WC_Checkout;
use WC_Payment_Gateway;
use WC_Product;
use WC_Product_Variation;

class CartCheckout
{
    public function toArray(): array
    {
        $checkout = $this->checkout();
        return [
            'config' => [
                'currencySymbol' => $this->currencySymbol(),
                'cartUrl' => $this->cartUrl(),
                'checkoutUrl' => $this->checkoutUrl(),
                'items' => $this->items(),
                'totals' => $this->totals(),
                'paymentMethods' => $this->paymentMethods(),
                'selectedPaymentMethod' => $this->selectedPaymentMethod(),
                'routes' => [
                    'updateItem' => '/wp-json/wc/store/v1/cart/update-item',
                    'removeItem' => '/wp-json/wc/store/v1/cart/remove-item',
                ],
                'storeApiNonce' => (string) wp_create_nonce('wc_store_api'),
                'recipientFullName'
                    => (string) ($checkout?->get_value('shipping_first_name')
                        ?? ''),
                'shippingFirstName'
                    => (string) ($checkout?->get_value('shipping_first_name')
                        ?? ''),
                'shippingLastName' => '',
                'i18n' => $this->strings(),
            ],
            'fields' => $this->fields(),
            'paymentGateways' => $this->availableGateways(),
            'selectedPaymentMethod' => $this->selectedPaymentMethod(),
            'cartUrl' => $this->cartUrl(),
            'checkoutUrl' => $this->checkoutUrl(),
            'deliverySummary' => $this->deliverySummary(),
        ];
    }

    /**
     * The copy the cart and checkout scripts show.
     *
     * It travels in the config rather than through @wordpress/i18n, so the
     * bundle does not need the global wp.i18n and the two render-blocking
     * scripts behind it (see resources/js/modules/strings.ts).
     *
     * @return array<string, string>
     */
    protected function strings(): array
    {
        return [
            'placeRequired' => __('Select a delivery location.', 'sage-front'),
            'addressRequired' => __('Enter the delivery address.', 'sage-front'),
            'postcodeFormat' => __(
                'Postal code must use the format 00-000.',
                'sage-front',
            ),
            'postcodeRequired' => __('Enter the postal code.', 'sage-front'),
            'cityRequired' => __('Enter the city.', 'sage-front'),
            'recipientNameRequired' => __(
                'Enter the recipient full name.',
                'sage-front',
            ),
            'locationNameRequired' => __('Enter the location name.', 'sage-front'),
            'recipientPhoneRequired' => __(
                'Enter the recipient phone number.',
                'sage-front',
            ),
            'recipientPhoneInvalid' => __(
                'Enter a valid recipient phone number.',
                'sage-front',
            ),
            'senderFirstNameRequired' => __(
                'Enter the sender first name.',
                'sage-front',
            ),
            'senderLastNameRequired' => __(
                'Enter the sender last name.',
                'sage-front',
            ),
            'senderPhoneRequired' => __(
                'Enter the sender phone number.',
                'sage-front',
            ),
            'senderPhoneInvalid' => __(
                'Enter a valid sender phone number.',
                'sage-front',
            ),
            'emailInvalid' => __('Enter a valid email address.', 'sage-front'),
            'emailRequired' => __('Enter the email address.', 'sage-front'),
            'taxIdLength' => __('Tax ID must contain 10 digits.', 'sage-front'),
            'subtotal' => __('Subtotal', 'sage-front'),
            'delivery' => __('Delivery', 'sage-front'),
            'discount' => __('Discount', 'sage-front'),
            'orderTotal' => __('Order total', 'sage-front'),
            'orderFailed' => __('Unable to place the order.', 'sage-front'),
        ];
    }

    public function cartState(): array
    {
        return [
            'items' => $this->items(),
            'totals' => $this->totals(),
        ];
    }

    public function checkout(): ?WC_Checkout
    {
        if (!function_exists('WC')) {
            return null;
        }

        return WC()->checkout();
    }

    public function fields(): array
    {
        $checkout = $this->checkout();

        if (!($checkout instanceof WC_Checkout)) {
            return [];
        }

        $fields = $checkout->get_checkout_fields();

        return [
            'shipping_type_of_place' => $this->prepareField(
                $fields['shipping']['shipping_type_of_place'] ?? [],
                (string) $checkout->get_value('shipping_type_of_place'),
            ),
            'shipping_place_name' => $this->prepareField(
                $fields['shipping']['shipping_place_name'] ?? [],
                (string) $checkout->get_value('shipping_place_name'),
            ),
            'shipping_first_name' => $this->prepareField(
                $fields['shipping']['shipping_first_name'] ?? [],
                (string) $checkout->get_value('shipping_first_name'),
            ),
            'shipping_address_1' => $this->prepareField(
                $fields['shipping']['shipping_address_1'] ?? [],
                (string) $checkout->get_value('shipping_address_1'),
            ),
            'shipping_postcode' => $this->prepareField(
                $fields['shipping']['shipping_postcode'] ?? [],
                (string) $checkout->get_value('shipping_postcode'),
                ['x-mask' => '99-999'],
            ),
            'shipping_city' => $this->prepareField(
                $fields['shipping']['shipping_city'] ?? [],
                (string) $checkout->get_value('shipping_city'),
            ),
            'shipping_phone' => $this->prepareField(
                $fields['shipping']['shipping_phone'] ?? [],
                (string) $checkout->get_value('shipping_phone'),
            ),
            'billing_first_name' => $this->prepareField(
                $fields['billing']['billing_first_name'] ?? [],
                (string) $checkout->get_value('billing_first_name'),
            ),
            'billing_last_name' => $this->prepareField(
                $fields['billing']['billing_last_name'] ?? [],
                (string) $checkout->get_value('billing_last_name'),
            ),
            'billing_phone' => $this->prepareField(
                $fields['billing']['billing_phone'] ?? [],
                (string) $checkout->get_value('billing_phone'),
            ),
            'billing_email' => $this->prepareField(
                $fields['billing']['billing_email'] ?? [],
                (string) $checkout->get_value('billing_email'),
            ),
            'billing_nip' => $this->prepareField(
                $fields['billing']['billing_nip'] ?? [],
                (string) $checkout->get_value('billing_nip'),
                ['x-mask' => '9999999999'],
            ),
            'order_comments' => $this->prepareField(
                $fields['order']['order_comments'] ?? [],
                (string) $checkout->get_value('order_comments'),
            ),
        ];
    }

    /**
     * @return array<string, WC_Payment_Gateway>
     */
    public function availableGateways(): array
    {
        if (!function_exists('WC')) {
            return [];
        }

        $paymentGateways = WC()->payment_gateways();

        if (!$paymentGateways) {
            return [];
        }

        return $paymentGateways->get_available_payment_gateways();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function items(): array
    {
        $cart = $this->cart();

        if (!($cart instanceof WC_Cart)) {
            return [];
        }

        $items = [];

        foreach ($cart->get_cart() as $itemKey => $cartItem) {
            $product = $cartItem['data'] ?? null;

            if (!($product instanceof WC_Product)) {
                continue;
            }

            $imageId = $product->get_image_id();
            $lineTotal
                = (float) ($cartItem['line_total'] ?? 0)
                + (float) ($cartItem['line_tax'] ?? 0);
            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
            $unitTotal = $quantity > 0 ? $lineTotal / $quantity : $lineTotal;

            $items[] = [
                'key' => (string) $itemKey,
                'productId' => (int) $product->get_id(),
                'name' => $this->itemName($product),
                'quantity' => $quantity,
                'lineTotal' => $this->money($lineTotal),
                'unitPrice' => $this->money($unitTotal),
                'url' => function_exists('get_permalink')
                    ? (string) get_permalink($product->get_id())
                    : '',
                'image'
                    => $imageId > 0
                        ? (string) wp_get_attachment_image_url(
                            $imageId,
                            'woocommerce_thumbnail',
                        )
                        : '',
                'imageAlt'
                    => $imageId > 0
                        ? (string) get_post_meta(
                            $imageId,
                            '_wp_attachment_image_alt',
                            true,
                        )
                        : '',
                'summary' => $this->itemSummary($cartItem, $product),
            ];
        }

        return $items;
    }

    protected function deliverySummary(): string
    {
        if (!(function_exists('WC') && WC()->session)) {
            return '';
        }

        $parts = [];
        $deliveryDate = (string) WC()->session->get('delivery_date');
        $deliveryTime = (string) WC()->session->get('delivery_time');

        if ($deliveryDate !== '') {
            $parts[] = sprintf(
                '%s: %s',
                __('Delivery date', 'sage-front'),
                wc_clean($deliveryDate),
            );
        }

        if ($deliveryTime !== '') {
            $parts[] = sprintf(
                '%s: %s',
                __('Delivery time', 'sage-front'),
                wc_clean($deliveryTime),
            );
        }

        return $parts !== [] ? implode('<br>', $parts) : '';
    }

    protected function itemName(WC_Product $product): string
    {
        if (!($product instanceof WC_Product_Variation)) {
            return $product->get_name();
        }

        $parentId = $product->get_parent_id();
        $parentProduct = $parentId > 0 ? wc_get_product($parentId) : null;

        return $parentProduct instanceof WC_Product
            ? $parentProduct->get_name()
            : $product->get_name();
    }

    protected function itemSummary(array $cartItem, WC_Product $product): string
    {
        if (!($product instanceof WC_Product_Variation)) {
            return '';
        }

        $variation = $cartItem['variation'] ?? [];

        if (is_array($variation) && $variation !== []) {
            $summary = $this->variationSummary($variation, $product);

            if ($summary !== '') {
                return $summary;
            }
        }

        if (function_exists('wc_get_formatted_cart_item_data')) {
            $summary = $this->normalizeSummary(
                wc_get_formatted_cart_item_data($cartItem, true),
            );

            if ($summary !== '') {
                return $summary;
            }
        }

        $summary = $this->normalizeSummary(
            wc_get_formatted_variation($product, true, false, true),
        );

        return $summary;
    }

    protected function variationSummary(
        array $variation,
        WC_Product_Variation $product,
    ): string {
        $lines = [];

        foreach ($variation as $attribute => $value) {
            $label = $this->variationLabel((string) $attribute, $product);
            $formattedValue = $this->variationValue(
                (string) $attribute,
                (string) $value,
            );

            if ($label === '' || $formattedValue === '') {
                continue;
            }

            $lines[] = sprintf('%s: %s', $label, $formattedValue);
        }

        return implode(', ', $lines);
    }

    protected function variationLabel(
        string $attribute,
        WC_Product_Variation $product,
    ): string {
        $attributeName = preg_replace('/^attribute_/i', '', $attribute) ?: '';

        if ($attributeName === '') {
            return '';
        }

        $label = function_exists('wc_attribute_label')
            ? (string) wc_attribute_label($attributeName, $product)
            : '';

        if ($label !== '') {
            return $this->humanizeVariationLabel($label);
        }

        return $this->humanizeVariationLabel($attributeName);
    }

    protected function variationValue(string $attribute, string $value): string
    {
        $attributeName = preg_replace('/^attribute_/i', '', $attribute) ?: '';
        $cleanValue = trim(wp_strip_all_tags($value));

        if ($attributeName !== '' && taxonomy_exists($attributeName)) {
            $term = get_term_by('slug', $cleanValue, $attributeName);

            if ($term && !is_wp_error($term)) {
                return trim(wp_strip_all_tags((string) $term->name));
            }
        }

        return $cleanValue;
    }

    protected function normalizeSummary(string $summary): string
    {
        return preg_replace('/\s+/', ' ', trim(wp_strip_all_tags($summary)))
            ?: '';
    }

    protected function humanizeVariationLabel(string $label): string
    {
        $normalized = preg_replace('/^pa_/i', '', $label) ?: '';
        $normalized = preg_replace('/[-_]+/', ' ', $normalized) ?: '';
        $normalized = trim($normalized);

        return $normalized !== '' ? ucfirst($normalized) : '';
    }

    protected function totals(): array
    {
        $cart = $this->cart();

        if (!($cart instanceof WC_Cart)) {
            return [
                'subtotal' => $this->totalLine(__('Subtotal', 'sage-front'), 0),
                'shipping' => $this->totalLine(__('Dostawa', 'sage-front'), 0),
                'discount' => $this->totalLine(__('Discount', 'sage-front'), 0),
                'total' => $this->totalLine(__('Order Total', 'sage-front'), 0),
            ];
        }

        return [
            'subtotal' => $this->totalLine(
                __('Subtotal', 'sage-front'),
                (float) $cart->get_subtotal(),
            ),
            'shipping' => $this->totalLine(
                __('Dostawa', 'sage-front'),
                (float) $cart->get_shipping_total(),
            ),
            'discount' => $this->totalLine(
                __('Discount', 'sage-front'),
                (float) $cart->get_discount_total(),
            ),
            'total' => $this->totalLine(
                __('Order Total', 'sage-front'),
                (float) $cart->get_total('edit'),
            ),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function paymentMethods(): array
    {
        $methods = [];

        foreach ($this->availableGateways() as $gateway) {
            $methods[] = [
                'id' => (string) $gateway->id,
                'title' => wp_strip_all_tags((string) $gateway->get_title()),
                'description' => wp_strip_all_tags(
                    (string) $gateway->get_description(),
                ),
            ];
        }

        return $methods;
    }

    protected function selectedPaymentMethod(): string
    {
        $availableGateways = $this->availableGateways();

        if ($availableGateways === []) {
            return '';
        }

        if (function_exists('WC') && WC()->session) {
            $selected = (string) WC()->session->get('chosen_payment_method');

            if ($selected !== '' && isset($availableGateways[$selected])) {
                return $selected;
            }
        }

        return (string) array_key_first($availableGateways);
    }

    protected function prepareField(
        array $field,
        string $value,
        array $customAttributes = [],
    ): array {
        $field['input_class'] = array_values(
            array_filter(
                array_merge((array) ($field['input_class'] ?? []), [
                    'w-full rounded-[14px] border border-[#DDD7CF] bg-[#FCF9F6] px-4 py-3 text-[14px] leading-5 text-green-default placeholder:text-[#A4A094] focus:border-green-easy focus:outline-none',
                ]),
            ),
        );
        $field['class'] = array_values(
            array_filter(
                array_merge((array) ($field['class'] ?? []), [
                    'sage-cart-checkout-field',
                ]),
            ),
        );
        $field['label_class'] = array_values(
            array_filter(
                array_merge((array) ($field['label_class'] ?? []), [
                    'mb-1.5 block text-[14px] font-medium text-green-default',
                ]),
            ),
        );
        $field['custom_attributes'] = array_merge(
            (array) ($field['custom_attributes'] ?? []),
            $customAttributes,
        );
        $field['return'] = true;
        $field['value'] = $value;

        return $field;
    }

    protected function totalLine(string $label, float $amount): array
    {
        return [
            'label' => $label,
            'amount' => $this->money($amount),
        ];
    }

    protected function money(float $amount): array
    {
        return [
            'amount' => $amount,
            'formatted' => $this->formatMoney($amount),
        ];
    }

    protected function formatMoney(float $amount): string
    {
        $decimals = floor($amount) === $amount ? 0 : 2;

        return sprintf(
            '%s %s',
            number_format_i18n($amount, $decimals),
            $this->currencySymbol(),
        );
    }

    protected function currencySymbol(): string
    {
        $symbol = function_exists('get_woocommerce_currency_symbol')
            ? (string) get_woocommerce_currency_symbol()
            : 'zł';

        return html_entity_decode($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function cartUrl(): string
    {
        return function_exists('wc_get_cart_url')
            ? (string) wc_get_cart_url()
            : home_url('/cart');
    }

    protected function checkoutUrl(): string
    {
        return function_exists('wc_get_checkout_url')
            ? (string) wc_get_checkout_url()
            : home_url('/checkout');
    }

    protected function cart(): ?WC_Cart
    {
        if (!function_exists('WC')) {
            return null;
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        return WC()->cart;
    }
}
