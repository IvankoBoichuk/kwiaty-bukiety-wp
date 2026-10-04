<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

class DeliveryTimer
{
    public const OPTION_NAME = 'sage_delivery_timer_settings';
    public const ORDER_LEAD_HOURS = 2;
    public const PURCHASE_DAYS_AHEAD = 60;

    /**
     * @var array<string, string>|null
     */
    protected ?array $settings = null;

    public static function boot(): void
    {
        add_shortcode('delivery_timer', [self::class, 'renderShortcode']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderShortcode(array $attributes = []): string
    {
        unset($attributes);

        return view('partials.delivery-timer', [
            'deliveryTimer' => app(self::class)->viewData(),
        ])->render();
    }

    /**
     * @return array<string, string>
     */
    public static function defaultOptions(): array
    {
        return [
            'weekday_hours' => '9-17',
            'weekend_hours' => '9-14',
            'time_slots' => '08-12, 12-15, 15-18, 18-21',
            'holidays' => '',
        ];
    }

    /**
     * @param mixed $input
     * @return array<string, string>
     */
    public function sanitizeOptions(mixed $input): array
    {
        $input = is_array($input) ? $input : [];

        return [
            'weekday_hours' => $this->sanitizeHourRange(
                (string) ($input['weekday_hours']
                    ?? self::defaultOptions()['weekday_hours']),
                self::defaultOptions()['weekday_hours'],
            ),
            'weekend_hours' => $this->sanitizeHourRange(
                (string) ($input['weekend_hours']
                    ?? self::defaultOptions()['weekend_hours']),
                self::defaultOptions()['weekend_hours'],
            ),
            'time_slots' => $this->sanitizeTimeSlots(
                (string) ($input['time_slots']
                    ?? self::defaultOptions()['time_slots']),
                self::defaultOptions()['time_slots'],
            ),
            'holidays' => implode(
                ', ',
                $this->normalizeHolidayList($input['holidays'] ?? ''),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function settings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        return $this->settings ??= array_replace(
            self::defaultOptions(),
            $this->sanitizeOptions($stored),
        );
    }

    /**
     * @return array{
     *     timezone: string,
     *     holidays: array<int, string>,
     *     leadTimeHours: int,
     *     timeSlots: array<int, array{value: string, label: string, start: int, end: int}>,
     *     strings: array<string, string>
     * }
     */
    public function viewData(): array
    {
        $settings = $this->settings();
        $timeSlots = $this->normalizeTimeSlotList($settings['time_slots']);

        return [
            'timezone' => $this->timezone(),
            'holidays' => $this->normalizeHolidayList($settings['holidays']),
            'leadTimeHours' => self::ORDER_LEAD_HOURS,
            'timeSlots' => $timeSlots,
            'strings' => $this->strings(),
        ];
    }

    /**
     * The copy the countdown shows once today's last slot has passed.
     *
     * It travels in the config rather than through @wordpress/i18n, the same
     * way the cart and checkout copy does (see resources/js/modules/strings.ts).
     * The hour keeps its own placeholder so the markup around it stays in the
     * script and out of the catalogue.
     *
     * @return array<string, string>
     */
    protected function strings(): array
    {
        return [
            /* translators: %s: opening hour of the next delivery slot. */
            'nextDeliveryTomorrow' => __(
                'Next delivery tomorrow from %s',
                'sage-front',
            ),
            /* translators: %1$s: delivery date. %2$s: opening hour of the next delivery slot. */
            'nextDeliveryOnDate' => __(
                'Next delivery %1$s from %2$s',
                'sage-front',
            ),
        ];
    }

    /**
     * @return array{
     *     timezone: string,
     *     holidays: array<int, string>,
     *     leadTimeHours: int,
     *     dateOptions: array<int, array{value: string, label: string}>,
     *     timeSlots: array<int, array{value: string, label: string, start: int, end: int}>,
     *     timeOptions: array<int, array{value: string, label: string, start: int, end: int}>,
     *     timeOptionsByDate: array<string, array<int, array{value: string, label: string, start: int, end: int}>>
     * }
     */
    public function purchaseOptions(): array
    {
        $viewData = $this->viewData();
        $timeSlots = $viewData['timeSlots'];
        $availableTimeOptionsByDate = $this->buildAvailableTimeOptionsByDate(
            $viewData,
            $timeSlots,
        );
        $dateOptions = $this->buildDateOptions(
            array_keys($availableTimeOptionsByDate),
            new DateTimeImmutable(
                'now',
                new DateTimeZone($viewData['timezone']),
            ),
            new DateTimeZone($viewData['timezone']),
        );

        return [
            ...$viewData,
            'dateOptions' => $dateOptions,
            'timeOptions' => $timeSlots,
            'timeOptionsByDate' => $availableTimeOptionsByDate,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function workingHoursDetails(): array
    {
        $settings = $this->settings();

        return [
            sprintf(
                /* translators: %s is an hour range, e.g. 9:00 - 18:00 */
                __('Mon-Fri: %s', 'sage-front'),
                $this->formatHourRange($settings['weekday_hours']),
            ),
            sprintf(
                /* translators: %s is an hour range, e.g. 10:00 - 16:00 */
                __('Sat-Sun: %s', 'sage-front'),
                $this->formatHourRange($settings['weekend_hours']),
            ),
        ];
    }

    protected function timezone(): string
    {
        $timezone = get_option('timezone_string');

        return is_string($timezone) && $timezone !== ''
            ? $timezone
            : 'Europe/Warsaw';
    }

    /**
     * @param array<int, string> $availableDates
     * @return array<int, array{value: string, label: string}>
     */
    protected function buildDateOptions(
        array $availableDates,
        DateTimeImmutable $today,
        DateTimeZone $timezone,
    ): array {
        $options = [];

        foreach (array_slice($availableDates, 0, 2) as $availableDate) {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $availableDate,
                $timezone,
            );

            if ($date === false) {
                continue;
            }

            $options[] = [
                'value' => $availableDate,
                'label' => $this->dateOptionLabel($date, $today, $timezone),
            ];
        }

        return $options;
    }

    /**
     * @param array{timezone: string, holidays: array<int, string>, leadTimeHours: int, timeSlots: array<int, array{value: string, label: string, start: int, end: int}>} $config
     * @param array<int, array{value: string, label: string, start: int, end: int}> $timeSlots
     * @return array<string, array<int, array{value: string, label: string, start: int, end: int}>>
     */
    protected function buildAvailableTimeOptionsByDate(
        array $config,
        array $timeSlots,
    ): array {
        $timezone = new DateTimeZone($config['timezone']);
        $today = new DateTimeImmutable('now', $timezone);
        $candidate = $today->setTime(0, 0, 0);
        $availableTimeOptionsByDate = [];

        for ($index = 0; $index < self::PURCHASE_DAYS_AHEAD; $index++) {
            $availableSlots = $this->availableTimeSlotsForDate(
                $candidate,
                $today,
                $config,
                $timeSlots,
            );

            if ($availableSlots !== []) {
                $availableTimeOptionsByDate[
                    $candidate->format('Y-m-d')
                ] = $availableSlots;
            }

            $candidate = $candidate->modify('+1 day');
        }

        return $availableTimeOptionsByDate;
    }

    /**
     * @param array{timezone: string, holidays: array<int, string>, leadTimeHours: int, timeSlots: array<int, array{value: string, label: string, start: int, end: int}>} $config
     * @param array<int, array{value: string, label: string, start: int, end: int}> $timeSlots
     * @return array<int, array{value: string, label: string, start: int, end: int}>
     */
    protected function availableTimeSlotsForDate(
        DateTimeImmutable $candidate,
        DateTimeImmutable $today,
        array $config,
        array $timeSlots,
    ): array {
        $candidateKey = $candidate->format('Y-m-d');

        if (in_array($candidate->format('Y-m-d'), $config['holidays'], true)) {
            return [];
        }

        if ($candidateKey !== $today->format('Y-m-d')) {
            return $timeSlots;
        }

        $minimumTime = $today->modify('+' . self::ORDER_LEAD_HOURS . ' hours');

        if ($minimumTime->format('Y-m-d') !== $candidateKey) {
            return [];
        }

        $minimumMinutes
            = (int) $minimumTime->format('G') * 60
            + (int) $minimumTime->format('i');

        return array_values(
            array_filter(
                $timeSlots,
                static fn(array $timeSlot): bool => $minimumMinutes
                    <= $timeSlot['start'] * 60,
            ),
        );
    }

    protected function dateOptionLabel(
        DateTimeImmutable $date,
        DateTimeImmutable $today,
        DateTimeZone $timezone,
    ): string {
        $daysDiff = (int) $today->setTime(0, 0, 0)->diff($date)->format('%a');

        if ($daysDiff === 0) {
            return __('Today', 'sage-front');
        }

        if ($daysDiff === 1) {
            return __('Tomorrow', 'sage-front');
        }

        return wp_date('d.m', $date->getTimestamp(), $timezone);
    }

    protected function sanitizeHourRange(
        string $value,
        string $fallback,
    ): string {
        $parsed = $this->parseHourRange($value);

        if ($parsed === null) {
            return $fallback;
        }

        return sprintf('%d-%d', $parsed['start'], $parsed['end']);
    }

    protected function sanitizeTimeSlots(
        string $value,
        string $fallback,
    ): string {
        $slots = $this->normalizeTimeSlotList($value);

        if ($slots === []) {
            return $fallback;
        }

        return implode(', ', array_column($slots, 'value'));
    }

    /**
     * @return array{start: int, end: int}|null
     */
    protected function parseHourRange(string $value): ?array
    {
        if (
            !preg_match('/^\s*(\d{1,2})\s*-\s*(\d{1,2})\s*$/', $value, $matches)
        ) {
            return null;
        }

        $start = (int) $matches[1];
        $end = (int) $matches[2];

        if (
            $start < 0
            || $start > 23
            || $end < 1
            || $end > 24
            || $start >= $end
        ) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    protected function formatHourRange(string $value): string
    {
        $hours = $this->parseHourRange($value);

        if ($hours === null) {
            return $value;
        }

        return sprintf('%02d:00-%02d:00', $hours['start'], $hours['end']);
    }

    /**
     * @return array<int, array{value: string, label: string, start: int, end: int}>
     */
    protected function normalizeTimeSlotList(string $value): array
    {
        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        $timeSlots = [];

        foreach ($parts as $part) {
            $slot = $this->parseHourRange(trim($part));

            if ($slot === null) {
                continue;
            }

            $formattedValue = sprintf(
                '%02d-%02d',
                $slot['start'],
                $slot['end'],
            );

            if (isset($timeSlots[$formattedValue])) {
                continue;
            }

            $timeSlots[$formattedValue] = [
                'value' => $formattedValue,
                'label' => $formattedValue,
                'start' => $slot['start'],
                'end' => $slot['end'],
            ];
        }

        return array_values($timeSlots);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    protected function normalizeHolidayList(mixed $value): array
    {
        $value = is_array($value) ? implode(',', $value) : (string) $value;
        $parts = preg_split('/[\s,]+/', $value) ?: [];
        $holidays = [];

        foreach ($parts as $part) {
            $date = trim($part);

            if ($date === '') {
                continue;
            }

            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                continue;
            }

            $holidays[] = $date;
        }

        $holidays = array_values(array_unique($holidays));
        sort($holidays);

        return $holidays;
    }
}
