<?php

declare(strict_types=1);

namespace App\Shop;

/**
 * Points "return to shop" at the last catalogue page the visitor saw
 * (production snippet #5), instead of always the shop root.
 */
final class CartReturn
{
    public const SESSION_KEY = 'last_shop_page';

    public static function boot(): void
    {
        add_action('wp', [self::class, 'remember'], 20);
        add_filter('woocommerce_return_to_shop_redirect', [self::class, 'redirect']);
    }

    public static function remember(): void
    {
        if (is_admin() || wp_doing_ajax() || ! self::session()) {
            return;
        }

        if (self::isExcludedPage()) {
            return;
        }

        $url = function_exists('wc_get_current_url')
            ? wc_get_current_url()
            : home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? ''));

        $url = remove_query_arg(
            ['add-to-cart', 'wc-ajax', 'key', 'order-pay', 'order-received', 'utm_source', 'utm_medium', 'utm_campaign'],
            $url,
        );

        // Never store an off-site URL, however it got here.
        if (wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST)) {
            return;
        }

        self::session()->set(self::SESSION_KEY, esc_url_raw($url));
    }

    public static function redirect(mixed $url): mixed
    {
        $session = self::session();

        if (! $session) {
            return $url;
        }

        $saved = (string) $session->get(self::SESSION_KEY);

        if ($saved === '' || self::pointsAtExcludedPage($saved)) {
            return $url;
        }

        return $saved;
    }

    protected static function isExcludedPage(): bool
    {
        return (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page());
    }

    protected static function pointsAtExcludedPage(string $url): bool
    {
        if (! function_exists('wc_get_page_permalink')) {
            return false;
        }

        foreach (['cart', 'checkout', 'myaccount'] as $page) {
            $permalink = (string) wc_get_page_permalink($page);

            if ($permalink !== '' && str_starts_with($url, $permalink)) {
                return true;
            }
        }

        return false;
    }

    protected static function session(): mixed
    {
        return function_exists('WC') && WC()->session ? WC()->session : null;
    }
}
