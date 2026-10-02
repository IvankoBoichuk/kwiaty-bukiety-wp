<?php

/**
 * Theme setup.
 */

namespace App;

use _WP_Dependency;
use Illuminate\Support\Facades\Vite;
use WP_Customize_Image_Control;
use WP_Customize_Manager;

Vite::useHotFile(get_theme_file_path('public/hot'));
Vite::useBuildDirectory('build');

/**
 * Inject styles into the block editor.
 *
 * @return array
 */
add_filter('block_editor_settings_all', function ($settings) {
    $style = Vite::asset('resources/css/editor.css');

    $settings['styles'][] = [
        'css' => "@import url('{$style}')",
    ];

    return $settings;
});

/**
 * Feed the front-end bundle its translations.
 *
 * The theme's scripts are emitted by Vite as bare module tags, not through
 * wp_enqueue_script, so there is no handle for wp_set_script_translations()
 * to hang the catalogue on -- and without it every __() call in the checkout
 * and product JS rendered in English, including the form validation messages.
 * Merge the generated JED catalogues by hand instead.
 *
 * @return string
 */
function scriptLocaleData(string $domain): string
{
    $locale = determine_locale();
    $directory = get_template_directory() . '/languages/front';
    $messages = [];

    foreach (glob("{$directory}/{$domain}-{$locale}-*.json") ?: [] as $file) {
        $decoded = json_decode((string) file_get_contents($file), true);
        $data = $decoded['locale_data'][$domain] ?? null;

        if (is_array($data)) {
            $messages = array_merge($messages, $data);
        }
    }

    return $messages === [] ? '' : (string) wp_json_encode([
        'domain' => $domain,
        'locale_data' => [$domain => $messages],
    ]);
}

add_action(
    'wp_enqueue_scripts',
    function () {
        if (is_admin()) {
            return;
        }

        if (!wp_script_is('wp-i18n')) {
            wp_enqueue_script('wp-i18n');
        }

        $localeData = scriptLocaleData('sage-front');

        if ($localeData !== '') {
            wp_add_inline_script(
                'wp-i18n',
                sprintf(
                    'wp.i18n.setLocaleData( %s.locale_data["sage-front"], "sage-front" );',
                    $localeData,
                ),
                'after',
            );
        }

        echo Vite::withEntryPoints(['resources/js/app.ts'])->toHtml();
    },
    100,
);

/**
 * Use the generated theme.json file.
 *
 * @return string
 */
add_filter(
    'theme_file_path',
    function ($path, $file) {
        return $file === 'theme.json'
            ? public_path('build/assets/theme.json')
            : $path;
    },
    10,
    2,
);

/**
 * Disable on-demand block asset loading.
 *
 * @link https://core.trac.wordpress.org/ticket/61965
 */
add_filter('should_load_separate_core_block_assets', '__return_false');

/**
 * Disable front-end assets that the theme does not use.
 *
 * @return void
 */
add_action(
    'wp_enqueue_scripts',
    function (): void {
        if (is_admin()) {
            return;
        }

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');

        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-blocks-style');
        wp_deregister_style('wc-blocks-style');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('core-block-supports');
        wp_dequeue_style('core-block-supports-duotone');

        if (!function_exists('is_woocommerce')) {
            return;
        }

        $isWooCommerceView
            = is_woocommerce() || is_cart() || is_checkout() || is_account_page();

        /*
         * jQuery is only kept where plugin code still depends on it:
         *
         * - the cart and checkout print the gateways' own payment fields, and
         *   PayU's list of banks ships inline jQuery plus the payu-gateway
         *   script that marks the chosen bank (the .active class app.css
         *   styles);
         * - the order-pay endpoint is WooCommerce's own form, driven by
         *   wc-checkout;
         * - the account screens are stock templates, where selectWoo and
         *   wc-country-select swap the state field per country.
         *
         * Everything else -- the product, catalogue and content pages -- runs
         * on Alpine and the Store API, so jQuery goes, along with the
         * front-end scripts that declare it as a dependency and would
         * otherwise pull it straight back in.
         *
         * The same views are the only ones that render payment fields, so
         * PayU's own assets go with them. The plugin enqueues all three on
         * every front-end request: payu-gateway's script is what pulled jQuery
         * back in, its stylesheet only dresses the bank list, and the Google
         * Pay SDK is a third-party request to pay.google.com that nothing
         * outside the checkout can use.
         */
        $needsJQuery = is_cart() || is_checkout() || is_account_page();

        if (!$needsJQuery) {
            wp_dequeue_script('jquery');
            wp_dequeue_script('jquery-core');
            wp_dequeue_script('jquery-migrate');
            wp_dequeue_script('wc-jquery-blockui');
            wp_dequeue_script('wc-js-cookie');
            wp_dequeue_script('wc-add-to-cart');
            wp_dequeue_script('wc-single-product');
            wp_dequeue_script('wc-cart-fragments');
            wp_dequeue_script('woocommerce');
            wp_dequeue_script('payu-gateway');
            wp_dequeue_script('google-pay');
            wp_dequeue_style('payu-gateway');
        }

        if (!$isWooCommerceView) {
            wp_dequeue_style('woocommerce-layout');
            wp_dequeue_style('woocommerce-smallscreen');
            wp_dequeue_style('woocommerce-general');
            wp_dequeue_style('woocommerce-inline');
            wp_dequeue_style('woocommerce-coming-soon');
        }

        if (!is_checkout()) {
            wp_dequeue_script('sourcebuster-js');
            wp_dequeue_script('wc-order-attribution');
        }

        $cartPageId = function_exists('wc_get_page_id')
            ? wc_get_page_id('cart')
            : 0;
        $checkoutPageId = function_exists('wc_get_page_id')
            ? wc_get_page_id('checkout')
            : 0;
        $customCartCheckoutPageIds = array_filter([
            $cartPageId,
            $checkoutPageId,
        ]);

        /*
         * The custom cart and checkout have no stock checkout form for
         * wc-checkout to drive, but the order-pay and order-received
         * endpoints live under the same page and do render WooCommerce's own
         * output, so they keep it -- checkout.js is what binds the payment
         * method radios and the submit handler on form#order_review.
         */
        $isCheckoutEndpoint = is_checkout_pay_page() || is_order_received_page();

        if (
            !$isCheckoutEndpoint
            && $customCartCheckoutPageIds !== []
            && is_page($customCartCheckoutPageIds)
        ) {
            wp_dequeue_script('wc-checkout');
        }
    },
    100,
);

/**
 * Drop the core block stylesheet once the page has finished rendering.
 *
 * wp_dequeue_style('wp-block-library') on wp_enqueue_scripts is not enough.
 * Core registers block-style-variation-styles with wp-block-library and
 * global-styles as its dependencies, then enqueues it while rendering any block
 * that carries a theme.json style variation -- which happens after
 * wp_enqueue_scripts has run, so the whole core stylesheet came back in as a
 * dependency on every page holding such a block.
 *
 * Only the variation's own inline CSS is wanted there. The core blocks the
 * content uses are styled by the theme (resources/css/buttons.css,
 * typography.css), which is why every page without a style variation -- posts
 * with images, lists and quotes included -- has always rendered without it.
 *
 * @return void
 */
add_action(
    'wp_print_styles',
    function (): void {
        if (is_admin()) {
            return;
        }

        $variationStyles = wp_styles()->query('block-style-variation-styles');

        if ($variationStyles instanceof _WP_Dependency) {
            $variationStyles->deps = array_values(
                array_diff($variationStyles->deps, ['wp-block-library']),
            );
        }

        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
    },
    1,
);

/**
 * Prevent WooCommerce from enqueueing its default stylesheet bundle.
 *
 * @return array<string, mixed>
 */
add_filter(
    'woocommerce_enqueue_styles',
    function ($styles) {
        return [];
    },
    100,
);

/**
 * Disable front-end generated image auto sizes to avoid extra inline output.
 */
add_filter('wp_img_tag_add_auto_sizes', '__return_false');

/**
 * Register the initial theme setup.
 *
 * @return void
 */
add_action(
    'after_setup_theme',
    function () {
        /**
         * Disable full-site editing support.
         *
         * @link https://wptavern.com/gutenberg-10-5-embeds-pdfs-adds-verse-block-color-options-and-introduces-new-patterns
         */
        remove_theme_support('block-templates');

        /**
         * Register the navigation menus.
         *
         * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
         */
        register_nav_menus([
            'primary_navigation' => __('Primary Navigation', 'sage-back'),
            'top_bar_navigation' => __('Top Bar Navigation', 'sage-back'),
            'footer_navigation' => __('Footer Navigation', 'sage-back'),
            'footer_secondary_navigation' => __(
                'Footer Secondary Navigation',
                'sage-back',
            ),
            'social_navigation' => __('Social Navigation', 'sage-back'),
        ]);

        /**
         * Disable the default block patterns.
         *
         * @link https://developer.wordpress.org/block-editor/developers/themes/theme-support/#disabling-the-default-block-patterns
         */
        remove_theme_support('core-block-patterns');

        /**
         * Enable plugins to manage the document title.
         *
         * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
         */
        add_theme_support('title-tag');

        /**
         * Enable post thumbnail support.
         *
         * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
         */
        add_theme_support('post-thumbnails');

        /**
         * Enable responsive embed support.
         *
         * @link https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#responsive-embedded-content
         */
        add_theme_support('responsive-embeds');

        /**
         * Enable HTML5 markup support.
         *
         * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
         */
        add_theme_support('html5', [
            'caption',
            'comment-form',
            'comment-list',
            'gallery',
            'search-form',
            'script',
            'style',
        ]);

        /**
         * Enable selective refresh for widgets in customizer.
         *
         * @link https://developer.wordpress.org/reference/functions/add_theme_support/#customize-selective-refresh-widgets
         */
        add_theme_support('customize-selective-refresh-widgets');

        /**
         * Load the theme's translated strings.
         *
         * @link https://developer.wordpress.org/reference/functions/load_theme_textdomain/
         */
        load_theme_textdomain(
            'sage-front',
            get_template_directory() . '/languages/front',
        );

        /**
         * The admin-facing strings use their own domain, which nothing ever
         * loaded -- so all 60-odd of them (menu locations, sidebar names,
         * customizer labels, both settings pages) were untranslatable.
         */
        load_theme_textdomain(
            'sage-back',
            get_template_directory() . '/languages/back',
        );
    },
    20,
);

/**
 * Register the theme sidebars.
 *
 * @return void
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];

    register_sidebar(
        [
            'name' => __('Primary', 'sage-back'),
            'id' => 'sidebar-primary',
        ] + $config,
    );

    register_sidebar(
        [
            'name' => __('Footer', 'sage-back'),
            'id' => 'sidebar-footer',
        ] + $config,
    );
});

add_action('customize_register', function (WP_Customize_Manager $wp_customize) {
    // Both settings hold an uploaded image URL, so sanitize as a URL rather
    // than letting the raw value through.
    $wp_customize->add_setting('logo_dark', [
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_setting('logo_light', [
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_setting('logo_light_lg', [
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_setting('logo_dark_lg', [
        'sanitize_callback' => 'esc_url_raw',
    ]);

    $wp_customize->add_control(
        new WP_Customize_Image_Control($wp_customize, 'logo_dark', [
            'label' => __('Logo Dark', 'sage-back'),
            'section' => 'title_tagline',
            'settings' => 'logo_dark',
        ]),
    );

    $wp_customize->add_control(
        new WP_Customize_Image_Control($wp_customize, 'logo_dark_lg', [
            'label' => __('Logo Dark Large', 'sage-back'),
            'section' => 'title_tagline',
            'settings' => 'logo_dark_lg',
        ]),
    );

    $wp_customize->add_control(
        new WP_Customize_Image_Control($wp_customize, 'logo_light', [
            'label' => __('Logo Light', 'sage-back'),
            'section' => 'title_tagline',
            'settings' => 'logo_light',
        ]),
    );

    $wp_customize->add_control(
        new WP_Customize_Image_Control($wp_customize, 'logo_light_lg', [
            'label' => __('Logo Light Large', 'sage-back'),
            'section' => 'title_tagline',
            'settings' => 'logo_light_lg',
        ]),
    );
});
