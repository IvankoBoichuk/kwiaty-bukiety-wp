import { createTranslator, type Strings } from './strings';

type TimeSlot = {
    value: string;
    label: string;
    start: number;
    end: number;
};

type DeliveryTimerConfig = {
    timezone: string;
    holidays: string[];
    leadTimeHours: number;
    timeSlots: TimeSlot[];
    strings?: Strings;
};

type TimerState =
    | {
        type: 'countdown';
        value: string;
    }
    | {
        type: 'message';
        value: string;
    };

type ZonedNow = {
    date: Date;
    dateKey: string;
};

const SELECTOR = '[data-delivery-timer]';

const pad = (value: number): string => String(value).padStart(2, '0');

const getZonedNow = (timeZone: string): ZonedNow => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(new Date());

    const values = parts.reduce<Record<string, string>>((carry, part) => {
        if (part.type !== 'literal') {
            carry[part.type] = part.value;
        }

        return carry;
    }, {});

    const year = Number(values.year);
    const month = Number(values.month);
    const day = Number(values.day);
    const hour = Number(values.hour);
    const minute = Number(values.minute);
    const second = Number(values.second);
    const date = new Date(year, month - 1, day, hour, minute, second);

    return {
        date,
        dateKey: `${values.year}-${values.month}-${values.day}`,
    };
};

const isHoliday = (dateKey: string, holidays: Set<string>): boolean => {
    return holidays.has(dateKey);
};

const formatHour = (hour: number): string => `${pad(hour)}:00`;

const formatDate = (date: Date): string => {
    return new Intl.DateTimeFormat('pl-PL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const formatDuration = (milliseconds: number): string => {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
};

const nextWorkingDate = (
    currentDate: Date,
    holidays: Set<string>,
): Date => {
    const candidate = new Date(currentDate);
    candidate.setHours(0, 0, 0, 0);

    do {
        candidate.setDate(candidate.getDate() + 1);
    } while (isHoliday(toDateKey(candidate), holidays));

    return candidate;
};

const toDateKey = (date: Date): string => {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const slotStartDate = (date: Date, slot: TimeSlot): Date => {
    const start = new Date(date);
    start.setHours(slot.start, 0, 0, 0);

    return start;
};

const todayCutoffTime = (config: DeliveryTimerConfig, now: Date): Date | null => {
    if (config.timeSlots.length === 0) {
        return null;
    }

    const leadMs = config.leadTimeHours * 60 * 60 * 1000;
    const nowWithLead = new Date(now.getTime() + leadMs);
    let latestAvailableSlotStartMs: number | null = null;

    config.timeSlots.forEach((slot) => {
        const start = slotStartDate(now, slot);

        if (nowWithLead <= start) {
            latestAvailableSlotStartMs = start.getTime();
        }
    });

    if (latestAvailableSlotStartMs === null) {
        return null;
    }

    return new Date(latestAvailableSlotStartMs - leadMs);
};

const firstSlotStart = (config: DeliveryTimerConfig, date: Date): Date | null => {
    const firstSlot = config.timeSlots[0];

    if (!firstSlot) {
        return null;
    }

    return slotStartDate(date, firstSlot);
};

const nextDeliveryMessage = (
    config: DeliveryTimerConfig,
    now: ZonedNow,
    holidays: Set<string>,
): string => {
    const t = createTranslator(config.strings);
    const nextDate = nextWorkingDate(now.date, holidays);
    const nextSlotStart = firstSlotStart(config, nextDate);
    const tomorrow = new Date(now.date);

    tomorrow.setHours(0, 0, 0, 0);
    tomorrow.setDate(tomorrow.getDate() + 1);

    // The hour is the only part of the message that carries markup, so it is
    // wrapped here rather than in the catalogue.
    const formattedStart = `<span>${formatHour(nextSlotStart?.getHours() ?? 0)}</span>`;

    if (toDateKey(nextDate) === toDateKey(tomorrow)) {
        return t('nextDeliveryTomorrow', 'Next delivery tomorrow from %s')
            .replace('%s', formattedStart);
    }

    return t('nextDeliveryOnDate', 'Next delivery %1$s from %2$s')
        .replace('%1$s', formatDate(nextDate))
        .replace('%2$s', formattedStart);
};

const resolveState = (
    config: DeliveryTimerConfig,
    holidays: Set<string>,
): TimerState => {
    const now = getZonedNow(config.timezone);

    if (isHoliday(now.dateKey, holidays)) {
        return {
            type: 'message',
            value: nextDeliveryMessage(config, now, holidays),
        };
    }

    const cutoffTime = todayCutoffTime(config, now.date);

    if (!cutoffTime || now.date > cutoffTime) {
        return {
            type: 'message',
            value: nextDeliveryMessage(config, now, holidays),
        };
    }

    return {
        type: 'countdown',
        value: formatDuration(cutoffTime.getTime() - now.date.getTime()),
    };
};

const initDeliveryTimer = (element: HTMLElement): void => {
    const rawConfig = element.dataset.deliveryTimer;

    if (!rawConfig) {
        return;
    }

    const config = JSON.parse(rawConfig) as DeliveryTimerConfig;
    const holidays = new Set(config.holidays);
    const prompt = element.querySelector<HTMLElement>('.delivery-timer__prompt');
    const time = element.querySelector<HTMLElement>('.delivery-timer__time');
    const next = element.querySelector<HTMLElement>('.delivery-timer__next');

    if (!prompt || !time || !next) {
        return;
    }

    let intervalId: number | null = null;

    const stopInterval = (): void => {
        if (intervalId === null) {
            return;
        }

        window.clearInterval(intervalId);
        intervalId = null;
    };

    const ensureInterval = (): void => {
        if (intervalId !== null) {
            return;
        }

        intervalId = window.setInterval(render, 1000);
    };

    const render = (): void => {
        const state = resolveState(config, holidays);

        if (state.type === 'countdown') {
            ensureInterval();
            prompt.hidden = false;
            time.hidden = false;
            next.hidden = true;
            time.textContent = state.value;
            return;
        }

        stopInterval();
        prompt.hidden = true;
        time.hidden = true;
        next.hidden = false;
        next.innerHTML = state.value;
    };

    render();
};

export const initDeliveryTimers = (): void => {
    document.querySelectorAll<HTMLElement>(SELECTOR).forEach(initDeliveryTimer);
};