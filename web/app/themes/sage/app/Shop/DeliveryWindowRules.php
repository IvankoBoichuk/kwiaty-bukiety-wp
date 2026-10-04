<?php

declare(strict_types=1);

namespace App\Shop;

use App\Support\DeliveryTimer;

/**
 * Checks the submitted delivery slot against the schedule as it stands now.
 *
 * The product page carries its whole calendar in the HTML -- the date presets,
 * the per-date slot map and the calendar's upper bound all come from
 * DeliveryTimer::purchaseOptions() at render time -- and that HTML is page
 * cached for a week. A copy served from the cache therefore offers "Dziś" with
 * the date it was generated on and the slots that were still open at that
 * minute, and nothing on the way back in used to disagree: the Store API took
 * a yesterday's date, or this morning's 08-12 slot at a quarter to four in the
 * afternoon, and returned 201.
 *
 * The front end now refetches the schedule (see App\Api\DeliverySchedule), so
 * a cached page corrects itself; this is the half that does not depend on the
 * client getting it right.
 *
 * Only what the request actually sends is judged. Whether a date is required
 * at all stays with the rules that already answer that question for their own
 * products -- LeadTimeRules for the funeral categories, PostalDelivery for the
 * posted ones -- so programmatic add_to_cart() calls keep working.
 */
final class DeliveryWindowRules
{
    public static function boot(): void
    {
        // Ahead of LeadTimeRules (10) and PostalDelivery (20): a stale date is
        // worth saying so about before the lead time of that same date is.
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validate'], 5, 6);
    }

    public static function validate(
        mixed $passed,
        mixed $productId,
        mixed $quantity,
        mixed $variationId = 0,
        mixed $variations = [],
        mixed $cartItemData = [],
    ): mixed {
        unset($productId, $quantity, $variationId, $variations);

        if ($passed === false || ! empty($cartItemData['is_sage_addition'])) {
            return $passed;
        }

        $payload = PurchaseRequest::payload();
        $date = $payload['delivery_date'];

        if ($date === '') {
            return $passed;
        }

        $timeOptionsByDate = self::timeOptionsByDate();

        if (! self::isDateAvailable($date, $timeOptionsByDate)) {
            wc_add_notice(self::staleScheduleNotice(), 'error');

            return false;
        }

        $time = $payload['delivery_time'];

        if ($time !== '' && ! self::matchesSlot($time, $timeOptionsByDate[$date])) {
            wc_add_notice(self::staleScheduleNotice(), 'error');

            return false;
        }

        return $passed;
    }

    /**
     * A date is deliverable only while the schedule still lists slots for it,
     * which rules out past dates, holidays and a today whose last slot has
     * already started.
     *
     * @param  array<string, array<int, array{value: string, label: string, start: int, end: int}>>  $timeOptionsByDate
     */
    public static function isDateAvailable(string $date, array $timeOptionsByDate): bool
    {
        return ($timeOptionsByDate[$date] ?? []) !== [];
    }

    /**
     * The two front ends spell the time differently: the preset buttons post
     * the slot itself (`08-12`), the funeral form's native time input posts a
     * wall clock time (`08:30`) that has to land inside one.
     *
     * @param  array<int, array{value: string, label: string, start: int, end: int}>  $slots
     */
    public static function matchesSlot(string $time, array $slots): bool
    {
        $time = trim($time);

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})$/', $time, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            foreach ($slots as $slot) {
                if ($slot['start'] === $start && $slot['end'] === $end) {
                    return true;
                }
            }

            return false;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches) !== 1) {
            return false;
        }

        $minutes = ((int) $matches[1] * 60) + (int) $matches[2];

        foreach ($slots as $slot) {
            if ($minutes >= $slot['start'] * 60 && $minutes < $slot['end'] * 60) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, array{value: string, label: string, start: int, end: int}>>
     */
    protected static function timeOptionsByDate(): array
    {
        return (array) (app(DeliveryTimer::class)->purchaseOptions()['timeOptionsByDate'] ?? []);
    }

    protected static function staleScheduleNotice(): string
    {
        return __(
            'The selected delivery time is no longer available. Please refresh the page and choose a new time.',
            'sage-front',
        );
    }
}
