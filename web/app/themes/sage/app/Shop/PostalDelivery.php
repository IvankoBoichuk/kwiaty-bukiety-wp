<?php

declare(strict_types=1);

namespace App\Shop;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Delivery rules for products shipped by post (production snippet #77).
 *
 * Orders placed before 15:00 Warsaw time can go out tomorrow, later ones the
 * day after, and Sundays are never a delivery day.
 *
 * The original read the flag with ACF's get_field(); ACF is not part of this
 * stack, but the data survived as plain post meta on 77 products, so the raw
 * key is read directly. The snippet's second half re-configured a jQuery UI
 * datepicker that this theme does not use -- the calendar here comes from
 * App\Support\DeliveryTimer -- so only the server-side rule is migrated.
 */
final class PostalDelivery
{
    public const META_KEY = 'is_postal_delivery';

    public const CUTOFF_HOUR = 15;

    public const TIMEZONE = 'Europe/Warsaw';

    public static function boot(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validate'], 20, 3);
    }

    public static function isPostalProduct(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $value = get_post_meta($productId, self::META_KEY, true);

        return ! in_array($value, ['', '0', 0, false, null], true);
    }

    /**
     * Days of notice required right now: 1 before the cutoff, 2 after it.
     */
    public static function minimumDaysAhead(): int
    {
        return (int) self::now()->format('G') < self::CUTOFF_HOUR ? 1 : 2;
    }

    public static function validate(mixed $passed, mixed $productId, mixed $quantity): mixed
    {
        unset($quantity);

        if (! self::isPostalProduct((int) $productId)) {
            return $passed;
        }

        $date = isset($_REQUEST['delivery_date'])
            ? sanitize_text_field(wp_unslash($_REQUEST['delivery_date']))
            : '';

        if ($date === '') {
            wc_add_notice(__('Proszę wybrać datę dostawy.', 'sage-front'), 'error');

            return false;
        }

        $selected = DateTimeImmutable::createFromFormat('!Y-m-d', $date, self::timezone());

        if (! $selected) {
            wc_add_notice(__('Nie udało się odczytać wybranej daty dostawy.', 'sage-front'), 'error');

            return false;
        }

        if ((int) $selected->format('N') === 7) {
            wc_add_notice(__('Nie realizujemy dostaw w niedziele.', 'sage-front'), 'error');

            return false;
        }

        $daysAhead = self::minimumDaysAhead();
        $earliest = self::now()->setTime(0, 0)->modify("+{$daysAhead} days");

        if ($selected < $earliest) {
            wc_add_notice(
                $daysAhead === 1
                    ? __('Najwcześniejszy możliwy termin dostawy to jutro.', 'sage-front')
                    : __('Dla zamówień złożonych po 15:00 czasu polskiego, najbliższy termin to pojutrze.', 'sage-front'),
                'error',
            );

            return false;
        }

        return $passed;
    }

    protected static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    protected static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }
}
