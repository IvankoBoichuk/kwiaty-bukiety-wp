<?php

declare(strict_types=1);

namespace App\Shop;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Minimum lead time for the funeral categories (production snippets #65, #66
 * and #67): wreaths need 24 hours, funeral bouquets 12.
 *
 * Rewritten rather than copied. The originals read $_REQUEST['datadostav'],
 * a field name from the Storefront checkout; this theme posts `delivery_date`
 * (Y-m-d) and `delivery_time` (HH-HH) from the add-to-cart form -- see
 * app/filters.php, woocommerce_before_add_to_cart_button -- or sends them as
 * camelCase JSON through the Store API, which is why the request is read
 * through PurchaseRequest rather than from $_REQUEST: that superglobal is
 * empty on every Store API call, so this rule used to reject every date.
 *
 * Behaviour change worth knowing: snippet #66 raised an error notice and then
 * returned the unchanged $passed, so the wreath still went into the cart. Here
 * the rule actually blocks, which is what the snippet's name promised.
 */
final class LeadTimeRules
{
    /**
     * Category slug => required hours of notice.
     *
     * @var array<string, int>
     */
    public const RULES = [
        'wieniec-pogrzebowa' => 24,
        'wiazanka-na-pogrzeb' => 12,
    ];

    public const TIMEZONE = 'Europe/Warsaw';

    public static function boot(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validate'], 10, 3);
    }

    /**
     * The hours of notice a product needs, or null when no rule applies.
     */
    public static function hoursFor(int $productId): ?int
    {
        foreach (self::rules() as $slug => $hours) {
            if (has_term($slug, 'product_cat', $productId)) {
                return $hours;
            }
        }

        return null;
    }

    /**
     * The storefront notice for a product, for the single-product template.
     */
    public static function noticeFor(int $productId): string
    {
        $hours = self::hoursFor($productId);

        if ($hours === null) {
            return '';
        }

        return sprintf(
            /* translators: %d: hours of notice required before delivery. */
            __('Zamówienia z tej kategorii realizujemy wyłącznie z minimum %d-godzinnym wyprzedzeniem.', 'sage-front'),
            $hours,
        );
    }

    public static function validate(mixed $passed, mixed $productId, mixed $quantity): mixed
    {
        unset($quantity);

        $hours = self::hoursFor((int) $productId);

        if ($hours === null) {
            return $passed;
        }

        $payload = PurchaseRequest::payload();
        $date = $payload['delivery_date'];
        $time = $payload['delivery_time'];

        if ($date === '' || $time === '') {
            wc_add_notice(
                __('Dla tego produktu należy wybrać datę i godzinę dostawy.', 'sage-front'),
                'error',
            );

            return false;
        }

        $deliversAt = self::deliveryStart($date, $time);

        if ($deliversAt === null) {
            wc_add_notice(
                __('Nie udało się odczytać wybranego terminu dostawy.', 'sage-front'),
                'error',
            );

            return false;
        }

        $earliest = self::now()->modify("+{$hours} hours");

        if ($deliversAt < $earliest) {
            wc_add_notice(
                sprintf(
                    /* translators: %d: hours of notice required before delivery. */
                    __('Uwaga: zamówienia z tej kategorii realizujemy wyłącznie z minimum %d-godzinnym wyprzedzeniem.', 'sage-front'),
                    $hours,
                ),
                'error',
            );

            return false;
        }

        return $passed;
    }

    /**
     * `2026-10-05` + `08-12` => the start of that delivery window.
     */
    protected static function deliveryStart(string $date, string $time): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $startHour = (int) explode('-', $time)[0];

        if ($startHour < 0 || $startHour > 23) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %02d:00', $date, $startHour),
            self::timezone(),
        );

        return $parsed ?: null;
    }

    protected static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    protected static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    /**
     * @return array<string, int>
     */
    protected static function rules(): array
    {
        /**
         * Lets a category rule be added or retimed without touching the class.
         *
         * @param  array<string, int>  $rules  Category slug => hours of notice.
         */
        return (array) apply_filters('sage/shop/lead_time_rules', self::RULES);
    }
}
