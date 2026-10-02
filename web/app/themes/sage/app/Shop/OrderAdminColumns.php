<?php

declare(strict_types=1);

namespace App\Shop;

/**
 * NIP column in the orders list (production snippet #75).
 *
 * The original only hooked the legacy post-table filters. WooCommerce 11 runs
 * HPOS by default, where the orders list is its own table, so both sets of
 * hooks are registered and whichever storage is active gets the column.
 */
final class OrderAdminColumns
{
    public const COLUMN = 'billing_nip';

    /**
     * Checked in order. Orders imported from production carry `_billing_nip`;
     * this theme's checkout writes the field under its translated label (see
     * app/filters.php, woocommerce_checkout_create_order).
     *
     * @var array<int, string>
     */
    public const META_KEYS = ['_billing_nip', 'billing_nip'];

    public static function boot(): void
    {
        // HPOS.
        add_filter('woocommerce_shop_order_list_table_columns', [self::class, 'addColumn']);
        add_action('woocommerce_shop_order_list_table_custom_column', [self::class, 'renderHpos'], 10, 2);

        // Legacy post-based orders.
        add_filter('manage_edit-shop_order_columns', [self::class, 'addColumn']);
        add_action('manage_shop_order_posts_custom_column', [self::class, 'renderLegacy'], 10, 2);
    }

    /**
     * @param  mixed  $columns
     * @return mixed
     */
    public static function addColumn($columns)
    {
        if (! is_array($columns)) {
            return $columns;
        }

        $columns[self::COLUMN] = __('NIP', 'sage-back');

        return $columns;
    }

    public static function renderHpos(mixed $column, mixed $order): void
    {
        if ($column !== self::COLUMN || ! $order instanceof \WC_Order) {
            return;
        }

        self::output(self::nip($order));
    }

    public static function renderLegacy(mixed $column, mixed $postId): void
    {
        if ($column !== self::COLUMN) {
            return;
        }

        $order = wc_get_order((int) $postId);

        self::output($order instanceof \WC_Order ? self::nip($order) : '');
    }

    protected static function nip(\WC_Order $order): string
    {
        $keys = array_merge(self::META_KEYS, [__('NIP', 'sage-front')]);

        foreach ($keys as $key) {
            $value = $order->get_meta((string) $key, true);

            if (! is_array($value) && ! is_object($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    protected static function output(string $nip): void
    {
        if (trim($nip) === '') {
            echo '<span aria-hidden="true">&mdash;</span>';

            return;
        }

        echo esc_html($nip);
    }
}
