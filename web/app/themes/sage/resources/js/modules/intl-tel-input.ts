import type { Iti } from 'intl-tel-input'

const itiInstances = new WeakMap<HTMLInputElement, Iti>()

/**
 * The library and its flag sprites are only needed where a phone field exists
 * -- the checkout and the product page -- so load them on demand rather than
 * from the shared bundle. The validation helpers below stay synchronous: they
 * read the instance map and fall back to a digit count when it is empty, which
 * is also what happens before this resolves.
 */
export async function initIntlTelInputs(): Promise<void> {
    const phoneInputs = document.querySelectorAll<HTMLInputElement>('input[type="tel"]')

    if (phoneInputs.length === 0) {
        // The checkout form is only rendered once the cart has items, so the
        // field often arrives after this first pass. Wait for it instead of
        // giving up, which is what the eager version silently did.
        watchForPhoneInputs()

        return
    }

    await enhance(phoneInputs)
}

let watching = false

function watchForPhoneInputs(): void {
    if (watching || typeof MutationObserver === 'undefined') {
        return
    }

    watching = true

    const observer = new MutationObserver(() => {
        const inputs = document.querySelectorAll<HTMLInputElement>('input[type="tel"]')

        if (inputs.length === 0) {
            return
        }

        observer.disconnect()
        watching = false
        void enhance(inputs)
    })

    observer.observe(document.body, { childList: true, subtree: true })
}

async function enhance(phoneInputs: NodeListOf<HTMLInputElement>): Promise<void> {
    const [{ default: intlTelInput }] = await Promise.all([
        import('intl-tel-input'),
        import('intl-tel-input/styles'),
    ])

    phoneInputs.forEach((input) => {
        if (input.dataset.intlTelInputInitialized === 'true') {
            return
        }

        const instance = intlTelInput(input, {
            initialCountry: 'pl',
            nationalMode: true,
            autoPlaceholder: 'aggressive',
            formatOnDisplay: true,
            separateDialCode: true,
            loadUtils: () => import('intl-tel-input/utils'),
        })

        itiInstances.set(input, instance)

        input.dataset.intlTelInputInitialized = 'true'
    })
}

export function normalizeIntlTelInputValue(input: HTMLInputElement): string {
    const instance = itiInstances.get(input)

    if (!instance) {
        return input.value.trim()
    }

    const normalizedValue = instance.getNumber('E164')

    if (!normalizedValue) {
        return input.value.trim()
    }

    input.value = normalizedValue

    return normalizedValue
}

export function isIntlTelInputValid(input: HTMLInputElement): boolean {
    const instance = itiInstances.get(input)

    if (!instance) {
        return input.value.replace(/\D/g, '').length >= 9
    }

    const validationResult = instance.isValidNumber()

    if (validationResult === null) {
        return input.value.replace(/\D/g, '').length >= 9
    }

    return validationResult
}