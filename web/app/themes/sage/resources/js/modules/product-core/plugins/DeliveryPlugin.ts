import type {
    DeliverySchedule,
    DeliveryTimeSlot,
    ProductPlugin,
    ProductPurchaseStore,
} from '../types'
import { fetchDeliveryContext } from '../api'

function formatDateValue(date: Date): string {
    const year = date.getFullYear()
    const month = `${date.getMonth() + 1}`.padStart(2, '0')
    const day = `${date.getDate()}`.padStart(2, '0')

    return `${year}-${month}-${day}`
}

function setHiddenValue(selector: string, value: string): void {
    document.querySelectorAll<HTMLInputElement>(selector).forEach((input) => {
        input.value = value
    })
}

function toDateKey(date: Date): string {
    return formatDateValue(date)
}

function getAvailableTimeSlots(schedule: DeliverySchedule, deliveryDate: Date | null): DeliveryTimeSlot[] {
    if (!deliveryDate) {
        return []
    }

    return schedule.timeOptionsByDate?.[toDateKey(deliveryDate)] ?? []
}

export class DeliveryPlugin implements ProductPlugin {
    pluginName = 'delivery'
    private selectedDate: Date | null = null
    private schedule: DeliverySchedule = {}
    private store: ProductPurchaseStore | null = null
    private dateInput: HTMLInputElement | null = null
    private dateLabel: HTMLSpanElement | null = null
    private customDateLabel = ''
    private funeralDateInput: HTMLInputElement | null = null
    private funeralTimeInput: HTMLInputElement | null = null

    private clearInputError(input: HTMLInputElement): void {
        input.setCustomValidity('')
    }

    private setInputError(input: HTMLInputElement, message: string): void {
        input.setCustomValidity(message)
        input.reportValidity()
    }

    private toTimeValue(hour: number): string {
        return `${String(hour).padStart(2, '0')}:00`
    }

    private matchesAvailableSlot(value: string, selectedDate: Date): boolean {
        const [hours, minutes] = value.split(':').map(Number)

        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return false
        }

        const totalMinutes = (hours * 60) + minutes
        const availableSlots = getAvailableTimeSlots(this.schedule, selectedDate)

        return availableSlots.some((slot) => {
            const slotStartMinutes = slot.start * 60
            const slotEndMinutes = slot.end * 60

            return totalMinutes >= slotStartMinutes
                && totalMinutes < slotEndMinutes
        })
    }

    init(store: ProductPurchaseStore): void {
        const scheduleHost = document.querySelector<HTMLElement>('[data-delivery-schedule]')
        const dateOptions = document.querySelectorAll<HTMLButtonElement>('.delivery-date-option')
        const customDateBtn = document.querySelector<HTMLButtonElement>('.delivery-date-custom')
        const dateInput = document.getElementById('delivery-date-input') as HTMLInputElement | null
        const dateLabel = document.querySelector<HTMLSpanElement>('.delivery-date-label')
        const funeralDateInput = document.querySelector<HTMLInputElement>('[data-funeral-delivery-date-input]')
            ?? document.querySelector<HTMLInputElement>('[name="deliveryDate"]')
        const funeralTimeInput = document.querySelector<HTMLInputElement>('[data-funeral-delivery-time-input]')
            ?? document.querySelector<HTMLInputElement>('[name="deliveryTime"]')
        const cardMessageField = document.querySelector<HTMLTextAreaElement>('[name="card-message"]')
        const timeOptions = document.querySelectorAll<HTMLButtonElement>('.delivery-time-option')
        const rawSchedule = scheduleHost?.dataset.deliverySchedule

        if (!rawSchedule) {
            return
        }

        this.schedule = JSON.parse(rawSchedule) as DeliverySchedule
        this.store = store
        this.dateInput = dateInput
        this.dateLabel = dateLabel
        this.funeralDateInput = funeralDateInput
        this.funeralTimeInput = funeralTimeInput

        this.applyScheduleBounds()

        setHiddenValue('[data-delivery-date-hidden]', '')
        setHiddenValue('[data-delivery-time-hidden]', '')
        setHiddenValue('[data-card-message-hidden]', cardMessageField?.value ?? '')
        store.setDeliveryDate('')
        store.setDeliveryTime('')
        store.setCardMessage(cardMessageField?.value ?? '')

        // The markup above was rendered before the page cache stored it, so
        // its calendar can be up to a week old -- "Dziś" carrying the date the
        // page was generated on, and today's slots as they stood that minute.
        // The fresh copy replaces it as soon as it arrives.
        void this.refreshSchedule()

        if (funeralDateInput && funeralTimeInput) {
            this.initFuneralNativeInputs(store, funeralDateInput, funeralTimeInput)
            return
        }

        this.updateTimeSlotAvailability(store, this.selectedDate)

        this.customDateLabel = dateLabel?.textContent ?? ''
        const customDateLabel = this.customDateLabel

        dateOptions.forEach((btn) => {
            btn.addEventListener('click', () => {
                const dateValue = btn.dataset.dateValue
                const dateOption = btn.dataset.dateOption

                if (dateValue) {
                    this.activateDateOption(dateOptions, btn)

                    // A preset wins over whatever the picker last returned, so
                    // the custom button drops back to its neutral label.
                    if (dateInput) {
                        dateInput.value = ''
                    }

                    if (dateLabel) {
                        dateLabel.textContent = customDateLabel
                    }

                    this.selectedDate = new Date(`${dateValue}T00:00:00`)
                    this.syncSelectedDate(store, this.selectedDate)

                    return
                }

                if (dateOption === 'custom' && dateInput) {
                    this.openDatePicker(dateInput)
                }
            })
        })

        if (dateInput && dateLabel && customDateBtn) {
            // The input covers the custom button, so its own click is the only
            // one that ever fires there.
            dateInput.addEventListener('click', () => {
                this.openDatePicker(dateInput)
            })

            dateInput.addEventListener('change', (event) => {
                const target = event.target as HTMLInputElement

                if (!target.value) {
                    return
                }

                this.selectedDate = new Date(`${target.value}T00:00:00`)

                if (Number.isNaN(this.selectedDate.getTime())) {
                    this.selectedDate = null

                    return
                }

                this.activateDateOption(dateOptions, customDateBtn)
                dateLabel.textContent = this.selectedDate.toLocaleDateString('pl-PL', {
                    day: '2-digit',
                    month: '2-digit',
                })

                this.syncSelectedDate(store, this.selectedDate)
            })
        }

        timeOptions.forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.disabled) {
                    return
                }

                timeOptions.forEach((button) => {
                    button.classList.remove('active')
                })

                btn.classList.add('active')

                const value = btn.dataset.timeSlot ?? ''
                setHiddenValue('[data-delivery-time-hidden]', value)
                store.setDeliveryTime(value)
            })
        })

        cardMessageField?.addEventListener('input', () => {
            setHiddenValue('[data-card-message-hidden]', cardMessageField.value)
            store.setCardMessage(cardMessageField.value)
        })
    }

    /**
     * Swaps the rendered calendar for the one the server has right now, and
     * drops a selection the customer made against the stale one. Does nothing
     * when the request fails: the server refuses a slot that is no longer
     * offered either way, so a stale pick ends in an error rather than an
     * order nobody can deliver.
     */
    private async refreshSchedule(): Promise<void> {
        const context = await fetchDeliveryContext()

        if (!context || !this.store) {
            return
        }

        this.schedule = context.schedule

        this.applyScheduleBounds()
        this.applyDateOptions()
        this.revalidateSelectedDate(this.store)
    }

    /**
     * The custom-date picker may not offer a day the schedule has no slots
     * for, at either end.
     */
    private applyScheduleBounds(): void {
        const availableDateKeys = Object.keys(this.schedule.timeOptionsByDate ?? {})
        const firstAvailableDate = availableDateKeys[0] ?? ''
        const lastAvailableDate = availableDateKeys[availableDateKeys.length - 1] ?? ''

        const dateInputs = [this.dateInput, this.funeralDateInput]

        dateInputs.forEach((input) => {
            if (!input) {
                return
            }

            if (firstAvailableDate) {
                input.min = firstAvailableDate
            }

            if (lastAvailableDate) {
                input.max = lastAvailableDate
            }
        })
    }

    /**
     * Rewrites the "Dziś"/"Jutro" presets. There are never more than two, and
     * a day that has run out of slots leaves one fewer, so the spare button is
     * hidden rather than left pointing at a date the server would refuse.
     */
    private applyDateOptions(): void {
        const freshOptions = this.schedule.dateOptions ?? []
        const presetButtons = document.querySelectorAll<HTMLButtonElement>('.delivery-date-option[data-date-value]')

        presetButtons.forEach((btn, index) => {
            const option = freshOptions[index]

            if (!option) {
                btn.hidden = true
                btn.classList.remove('active')

                return
            }

            btn.hidden = false
            btn.dataset.dateValue = option.value
            btn.textContent = option.label
        })
    }

    private revalidateSelectedDate(store: ProductPurchaseStore): void {
        if (!this.selectedDate) {
            return
        }

        if (getAvailableTimeSlots(this.schedule, this.selectedDate).length > 0) {
            // Still deliverable, but its slots may have moved on.
            if (this.funeralDateInput && this.funeralTimeInput) {
                this.updateFuneralTimeOptions(store, this.funeralTimeInput, this.selectedDate)
            } else {
                this.updateTimeSlotAvailability(store, this.selectedDate)
            }

            return
        }

        this.selectedDate = null
        setHiddenValue('[data-delivery-date-hidden]', '')
        store.setDeliveryDate('')

        if (this.funeralDateInput && this.funeralTimeInput) {
            this.funeralDateInput.value = ''
            this.updateFuneralTimeOptions(store, this.funeralTimeInput, null)

            return
        }

        if (this.dateInput) {
            this.dateInput.value = ''
        }

        if (this.dateLabel) {
            this.dateLabel.textContent = this.customDateLabel
        }

        document.querySelectorAll<HTMLButtonElement>('.delivery-date-option').forEach((btn) => {
            btn.classList.remove('active')
        })

        this.updateTimeSlotAvailability(store, null)
    }

    private initFuneralNativeInputs(store: ProductPurchaseStore, dateInput: HTMLInputElement, timeInput: HTMLInputElement): void {
        this.updateFuneralTimeOptions(store, timeInput, null)

        dateInput.addEventListener('change', () => {
            if (!dateInput.value) {
                this.selectedDate = null
                setHiddenValue('[data-delivery-date-hidden]', '')
                store.setDeliveryDate('')
                this.updateFuneralTimeOptions(store, timeInput, null)
                return
            }

            this.selectedDate = new Date(`${dateInput.value}T00:00:00`)

            if (Number.isNaN(this.selectedDate.getTime()) || getAvailableTimeSlots(this.schedule, this.selectedDate).length === 0) {
                dateInput.value = ''
                this.selectedDate = null
                setHiddenValue('[data-delivery-date-hidden]', '')
                store.setDeliveryDate('')
                this.updateFuneralTimeOptions(store, timeInput, null)
                return
            }

            setHiddenValue('[data-delivery-date-hidden]', dateInput.value)
            store.setDeliveryDate(dateInput.value)
            this.updateFuneralTimeOptions(store, timeInput, this.selectedDate)
        })

        timeInput.addEventListener('change', () => {
            const value = timeInput.value

            this.clearInputError(timeInput)

            if (!this.selectedDate || value === '') {
                setHiddenValue('[data-delivery-time-hidden]', '')
                store.setDeliveryTime('')
                return
            }

            if (!this.matchesAvailableSlot(value, this.selectedDate)) {
                timeInput.value = ''
                setHiddenValue('[data-delivery-time-hidden]', '')
                store.setDeliveryTime('')
                this.setInputError(timeInput, 'Choose an available delivery time.')
                return
            }

            setHiddenValue('[data-delivery-time-hidden]', value)
            store.setDeliveryTime(value)
        })
    }

    private activateDateOption(dateOptions: NodeListOf<HTMLButtonElement>, active: HTMLButtonElement): void {
        dateOptions.forEach((button) => {
            button.classList.remove('active')
        })

        active.classList.add('active')
    }

    /**
     * Desktop browsers only open the calendar when the click lands on their own
     * picker indicator, which this input hides, so it has to be asked for
     * explicitly. Touch Safari opens its picker from the tap itself and chokes
     * on a second request, and Safari below 16 has no showPicker() at all.
     */
    private openDatePicker(input: HTMLInputElement): void {
        if (typeof input.showPicker !== 'function' || window.matchMedia('(pointer: coarse)').matches) {
            return
        }

        try {
            input.showPicker()
        } catch {
            // Thrown when the browser does not count this as a user gesture;
            // the input stays focused and keyboard-editable either way.
        }
    }

    private syncSelectedDate(store: ProductPurchaseStore, selectedDate: Date): void {
        this.updateTimeSlotAvailability(store, selectedDate)

        const value = formatDateValue(selectedDate)
        setHiddenValue('[data-delivery-date-hidden]', value)
        store.setDeliveryDate(value)
    }

    private updateTimeSlotAvailability(store: ProductPurchaseStore, selectedDate: Date | null): void {
        const timeOptions = document.querySelectorAll<HTMLButtonElement>('.delivery-time-option')

        if (!selectedDate) {
            timeOptions.forEach((btn) => {
                btn.disabled = true
                btn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none')
                btn.classList.remove('active', 'bg-green-easy', 'text-white')
            })

            setHiddenValue('[data-delivery-time-hidden]', '')
            store.setDeliveryTime('')

            return
        }

        let activeSlotStillAvailable = false
        const availableSlots = getAvailableTimeSlots(this.schedule, selectedDate)

        timeOptions.forEach((btn) => {
            const slotStart = Number(btn.dataset.slotStart)
            const slotEnd = Number(btn.dataset.slotEnd)

            if (Number.isNaN(slotStart) || Number.isNaN(slotEnd)) {
                return
            }

            const isAvailable = availableSlots.some((slot) => slot.start === slotStart && slot.end === slotEnd)

            if (isAvailable) {
                btn.disabled = false
                btn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none')

                if (btn.classList.contains('bg-green-easy')) {
                    activeSlotStillAvailable = true
                }

                return
            }

            btn.disabled = true
            btn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none')
            btn.classList.remove('active')
        })

        if (!activeSlotStillAvailable) {
            setHiddenValue('[data-delivery-time-hidden]', '')
            store.setDeliveryTime('')
        }
    }

    private updateFuneralTimeOptions(
        store: ProductPurchaseStore,
        timeInput: HTMLInputElement,
        selectedDate: Date | null,
    ): void {
        this.clearInputError(timeInput)

        const availableSlots = getAvailableTimeSlots(this.schedule, selectedDate)

        if (availableSlots.length === 0) {
            timeInput.disabled = true
            timeInput.min = ''
            timeInput.max = ''
            timeInput.step = ''
            timeInput.value = ''
            setHiddenValue('[data-delivery-time-hidden]', '')
            store.setDeliveryTime('')
            return
        }

        timeInput.disabled = false
        timeInput.min = this.toTimeValue(availableSlots[0].start)
        timeInput.max = this.toTimeValue(availableSlots[availableSlots.length - 1].end)
        timeInput.step = '1800'

        if (timeInput.value && selectedDate && !this.matchesAvailableSlot(timeInput.value, selectedDate)) {
            timeInput.value = ''
            setHiddenValue('[data-delivery-time-hidden]', '')
            store.setDeliveryTime('')
        }
    }
}
