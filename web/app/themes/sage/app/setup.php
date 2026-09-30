<?php

/**
 * Theme setup.
 */

namespace App;

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

add_filter(
    'wp_enqueue_scripts',
    function () {
        if (is_admin()) {
            return;
        }

        $dependencies = ['wp-i18n'];
        foreach ($dependencies as $dependency) {
            if (!wp_script_is($dependency)) {
                wp_enqueue_script($dependency);
            }
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

        if (!$isWooCommerceView) {
            wp_dequeue_script('jquery');
            wp_dequeue_script('jquery-core');
            wp_dequeue_script('jquery-migrate');
            wp_dequeue_script('wc-jquery-blockui');
            wp_dequeue_script('wc-add-to-cart');
            wp_dequeue_script('wc-js-cookie');
            wp_dequeue_script('woocommerce');
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

        if (
            $customCartCheckoutPageIds !== []
            && is_page($customCartCheckoutPageIds)
        ) {
            wp_dequeue_script('wc-checkout');
        }
    },
    100,
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
    $wp_customize->add_setting('logo_dark');
    $wp_customize->add_setting('logo_light');

    $wp_customize->add_control(
        new WP_Customize_Image_Control($wp_customize, 'logo_dark', [
            'label' => __('Logo Dark', 'sage-back'),
            'section' => 'title_tagline',
            'settings' => 'logo_dark',
        ]),
    );

    $wp_customize->add_control(
        new WP_Customize_Image_Control($wp_customize, 'logo_light', [
            'label' => __('Logo Light', 'sage-back'),
            'section' => 'title_tagline',
            'settings' => 'logo_light',
        ]),
    );
});
