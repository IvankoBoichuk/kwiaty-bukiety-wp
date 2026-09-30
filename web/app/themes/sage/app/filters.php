<?php

/**
 * Theme filters.
 */

namespace App;

use App\Support\Markup;
use App\Catalog\Settings;

/**
 * Add "… Continued" to the excerpt.
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(
        ' &hellip; <a href="%s">%s</a>',
        get_permalink(),
        __('Continued', 'sage-front'),
    );
});

add_filter(
    'woocommerce_format_price_range',
    fn($price, $from, $to) => \sprintf(
        __('from %s', 'sage-front'),
        wc_price($from),
    ),
    10,
    3,
);
add_filter('woocommerce_available_variation', function ($data) {
    $variation = wc_get_product($data['variation_id']);

    $attributes = $variation->get_attributes();

    $attribute_values = [];

    foreach ($attributes as $attribute_name => $attribute_value) {
        // Якщо це taxonomy (pa_color, pa_size)
        if (taxonomy_exists($attribute_name)) {
            $term = get_term_by('slug', $attribute_value, $attribute_name);
            if ($term && !is_wp_error($term)) {
                $attribute_values[] = $term->name;
            }
        } else {
            // custom attribute
            $attribute_values[] = $attribute_value;
        }
    }

    $data['name'] = implode(', ', $attribute_values);

    return $data;
});

add_filter(
    'woocommerce_checkout_fields',
    function ($fields) {
        $fields['shipping']['shipping_first_name']['class'] = [];
        $fields['shipping']['shipping_first_name']['label'] = __(
            'Full name', //'Imię i nazwisko',
            'sage-front',
        );
        $fields['shipping']['shipping_first_name']['placeholder'] = __(
            'e.g. John Doe', //'np. Jan Kowalski'
            'sage-front',
        );
        $fields['shipping']['shipping_first_name']['priority'] = 4;
        $fields['shipping']['shipping_first_name']['required'] = true;

        $fields['billing']['billing_email']['required'] = true;
        $fields['billing']['billing_email']['placeholder'] = __(
            'e.g. john.doe@example.com', //'np. jan.kowalski@example.com',
            'sage-front',
        );
        $fields['billing']['billing_email']['input_class'] = [];
        $fields['billing']['billing_email']['custom_attributes'] = [
            'inputmode' => 'email',
        ];

        $fields['billing']['billing_first_name']['class'] = [];
        $fields['billing']['billing_first_name']['placeholder'] = __(
            'e.g. John', //'np. Jan',
            'sage-front',
        );
        $fields['billing']['billing_last_name']['class'] = [];
        $fields['billing']['billing_last_name']['placeholder'] = __(
            'e.g. Doe', //'np. Kowalski',
            'sage-front',
        );
        $fields['billing']['billing_last_name']['required'] = true;
        $fields['billing']['billing_phone']['placeholder'] = __(
            'e.g. 123 456 789', //'np. 123 456 789',
            'sage-front',
        );
        $fields['billing']['billing_phone']['custom_attributes'] = [
            'inputmode' => 'tel',
        ];

        $fields['billing']['billing_nip'] = [
            'type' => 'text',
            'label' => __('NIP', 'sage-front'),
            'placeholder' => __('e.g. 1234567890', 'sage-front'),
            'required' => false,
            'class' => ['form-row-wide'],
            'clear' => true,
            'priority' => 10000,
            'custom_attributes' => ['inputmode' => 'numeric'],
        ];

        $fields['shipping']['shipping_phone'] = [
            'type' => 'tel',
            'label' => __('Phone number', 'sage-front'),
            'placeholder' => __('e.g. 123 456 789', 'sage-front'),
            'required' => true,
            'priority' => 100,
            'custom_attributes' => ['inputmode' => 'tel'],
        ];

        $fields['shipping']['shipping_postcode']['priority'] = 2;
        $fields['shipping']['shipping_postcode']['placeholder'] = __(
            'e.g. 00-001',
            'sage-front',
        );
        $fields['shipping']['shipping_postcode']['label'] = __(
            'Postal code',
            'sage-front',
        );
        $fields['shipping']['shipping_postcode']['required'] = true;
        $fields['shipping']['shipping_postcode']['custom_attributes'] = [
            'inputmode' => 'numeric',
        ];

        $fields['shipping']['shipping_city']['priority'] = 3;
        $fields['shipping']['shipping_city']['placeholder'] = __(
            'e.g. Warsaw',
            'sage-front',
        );
        $fields['shipping']['shipping_city']['label'] = __(
            'City',
            'sage-front',
        );
        $fields['shipping']['shipping_city']['required'] = true;

        $fields['shipping']['shipping_place_name'] = [
            'type' => 'text',
            'label' => __('Place name', 'sage-front'),
            'placeholder' => __('e.g. Hotel Marriott', 'sage-front'),
            'priority' => 5,
            'required' => false,
        ];

        $fields['shipping']['shipping_address_1']['label'] = __(
            'Street and number',
            'sage-front',
        );
        $fields['shipping']['shipping_address_1']['placeholder'] = __(
            'e.g. ul. Marszałkowska 1/5',
            'sage-front',
        );
        $fields['shipping']['shipping_address_1']['priority'] = 1;
        $fields['shipping']['shipping_address_1']['required'] = true;

        $fields['shipping']['shipping_type_of_place'] = [
            'type' => 'select',
            'label' => __('Delivery place', 'sage-front'),
            'placeholder' => __('Delivery place', 'sage-front'),
            'default' => 'placeholder',
            'options' => Settings::locationsOptions(),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true,
            'priority' => 0,
        ];

        foreach (
            [
                'billing_company',
                'billing_country',
                'billing_state',
                'billing_city',
                'billing_postcode',
                'billing_address_1',
                'billing_address_2',
                'shipping_company',
                'shipping_country',
                'shipping_state',
                'shipping_address_2',
            ] as $fieldKey
        ) {
            if (str_starts_with($fieldKey, 'billing_')) {
                unset($fields['billing'][$fieldKey]);
                continue;
            }

            unset($fields['shipping'][$fieldKey]);
        }

        $placeholderMap = [
            'order_comments' => __(
                'Notes about your order, e.g. specials notes for delivery',
                'sage-front',
            ),
        ];

        unset($fields['shipping']['shipping_last_name']);

        foreach ($placeholderMap as $fieldKey => $placeholder) {
            foreach (['billing', 'shipping', 'order'] as $groupKey) {
                if (!isset($fields[$groupKey][$fieldKey])) {
                    continue;
                }

                $fields[$groupKey][$fieldKey]['placeholder'] = $placeholder;
            }
        }

        foreach ($fields as $typeKey => &$typeFields) {
            unset($typeKey);

            foreach ($typeFields as $fieldKey => &$field) {
                unset($fieldKey);

                if (!isset($field['placeholder']) || !$field['placeholder']) {
                    $field['placeholder'] = isset($field['label'])
                        ? $field['label']
                        : 'placeholder';
                }
            }
        }

        unset($typeFields, $field);

        return $fields;
    },
    20,
);

function getAddToCartRequestPayload(
    \WP_REST_Request|array|null $request = null,
): array {
    static $jsonPayload;

    $source = [];

    if ($request instanceof \WP_REST_Request) {
        $source = $request->get_params();
    } elseif (is_array($request)) {
        $source = $request;
    } elseif ($_POST !== []) {
        $source = wp_unslash($_POST);
    } else {
        if ($jsonPayload === null) {
            $rawPayload = file_get_contents('php://input');
            $decodedPayload = is_string($rawPayload)
                ? json_decode($rawPayload, true)
                : null;

            $jsonPayload = is_array($decodedPayload) ? $decodedPayload : [];
        }

        $source = $jsonPayload;
    }

    return [
        'delivery_date' => sanitize_text_field(
            (string) ($source['delivery_date']
                ?? ($source['deliveryDate'] ?? '')),
        ),
        'delivery_time' => sanitize_text_field(
            (string) ($source['delivery_time']
                ?? ($source['deliveryTime'] ?? '')),
        ),
        'delivery_location' => sanitize_text_field(
            (string) ($source['delivery_location']
                ?? ($source['deliveryLocation'] ?? '')),
        ),
        'delivery_type' => sanitize_text_field(
            (string) ($source['delivery_type']
                ?? ($source['deliveryType'] ?? '')),
        ),
        'deceased_full_name' => sanitize_text_field(
            (string) ($source['deceased_full_name']
                ?? ($source['deceasedFullName'] ?? '')),
        ),
        'card_message' => sanitize_textarea_field(
            (string) ($source['card_message']
                ?? ($source['cardMessage'] ?? '')),
        ),
        'addition_ids' => array_values(
            array_filter(
                array_map(
                    'absint',
                    (array) ($source['addition_ids']
                        ?? ($source['additionIds'] ?? [])),
                ),
            ),
        ),
    ];
}

function formatDeliveryLocationValue(string $value): string
{
    return Settings::locationsOptions()[$value] ?? $value;
}

function formatDeliveryTypeValue(string $value): string
{
    return Settings::deliveryOptions()[$value] ?? $value;
}

function formatPurchaseMetaValue(string $metaKey, string $value): string
{
    $formatters = [
        'delivery_location' => 'formatDeliveryLocationValue',
        __('Delivery location', 'sage-front') => 'formatDeliveryLocationValue',
        'delivery_type' => 'formatDeliveryTypeValue',
        __('Delivery type', 'sage-front') => 'formatDeliveryTypeValue',
    ];

    $formatter = $formatters[$metaKey] ?? null;

    if (
        !is_string($formatter)
        || !function_exists(__NAMESPACE__ . '\\' . $formatter)
    ) {
        return $value;
    }

    return call_user_func(__NAMESPACE__ . '\\' . $formatter, $value);
}

function purchaseSessionKeys(): array
{
    return [
        'delivery_date',
        'delivery_time',
        'delivery_location',
        'delivery_type',
        'deceased_full_name',
        'card_message',
        'addition_ids',
    ];
}

function setPurchaseSessionData(array $payload): void
{
    if (!(function_exists('WC') && WC()->session)) {
        return;
    }

    foreach (purchaseSessionKeys() as $key) {
        WC()->session->set(
            $key,
            $payload[$key] ?? ($key === 'addition_ids' ? [] : ''),
        );
    }
}

function getPurchaseSessionData(): array
{
    if (!(function_exists('WC') && WC()->session)) {
        return [
            'delivery_date' => '',
            'delivery_time' => '',
            'delivery_location' => '',
            'delivery_type' => '',
            'deceased_full_name' => '',
            'card_message' => '',
            'addition_ids' => [],
        ];
    }

    return [
        'delivery_date' => (string) WC()->session->get('delivery_date'),
        'delivery_time' => (string) WC()->session->get('delivery_time'),
        'delivery_location' => (string) WC()->session->get('delivery_location'),
        'delivery_type' => (string) WC()->session->get('delivery_type'),
        'deceased_full_name' => (string) WC()->session->get(
            'deceased_full_name',
        ),
        'card_message' => (string) WC()->session->get('card_message'),
        'addition_ids' => array_values(
            array_filter(
                array_map(
                    'absint',
                    (array) WC()->session->get('addition_ids', []),
                ),
            ),
        ),
    ];
}

function clearPurchaseSessionData(): void
{
    if (!(function_exists('WC') && WC()->session)) {
        return;
    }

    foreach (purchaseSessionKeys() as $key) {
        WC()->session->set($key, $key === 'addition_ids' ? [] : '');
    }
}

add_filter(
    'woocommerce_add_cart_item_data',
    function ($cartItemData, $productId) {
        if (!empty($cartItemData['is_sage_addition'])) {
            return $cartItemData;
        }

        $payload = getAddToCartRequestPayload();
        unset($productId);

        setPurchaseSessionData($payload);

        return $cartItemData;
    },
    10,
    2,
);

add_filter(
    'woocommerce_store_api_add_to_cart_data',
    function ($add_to_cart_data, \WP_REST_Request $request) {
        $payload = getAddToCartRequestPayload($request);

        setPurchaseSessionData($payload);

        return $add_to_cart_data;
    },
    10,
    2,
);

$maybeClearCartForNewMainProduct = static function (
    int $requestedProductId,
    \WP_REST_Request|array|null $request = null,
): void {
    if (
        $requestedProductId <= 0
        || !(function_exists('WC') && WC()->cart instanceof \WC_Cart)
    ) {
        return;
    }

    foreach (WC()->cart->get_cart() as $cartItem) {
        if (!empty($cartItem['is_sage_addition'])) {
            continue;
        }

        $existingProductId = absint($cartItem['variation_id'] ?? 0);

        if ($existingProductId <= 0) {
            $existingProductId = absint($cartItem['product_id'] ?? 0);
        }

        if (
            $existingProductId > 0
            && $existingProductId !== $requestedProductId
        ) {
            $currentPayload = getAddToCartRequestPayload($request);

            WC()->cart->empty_cart();
            clearPurchaseSessionData();

            if (
                array_filter(
                    $currentPayload,
                    static fn($value) => $value !== [] && $value !== '',
                )
            ) {
                setPurchaseSessionData($currentPayload);
            }

            return;
        }
    }
};

add_filter(
    'woocommerce_add_to_cart_validation',
    function (
        $passed,
        $productId,
        $quantity,
        $variationId = 0,
        $variations = [],
        $cartItemData = [],
    ) use ($maybeClearCartForNewMainProduct) {
        unset($quantity, $variations);

        if (!empty($cartItemData['is_sage_addition'])) {
            return $passed;
        }

        if ($passed !== false) {
            $maybeClearCartForNewMainProduct(
                absint($variationId) ?: absint($productId),
                null,
            );
        }

        return $passed;
    },
    10,
    6,
);

add_action(
    'woocommerce_store_api_validate_add_to_cart',
    function ($product, $request) use ($maybeClearCartForNewMainProduct) {
        unset($product);

        $maybeClearCartForNewMainProduct(absint($request['id'] ?? 0), $request);

        $additionIds = array_values(
            array_filter(
                array_map('absint', (array) ($request['additionIds'] ?? [])),
            ),
        );

        foreach ($additionIds as $additionId) {
            if (!(wc_get_product($additionId) instanceof \WC_Product)) {
                throw new \Exception(
                    __('One of the additions was not found.', 'sage-front'),
                );
            }
        }
    },
    10,
    2,
);

add_action(
    'woocommerce_cart_emptied',
    __NAMESPACE__ . '\\clearPurchaseSessionData',
);

add_action(
    'woocommerce_add_to_cart',
    function (
        $cartItemKey,
        $productId,
        $quantity,
        $variationId,
        $variation,
        $cartItemData,
    ) {
        unset($productId, $variationId, $variation);

        if (!empty($cartItemData['is_sage_addition'])) {
            return;
        }

        $purchaseData = getPurchaseSessionData();
        $additionIds = $purchaseData['addition_ids'];

        if (
            $additionIds === []
            || !(function_exists('WC') && WC()->cart instanceof \WC_Cart)
        ) {
            return;
        }

        $addedItemKeys = [];

        foreach ($additionIds as $additionId) {
            $additionCartItemKey = WC()->cart->add_to_cart(
                absint($additionId),
                max(1, (int) $quantity),
                0,
                [],
                ['is_sage_addition' => true],
            );

            if ($additionCartItemKey !== false) {
                $addedItemKeys[] = $additionCartItemKey;
                continue;
            }

            foreach ($addedItemKeys as $addedItemKey) {
                WC()->cart->remove_cart_item($addedItemKey);
            }

            WC()->cart->remove_cart_item((string) $cartItemKey);

            if (function_exists('wc_add_notice')) {
                wc_add_notice(
                    __(
                        'Unable to add one of the additions to cart.',
                        'sage-front',
                    ),
                    'error',
                );
            }

            break;
        }

        if (WC()->session) {
            WC()->session->set('addition_ids', []);
        }
    },
    10,
    6,
);

// Add specific CSS class by filter.
add_filter('body_class', function ($classes) {
    return array_merge($classes, ['flex', 'flex-col', 'min-h-screen']);
});

add_filter(
    'woocommerce_get_item_data',
    function ($itemData, $cartItem) {
        unset($cartItem);

        return $itemData;
    },
    10,
    2,
);

function getOrderPurchaseMetaFromCart(): array
{
    $purchaseData = getPurchaseSessionData();
    $meta = [];

    if ($purchaseData['delivery_location'] !== '') {
        $meta[__('Delivery location', 'sage-front')] = wc_clean(
            $purchaseData['delivery_location'],
        );
    }

    if ($purchaseData['delivery_type'] !== '') {
        $meta[__('Delivery type', 'sage-front')] = wc_clean(
            $purchaseData['delivery_type'],
        );
    }

    if ($purchaseData['deceased_full_name'] !== '') {
        $meta[__('Deceased\'s full name', 'sage-front')] = wc_clean(
            $purchaseData['deceased_full_name'],
        );
    }

    if ($purchaseData['card_message'] !== '') {
        $meta[__('Card message content', 'sage-front')] = wc_clean(
            $purchaseData['card_message'],
        );
    }

    if ($purchaseData['addition_ids'] !== []) {
        $additionNames = [];

        foreach ($purchaseData['addition_ids'] as $additionId) {
            $addition = wc_get_product(absint($additionId));

            if ($addition instanceof \WC_Product) {
                $additionNames[] = $addition->get_name();
            }
        }

        if ($additionNames !== []) {
            $meta[__('Add-ons', 'sage-front')] = wc_clean(
                implode(', ', $additionNames),
            );
        }
    }

    return $meta;
}

function addOrderPurchaseMeta(\WC_Order $order): void
{
    foreach (getOrderPurchaseMetaFromCart() as $metaLabel => $metaValue) {
        $order->update_meta_data($metaLabel, $metaValue);
    }
}

add_action(
    'woocommerce_checkout_create_order_line_item',
    function ($item, $cartItemKey, $values) {
        unset($item, $cartItemKey, $values);
    },
    10,
    3,
);

add_action(
    'woocommerce_checkout_create_order',
    function ($order, $data) {
        foreach (
            [
                'shipping_type_of_place' => __(
                    'Delivery place type',
                    'sage-front',
                ),
                'shipping_place_name' => __('Place name', 'sage-front'),
                'shipping_phone' => __('Recipient phone', 'sage-front'),
                'billing_nip' => __('NIP', 'sage-front'),
            ] as $fieldKey => $metaLabel
        ) {
            if (empty($data[$fieldKey])) {
                continue;
            }

            $order->update_meta_data(
                $metaLabel,
                wc_clean((string) $data[$fieldKey]),
            );
        }

        $purchaseData = getPurchaseSessionData();

        if ($purchaseData['delivery_date'] !== '') {
            $order->update_meta_data(
                'delivery_date',
                wc_clean($purchaseData['delivery_date']),
            );
        }

        if ($purchaseData['delivery_time'] !== '') {
            $order->update_meta_data(
                'delivery_time',
                wc_clean($purchaseData['delivery_time']),
            );
        }

        addOrderPurchaseMeta($order);
    },
    10,
    2,
);

add_action(
    'woocommerce_after_checkout_validation',
    function ($data, $errors) {
        $shippingTypeOfPlace = wc_clean(
            (string) ($data['shipping_type_of_place'] ?? ''),
        );
        $shippingPlaceName = wc_clean(
            (string) ($data['shipping_place_name'] ?? ''),
        );

        if (
            $shippingTypeOfPlace === 'private-address'
            && $shippingPlaceName === ''
            && $errors instanceof \WP_Error
        ) {
            $errors->add(
                'shipping_place_name_required',
                __('Please provide the place name.', 'sage-front'),
            );
        }
    },
    10,
    2,
);

add_action(
    'woocommerce_store_api_checkout_update_order_from_request',
    function (\WC_Order $order, \WP_REST_Request $request) {
        $additionalFields = (array) ($request['additional_fields'] ?? []);

        foreach (
            [
                'shipping_type_of_place' => __(
                    'Delivery place type',
                    'sage-front',
                ),
                'shipping_place_name' => __('Place name', 'sage-front'),
                'shipping_phone' => __('Recipient phone', 'sage-front'),
                'billing_nip' => __('NIP', 'sage-front'),
            ] as $fieldKey => $metaLabel
        ) {
            if (empty($additionalFields[$fieldKey])) {
                continue;
            }

            $order->update_meta_data(
                $metaLabel,
                wc_clean((string) $additionalFields[$fieldKey]),
            );
        }

        $purchaseData = getPurchaseSessionData();

        if ($purchaseData['delivery_date'] !== '') {
            $order->update_meta_data(
                'delivery_date',
                wc_clean($purchaseData['delivery_date']),
            );
        }

        if ($purchaseData['delivery_time'] !== '') {
            $order->update_meta_data(
                'delivery_time',
                wc_clean($purchaseData['delivery_time']),
            );
        }

        addOrderPurchaseMeta($order);
    },
    10,
    2,
);

add_action(
    'woocommerce_admin_order_data_after_shipping_address',
    function (\WC_Order $order) {
        $metaFields = [
            'delivery_date' => __('Delivery date', 'sage-front'),
            'delivery_time' => __('Delivery time', 'sage-front'),
            __('Delivery location', 'sage-front') => __(
                'Delivery location',
                'sage-front',
            ),
            __('Delivery type', 'sage-front') => __(
                'Delivery type',
                'sage-front',
            ),
            __('Deceased\'s full name', 'sage-front') => __(
                'Deceased\'s full name',
                'sage-front',
            ),
            __('Card message content', 'sage-front') => __(
                'Card message content',
                'sage-front',
            ),
            __('Add-ons', 'sage-front') => __('Add-ons', 'sage-front'),
        ];

        $rows = [];

        foreach ($metaFields as $metaKey => $metaLabel) {
            $metaValue = $order->get_meta($metaKey, true);

            if ($metaValue === '') {
                continue;
            }

            $formattedValue = is_scalar($metaValue)
                ? formatPurchaseMetaValue($metaKey, (string) $metaValue)
                : wp_json_encode($metaValue);

            $rows[] = sprintf(
                '<p><strong>%s:</strong> %s</p>',
                esc_html($metaLabel),
                esc_html((string) $formattedValue),
            );
        }

        if ($rows === []) {
            return;
        }

        echo '<div class="address-order-meta">'
            . wp_kses_post(implode('', $rows))
            . '</div>';
    },
    10,
    1,
);

add_action('woocommerce_before_add_to_cart_button', function () {
    echo '<input type="hidden" name="delivery_date" value="" data-delivery-date-hidden>';
    echo '<input type="hidden" name="delivery_time" value="" data-delivery-time-hidden>';
    echo '<input type="hidden" name="card_message" value="" data-card-message-hidden>';
    echo '<div data-addition-inputs hidden></div>';
});

remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);

add_action('woocommerce_after_shop_loop', function () {
    global $wp_query;

    if (!($wp_query instanceof \WP_Query)) {
        return;
    }

    $currentPage = max(
        1,
        (int) get_query_var('paged'),
        (int) get_query_var('page'),
    );
    $maxPages = (int) $wp_query->max_num_pages;

    if ($maxPages <= 1 || $currentPage >= $maxPages) {
        return;
    }

    $nextPageUrl = get_pagenum_link($currentPage + 1);

    if (!is_string($nextPageUrl) || $nextPageUrl === '') {
        return;
    }

    printf(
        '<div class="mt-8 flex justify-center" data-products-load-more><button type="button" data-products-load-more-button data-next-url="%s" data-default-label="%s" data-loading-label="%s" class="%s"><span>%s</span></button></div>',
        esc_url($nextPageUrl),
        esc_attr__('Show more', 'sage-front'),
        esc_attr__('Loading...', 'sage-front'),
        esc_attr(Markup::buttonClasses('border', 'md', false)),
        esc_html__('Show more', 'sage-front'),
    );
});

remove_action(
    'woocommerce_before_main_content',
    'woocommerce_output_content_wrapper',
    10,
);
remove_action(
    'woocommerce_after_main_content',
    'woocommerce_output_content_wrapper_end',
    10,
);
