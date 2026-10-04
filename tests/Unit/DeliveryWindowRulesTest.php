<?php

declare(strict_types=1);

use App\Shop\DeliveryWindowRules;

/**
 * The product page is page cached for a week, so the date and slot it posts can
 * be days old. These two checks are what stands between a cached calendar and
 * an order for yesterday.
 */
function kbSlots(int ...$hours): array
{
    $slots = [];

    foreach (array_chunk($hours, 2) as [$start, $end]) {
        $slots[] = [
            'value' => sprintf('%02d-%02d', $start, $end),
            'label' => sprintf('%02d-%02d', $start, $end),
            'start' => $start,
            'end' => $end,
        ];
    }

    return $slots;
}

it('accepts a date the schedule still has slots for', function () {
    $schedule = ['2026-10-06' => kbSlots(8, 12, 12, 15)];

    expect(DeliveryWindowRules::isDateAvailable('2026-10-06', $schedule))->toBeTrue();
});

it('rejects a date the schedule no longer lists', function () {
    $schedule = ['2026-10-06' => kbSlots(8, 12)];

    // Yesterday, a holiday and a day past the 60-day window all look the same
    // from here: the map simply has no key for them.
    expect(DeliveryWindowRules::isDateAvailable('2026-10-03', $schedule))->toBeFalse();
});

it('rejects a date whose slots have all run out', function () {
    expect(DeliveryWindowRules::isDateAvailable('2026-10-04', ['2026-10-04' => []]))->toBeFalse();
});

it('accepts a slot the date still offers', function () {
    expect(DeliveryWindowRules::matchesSlot('12-15', kbSlots(12, 15, 15, 18)))->toBeTrue();
});

it('accepts an unpadded slot', function () {
    expect(DeliveryWindowRules::matchesSlot('8-12', kbSlots(8, 12)))->toBeTrue();
});

it('rejects a slot that has already started', function () {
    // 15:44 in Warsaw: the schedule has dropped 08-12, the cached page has not.
    expect(DeliveryWindowRules::matchesSlot('08-12', kbSlots(15, 18, 18, 21)))->toBeFalse();
});

it('accepts a funeral time inside an available slot', function () {
    expect(DeliveryWindowRules::matchesSlot('08:30', kbSlots(8, 12)))->toBeTrue();
});

it('treats the end of a slot as belonging to the next one', function () {
    expect(DeliveryWindowRules::matchesSlot('12:00', kbSlots(8, 12)))->toBeFalse();
    expect(DeliveryWindowRules::matchesSlot('12:00', kbSlots(12, 15)))->toBeTrue();
});

it('rejects a funeral time outside every available slot', function () {
    expect(DeliveryWindowRules::matchesSlot('07:30', kbSlots(8, 12)))->toBeFalse();
    expect(DeliveryWindowRules::matchesSlot('21:00', kbSlots(18, 21)))->toBeFalse();
});

it('rejects a time it cannot read', function () {
    expect(DeliveryWindowRules::matchesSlot('', kbSlots(8, 12)))->toBeFalse();
    expect(DeliveryWindowRules::matchesSlot('kiedykolwiek', kbSlots(8, 12)))->toBeFalse();
});
