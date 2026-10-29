<?php

declare(strict_types=1);

namespace App\Admin;

use App\Shop\FuneralOptions;
use WC_Product;

/**
 * Products -> Funeral settings: the add-ons offered under a wreath and the
 * ribbon message price.
 *
 * The add-on picker is WooCommerce's own product search; the page is added to
 * its screen IDs so WooCommerce loads that script and its styles here.
 */
class FuneralSettingsPage
{
    protected const PAGE_SLUG = 'sage-funeral-settings';

    protected const SETTINGS_GROUP = 'sage_funeral_settings';

    protected const SCREEN_ID = 'product_page_' . self::PAGE_SLUG;

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'registerPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_filter('option_page_capability_' . self::SETTINGS_GROUP, [self::class, 'optionCapability']);
        add_filter('woocommerce_screen_ids', [self::class, 'screenIds']);
        add_action('update_option_' . FuneralOptions::OPTION_NAME, [self::class, 'purgePageCache']);
    }

    public static function registerPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Funeral settings', 'sage-back'),
            __('Funeral settings', 'sage-back'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [self::class, 'renderPage'],
        );
    }

    public static function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            FuneralOptions::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitizeOptions'],
                'default' => FuneralOptions::defaultOptions(),
                'show_in_rest' => false,
            ],
        );
    }

    /**
     * options.php checks `manage_options` unless told otherwise, which would
     * lock a shop manager out of a page the menu shows them.
     */
    public static function optionCapability(): string
    {
        return 'manage_woocommerce';
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    public static function screenIds(mixed $ids): mixed
    {
        if (! is_array($ids)) {
            return $ids;
        }

        $ids[] = self::SCREEN_ID;

        return $ids;
    }

    /**
     * The add-ons and the ribbon price are printed into the funeral product
     * pages, which the page cache keeps for a week.
     */
    public static function purgePageCache(): void
    {
        do_action('litespeed_purge_all', 'funeral settings changed');
    }

    /**
     * @return array{addition_ids: array<int, int>, ribbon_price: float}
     */
    public static function sanitizeOptions(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $current = FuneralOptions::settings();

        $additionIds = array_values(array_unique(array_filter(
            array_map('absint', (array) ($input['addition_ids'] ?? [])),
            static fn(int $id): bool => $id > 0 && wc_get_product($id) instanceof WC_Product,
        )));

        $priceInput = trim(str_replace(',', '.', (string) ($input['ribbon_price'] ?? '')));
        $ribbonPrice = $current['ribbon_price'];

        if ($priceInput === '') {
            $ribbonPrice = 0.0;
        } elseif (is_numeric($priceInput) && (float) $priceInput >= 0) {
            $ribbonPrice = round((float) $priceInput, wc_get_price_decimals());
        } else {
            add_settings_error(
                FuneralOptions::OPTION_NAME,
                'ribbon_price',
                __('The ribbon message price must be a number of 0 or more; the previous price is kept.', 'sage-back'),
            );
        }

        return [
            'addition_ids' => $additionIds,
            'ribbon_price' => $ribbonPrice,
        ];
    }

    public static function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $options = FuneralOptions::settings();
        $optionName = FuneralOptions::OPTION_NAME;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Funeral settings', 'sage-back'); ?></h1>

            <p class="description">
                <?php echo esc_html__('What the wreath page offers on top of the wreath itself.', 'sage-back'); ?>
            </p>

            <?php settings_errors(); ?>

            <form action="options.php" method="post">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="sage-funeral-additions">
                                <?php echo esc_html__('Add-on products', 'sage-back'); ?>
                            </label>
                        </th>
                        <td>
                            <select
                                id="sage-funeral-additions"
                                class="wc-product-search"
                                multiple="multiple"
                                style="width: 50%;"
                                name="<?php echo esc_attr($optionName); ?>[addition_ids][]"
                                data-placeholder="<?php echo esc_attr__('Search for a product…', 'sage-back'); ?>"
                                data-action="woocommerce_json_search_products_and_variations"
                            >
                                <?php foreach ($options['addition_ids'] as $productId) {
                                    $product = wc_get_product($productId);

                                    if (! $product instanceof WC_Product) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr((string) $productId); ?>" selected="selected">
                                        <?php echo esc_html(wp_strip_all_tags($product->get_formatted_name())); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Offered under every wreath, in this order, and added to the cart as separate lines. Leave empty to offer none.', 'sage-back'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sage-funeral-ribbon-price">
                                <?php echo esc_html__('Ribbon message price', 'sage-back'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="sage-funeral-ribbon-price"
                                class="small-text"
                                min="0"
                                step="0.01"
                                name="<?php echo esc_attr($optionName); ?>[ribbon_price]"
                                value="<?php echo esc_attr((string) $options['ribbon_price']); ?>"
                            />
                            <?php echo esc_html(get_woocommerce_currency_symbol()); ?>
                            <p class="description">
                                <?php echo esc_html__('Added to the wreath price when the customer fills in the ribbon message. 0 makes the ribbon free. A cart that already holds a wreath keeps the price it was added at.', 'sage-back'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
