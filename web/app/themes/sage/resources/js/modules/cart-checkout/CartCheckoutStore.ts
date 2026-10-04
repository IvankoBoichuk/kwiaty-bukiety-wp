import type {
    CartCheckoutConfig,
    CartCheckoutStoreApiCartResponse,
    CartCheckoutStoreContract,
} from './types'
import {
    createWooStoreApiToolkit,
    WooStoreApiError,
} from '../woo-store-api'
import { setCartCount } from '../cart-count'
import { formatStoreApiMoney } from '../money'
import { isIntlTelInputValid, normalizeIntlTelInputValue } from '../intl-tel-input'
import * as yup from 'yup'
import type {
    CartResponse as WooStoreApiCartResponse,
    CheckoutOrderRequest,
    CheckoutProcessPaymentDataItem,
    StoreApiErrorData,
    StoreApiAddress,
} from '../woo-store-api'

import { createTranslator } from '../strings'

const DEFAULT_COUNTRY = 'PL'
const PAYU_LIST_BANKS_METHOD = 'payulistbanks'
const PAYU_LIST_BANKS_SELECTOR = '.payu-list-banks input[type="radio"]'
const PAYU_LIST_BANKS_ERROR_SELECTOR = '.pbl-error'

const ADDRESS_KEYS = [
    'first_name',
    'last_name',
    'company',
    'address_1',
    'address_2',
    'city',
    'state',
    'postcode',
    'country',
    'email',
    'phone',
] as const

const KNOWN_CHECKOUT_FIELDS = new Set([
    'ship_to_different_address',
    'payment_method',
    'order_comments',
    'woocommerce-process-checkout-nonce',
    '_wp_http_referer',
    'woocommerce_checkout_update_totals',
    'terms',
    'terms-field',
    'shipping_type_of_place',
    'shipping_place_name',
    'shipping_phone',
    'billing_nip',
    'shipping_first_name',
    'shipping_last_name',
    'shipping_company',
    'shipping_address_1',
    'shipping_address_2',
    'shipping_city',
    'shipping_state',
    'shipping_postcode',
    'shipping_country',
    'billing_first_name',
    'billing_last_name',
    'billing_company',
    'billing_address_1',
    'billing_address_2',
    'billing_city',
    'billing_state',
    'billing_postcode',
    'billing_country',
    'billing_email',
    'billing_phone',
])

const INFO_STEP_FIELD_NAMES = [
    'shipping_type_of_place',
    'shipping_address_1',
    'shipping_postcode',
    'shipping_city',
    'shipping_first_name',
    'shipping_place_name',
    'shipping_phone',
    'billing_first_name',
    'billing_last_name',
    'billing_phone',
    'billing_email',
    'billing_nip',
    'order_comments',
] as const

const SHIPPING_PLACE_TYPES = {
    PRIVATE_ADDRESS: 'private-address',
    COMPANY: 'company',
    CHURCH: 'church',
    FUNERAL_HOME: 'funeral-home',
    HOSPITAL: 'hospital',
    HOTEL: 'hotel',
    SCHOOL: 'school',
}

/*
 * The validation copy now arrives in the config instead of the global wp.i18n,
 * which was only reachable because two render-blocking scripts ran ahead of
 * this module. The translator starts out fallback-only and CartCheckoutStore
 * swaps the real strings in from its constructor, so the schema is built on
 * demand rather than while this module is still evaluating.
 */
let t = createTranslator()

const infoStepSchema = () => yup.object({
    shipping_type_of_place: yup.string().trim().required(t('placeRequired', 'Select a delivery location.')),
    shipping_address_1: yup.string().trim().required(t('addressRequired', 'Enter the delivery address.')),
    shipping_postcode: yup
        .string()
        .trim()
        .matches(/^\d{2}-\d{3}$/, t('postcodeFormat', 'Postal code must use the format 00-000.'))
        .required(t('postcodeRequired', 'Enter the postal code.')),
    shipping_city: yup.string().trim().required(t('cityRequired', 'Enter the city.')),
    shipping_first_name: yup.string().trim().required(t('recipientNameRequired', 'Enter the recipient full name.')),
    shipping_place_name: yup.string().trim().when('shipping_type_of_place', {
        is: SHIPPING_PLACE_TYPES.PRIVATE_ADDRESS,
        then: (schema) => schema.required(t('locationNameRequired', 'Enter the location name.')),
        otherwise: (schema) => schema,
    }),
    shipping_phone: yup.string().trim().required(t('recipientPhoneRequired', 'Enter the recipient phone number.')),
    billing_first_name: yup.string().trim().required(t('senderFirstNameRequired', 'Enter the sender first name.')),
    billing_last_name: yup.string().trim().required(t('senderLastNameRequired', 'Enter the sender last name.')),
    billing_phone: yup.string().trim().required(t('senderPhoneRequired', 'Enter the sender phone number.')),
    billing_email: yup
        .string()
        .trim()
        .matches(
            /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
            t('emailInvalid', 'Enter a valid email address.')
        )
        .required(t('emailRequired', 'Enter the email address.')),
    billing_nip: yup
        .string()
        .trim()
        .test('nip-length', t('taxIdLength', 'Tax ID must contain 10 digits.'), (value) => {
            if (!value) {
                return true
            }

            return value.replace(/\D/g, '').length === 10
        }),
    order_comments: yup.string().trim(),
})

type InfoStepFormData = yup.InferType<ReturnType<typeof infoStepSchema>>

function stripHtml(value?: string): string {
    if (!value) {
        return ''
    }

    const container = document.createElement('div')
    container.innerHTML = value

    return (container.textContent || container.innerText || '').trim()
}

function formatVariationLabel(label?: string): string {
    const normalized = (label || '')
        .replace(/^attribute_/i, '')
        .replace(/^pa_/i, '')
        .replace(/[-_]+/g, ' ')
        .trim()

    if (!normalized) {
        return ''
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1)
}

function variationSummary(item: WooStoreApiCartResponse['items'][number]): string {
    const variationLines = (item.variation || [])
        .map((entry) => {
            const label = formatVariationLabel(entry.attribute)
            const value = stripHtml(entry.value)

            return label && value ? `${label}: ${value}` : ''
        })
        .filter(Boolean)

    if (variationLines.length > 0) {
        return variationLines.join(', ')
    }

    return ''
}

function mapStoreApiCart(cart: CartCheckoutStoreApiCartResponse) {
    return {
        items: (cart.items || []).map((item) => ({
            key: item.key,
            productId: item.id,
            name: item.name,
            url: item.permalink || '',
            quantity: item.quantity,
            lineTotal: formatStoreApiMoney(item.totals?.line_total || '0', item.totals),
            unitPrice: formatStoreApiMoney(item.prices?.price || '0', item.prices),
            image: item.images?.[0]?.src,
            imageAlt: item.images?.[0]?.alt,
            summary: variationSummary(item),
        })),
        coupons: (cart.coupons || []).map((coupon) => ({
            code: coupon.code,
            discount: formatStoreApiMoney(coupon.totals?.total_discount || '0', coupon.totals || cart.totals),
        })),
        totals: {
            subtotal: {
                label: t('subtotal', 'Subtotal'),
                amount: formatStoreApiMoney(cart.totals?.total_items || '0', cart.totals),
            },
            shipping: {
                label: t('delivery', 'Delivery'),
                amount: formatStoreApiMoney(cart.totals?.total_shipping || '0', cart.totals),
            },
            discount: {
                label: t('discount', 'Discount'),
                amount: formatStoreApiMoney(cart.totals?.total_discount || '0', cart.totals),
            },
            total: {
                label: t('orderTotal', 'Order total'),
                amount: formatStoreApiMoney(cart.totals?.total_price || '0', cart.totals),
            },
        },
    }
}

function cartFromError(error: unknown): CartCheckoutStoreApiCartResponse | undefined {
    if (!(error instanceof WooStoreApiError)) {
        return undefined
    }

    const data = error.data as StoreApiErrorData | undefined

    return data?.cart
}

function formValue(formData: FormData, name: string, fallback = ''): string {
    const value = formData.get(name)

    return typeof value === 'string' ? value.trim() : fallback
}

function normalizedPhoneFieldValue(form: HTMLFormElement, fieldName: string): string {
    const input = form.querySelector<HTMLInputElement>(`input[name="${fieldName}"]`)

    if (!input || input.type !== 'tel') {
        return ''
    }

    return normalizeIntlTelInputValue(input)
}

function isValidPhoneField(container: ParentNode, fieldName: string): boolean {
    const input = container.querySelector<HTMLInputElement>(`input[name="${fieldName}"]`)

    if (!input || input.type !== 'tel') {
        return false
    }

    return isIntlTelInputValid(input)
}

function infoStepFields(container: ParentNode): Array<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> {
    return [...container.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea')]
        .filter((field) => field.type !== 'hidden')
}

function collectInfoStepData(container: ParentNode): InfoStepFormData {
    return INFO_STEP_FIELD_NAMES.reduce<InfoStepFormData>((result, name) => {
        const field = container.querySelector<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(`[name="${name}"]`)
        result[name] = field?.value?.trim?.() || ''
        return result
    }, {} as InfoStepFormData)
}

function infoStepValidationSchema(container: ParentNode) {
    return infoStepSchema().shape({
        shipping_phone: yup
            .string()
            .trim()
            .required(t('recipientPhoneRequired', 'Enter the recipient phone number.'))
            .test('shipping-phone-valid', t('recipientPhoneInvalid', 'Enter a valid recipient phone number.'), (value) => {
                if (!value) {
                    return false
                }

                return isValidPhoneField(container, 'shipping_phone')
            }),
        billing_phone: yup
            .string()
            .trim()
            .required(t('senderPhoneRequired', 'Enter the sender phone number.'))
            .test('billing-phone-valid', t('senderPhoneInvalid', 'Enter a valid sender phone number.'), (value) => {
                if (!value) {
                    return false
                }

                return isValidPhoneField(container, 'billing_phone')
            }),
    })
}

function extractValidationErrors(error: yup.ValidationError): Record<string, string> {
    const messages = new Map<string, string>()

    error.inner.forEach((entry) => {
        if (!entry.path || messages.has(entry.path)) {
            return
        }

        messages.set(entry.path, entry.message)
    })

    return Object.fromEntries(messages.entries())
}

function buildAddress(formData: FormData, prefix: 'billing' | 'shipping'): StoreApiAddress {
    const address = ADDRESS_KEYS.reduce<StoreApiAddress>((result, key) => {
        const name = `${prefix}_${key}`
        const value = formValue(formData, name)

        if (value !== '') {
            result[key] = value
        }

        return result
    }, {})

    if (!address.country) {
        address.country = DEFAULT_COUNTRY
    }

    return address
}

function mergeAddressFallback(primary: StoreApiAddress, fallback: StoreApiAddress): StoreApiAddress {
    const merged = { ...primary }

    ADDRESS_KEYS.forEach((key) => {
        if (!merged[key] && fallback[key]) {
            merged[key] = fallback[key]
        }
    })

    return merged
}

function buildAdditionalFields(formData: FormData): Record<string, string> {
    return {
        shipping_type_of_place: formValue(formData, 'shipping_type_of_place'),
        shipping_place_name: formValue(formData, 'shipping_place_name'),
        shipping_phone: formValue(formData, 'shipping_phone'),
        billing_nip: formValue(formData, 'billing_nip'),
    }
}

function buildPaymentData(formData: FormData): CheckoutProcessPaymentDataItem[] {
    const paymentData: CheckoutProcessPaymentDataItem[] = []

    formData.forEach((value, key) => {
        if (KNOWN_CHECKOUT_FIELDS.has(key)) {
            return
        }

        if (typeof value !== 'string' || value === '') {
            return
        }

        paymentData.push({ key, value })
    })

    return paymentData
}

function buildCheckoutOrderRequest(form: HTMLFormElement, selectedPaymentMethod: string): CheckoutOrderRequest {
    const formData = new FormData(form)
    const shippingPhone = normalizedPhoneFieldValue(form, 'shipping_phone')
    const billingPhone = normalizedPhoneFieldValue(form, 'billing_phone')

    if (shippingPhone) {
        formData.set('shipping_phone', shippingPhone)
    }

    if (billingPhone) {
        formData.set('billing_phone', billingPhone)
    }

    const shippingAddress = buildAddress(formData, 'shipping')
    const billingAddress = mergeAddressFallback(
        buildAddress(formData, 'billing'),
        shippingAddress,
    )

    return {
        billing_address: billingAddress,
        shipping_address: shippingAddress,
        additional_fields: buildAdditionalFields(formData),
        customer_note: formValue(formData, 'order_comments'),
        payment_method: selectedPaymentMethod,
        payment_data: buildPaymentData(formData),
    }
}

export class CartCheckoutStore implements CartCheckoutStoreContract {
    currentStep = 1
    isLoading = false
    isSubmitting = false
    validationErrors: Record<string, string> = {}
    items
    totals
    coupons
    couponCode = ''
    couponError = ''
    isCouponLoading = false
    paymentMethods
    selectedPaymentMethod
    recipientFullName
    shippingFirstName
    shippingLastName
    readonly wooStoreApi
    readonly routes
    readonly cartUrl
    readonly checkoutUrl

    constructor(config: CartCheckoutConfig) {
        t = createTranslator(config.i18n)

        this.items = config.items
        this.totals = config.totals
        this.coupons = config.coupons || []
        this.paymentMethods = config.paymentMethods
        this.selectedPaymentMethod = config.selectedPaymentMethod
        this.recipientFullName = config.recipientFullName || ''
        this.shippingFirstName = config.shippingFirstName || ''
        this.shippingLastName = config.shippingLastName || ''
        this.wooStoreApi = createWooStoreApiToolkit({
            nonce: config.storeApiNonce,
        })
        this.routes = config.routes
        this.cartUrl = config.cartUrl
        this.checkoutUrl = config.checkoutUrl
        this.currentStep = this.resolveInitialStep()
        this.bindPaymentMethodListeners()
    }

    get formattedTotal(): string {
        return this.totals.total.amount.formatted
    }

    get cartCount(): number {
        return this.items.reduce((total, item) => total + item.quantity, 0)
    }

    get isCartEmpty(): boolean {
        return this.items.length === 0
    }

    goToStep(step: number): void {
        if (this.isCartEmpty && step > 1) {
            return
        }

        this.currentStep = Math.min(3, Math.max(1, Math.floor(step) || 1))
        this.syncUrlWithStep()
        this.scrollToTop()
    }

    nextStep(): void {
        this.goToStep(this.currentStep + 1)
    }

    previousStep(): void {
        this.goToStep(this.currentStep - 1)
    }

    validateInfoStep(): boolean {
        const infoStep = document.querySelector<HTMLElement>('[data-cart-checkout-info-step]')

        if (!infoStep) {
            return false
        }

        try {
            infoStepValidationSchema(infoStep).validateSync(collectInfoStepData(infoStep), {
                abortEarly: false,
            })

            this.validationErrors = {}
            return true
        } catch (error) {
            if (error instanceof yup.ValidationError) {
                this.validationErrors = extractValidationErrors(error)

                const fields = infoStepFields(infoStep)
                const firstInvalidField = fields.find((field) => this.validationErrors[field.name])

                if (this.currentStep !== 2) {
                    this.goToStep(2)
                }

                if (firstInvalidField) {
                    requestAnimationFrame(() => {
                        firstInvalidField.focus()
                        this.scrollToField(firstInvalidField)
                    })
                }

                return false
            }

            return false
        }
    }

    clearFieldError(fieldName?: string): void {
        if (!fieldName || !this.validationErrors[fieldName]) {
            return
        }

        const nextErrors = { ...this.validationErrors }
        delete nextErrors[fieldName]
        this.validationErrors = nextErrors
    }

    scrollToTop(): void {
        requestAnimationFrame(() => {
            const container = document.querySelector<HTMLElement>('#sage-cart-checkout-form')
            const header = document.getElementById('header');

            if (container) {
                const offsetTop = container.getBoundingClientRect().top + window.scrollY - (header?.clientHeight ?? 0) - 16

                window.scrollTo({
                    top: Math.max(0, offsetTop),
                    behavior: 'smooth',
                })

                return
            }

            window.scrollTo({ top: 0, behavior: 'smooth' })
        })
    }

    scrollToField(field: HTMLElement): void {
        requestAnimationFrame(() => {
            const header = document.getElementById('header');
            const offsetTop = field.getBoundingClientRect().top + window.scrollY - (header?.clientHeight ?? 0) - (21 + 6)

            window.scrollTo({
                top: Math.max(0, offsetTop),
                behavior: 'smooth',
            })
        })
    }

    syncRecipientName(value: string): void {
        const normalized = value.trim().replace(/\s+/g, ' ')
        const [firstName = '', ...lastNameParts] = normalized.split(' ')

        this.recipientFullName = value
        this.shippingFirstName = firstName
        this.shippingLastName = lastNameParts.join(' ')
    }

    async updateQuantity(itemKey: string, quantity: number, productId?: number): Promise<void> {
        if (quantity < 1) {
            await this.removeItem(itemKey, productId)
            return
        }

        await this.mutateCart(() => this.wooStoreApi.cartItems.update({
            key: itemKey,
            quantity,
        }))
    }

    async removeItem(itemKey: string, _productId?: number): Promise<void> {
        await this.mutateCart(() => this.wooStoreApi.cartItems.remove({
            key: itemKey,
        }))
    }

    async applyCoupon(): Promise<void> {
        const code = this.couponCode.trim()

        if (code === '') {
            this.couponError = t('couponRequired', 'Enter a coupon code.')
            return
        }

        const applied = await this.mutateCoupon(
            () => this.wooStoreApi.cartCoupons.apply({ code }),
            t('couponApplyFailed', 'We could not apply this coupon.'),
        )

        if (applied) {
            this.couponCode = ''
        }
    }

    async removeCoupon(code: string): Promise<void> {
        await this.mutateCoupon(
            () => this.wooStoreApi.cartCoupons.remove({ code }),
            t('couponRemoveFailed', 'We could not remove this coupon.'),
        )
    }

    /**
     * Coupon mistypes are routine, so unlike the cart mutations these keep
     * their own loading flag and report the rejection next to the field --
     * Woo's own message ("Coupon does not exist!", "already applied") is more
     * use than a window.alert with the fallback copy.
     */
    async mutateCoupon(
        runRequest: () => Promise<CartCheckoutStoreApiCartResponse>,
        fallbackMessage: string,
    ): Promise<boolean> {
        if (this.isCouponLoading) {
            return false
        }

        this.isCouponLoading = true
        this.couponError = ''

        try {
            this.syncCart(await runRequest())

            return true
        } catch (error) {
            const nextCart = cartFromError(error)

            if (nextCart) {
                this.syncCart(nextCart)
            }

            const message = error instanceof Error ? stripHtml(error.message) : ''
            this.couponError = message || fallbackMessage

            return false
        } finally {
            this.isCouponLoading = false
        }
    }

    async submitOrder(event: SubmitEvent): Promise<void> {
        event.preventDefault()

        if (this.isSubmitting) {
            return
        }

        const form = event.currentTarget

        if (!(form instanceof HTMLFormElement)) {
            return
        }

        if (!this.validatePayuBankSelection(form)) {
            return
        }

        if (!this.validateInfoStep() || !form.reportValidity()) {
            return
        }

        this.isSubmitting = true

        try {
            const response = await this.wooStoreApi.checkoutOrder.process(
                buildCheckoutOrderRequest(form, this.selectedPaymentMethod),
            )

            if (response.payment_result?.redirect_url) {
                window.location.href = response.payment_result.redirect_url
                return
            }

            window.location.href = this.checkoutUrl
        } catch (error) {
            window.alert(error instanceof Error ? error.message : t('orderFailed', 'Unable to place the order.'))
        } finally {
            this.isSubmitting = false
        }
    }

    async mutateCart(runRequest: () => Promise<CartCheckoutStoreApiCartResponse>): Promise<void> {
        if (this.isLoading) {
            return
        }

        this.isLoading = true

        try {
            this.syncCart(await runRequest())

            if (this.isCartEmpty) {
                this.currentStep = 1
            }

            this.syncUrlWithStep()
        } catch (error) {
            const nextCart = cartFromError(error)

            if (nextCart) {
                this.syncCart(nextCart)
            }

            window.alert(error instanceof Error ? error.message : 'Unable to update the cart.')
        } finally {
            this.isLoading = false
        }
    }

    syncCart(cart: CartCheckoutStoreApiCartResponse): void {
        const nextCart = mapStoreApiCart(cart)

        this.items = nextCart.items
        this.totals = nextCart.totals
        this.coupons = nextCart.coupons

        // The header and bottom-bar badges are part of the cached page shell,
        // so nothing else would move them when an item is removed here.
        setCartCount(cart.items_count ?? nextCart.items.reduce((total, item) => total + item.quantity, 0))
    }

    resolveInitialStep(): number {
        const currentPath = window.location.pathname.replace(/\/+$/, '')
        const checkoutPath = this.urlPath(this.checkoutUrl)

        if (checkoutPath !== '' && currentPath === checkoutPath) {
            return this.isCartEmpty ? 1 : 2
        }

        return 1
    }

    syncUrlWithStep(): void {
        const targetUrl = this.currentStep > 1 ? this.checkoutUrl : this.cartUrl

        if (!targetUrl) {
            return
        }

        const targetPath = this.urlPath(targetUrl)
        const currentPath = window.location.pathname.replace(/\/+$/, '')

        if (targetPath === '' || currentPath === targetPath) {
            return
        }

        window.history.replaceState({ step: this.currentStep }, '', targetUrl)
    }

    bindPaymentMethodListeners(): void {
        requestAnimationFrame(() => {
            const form = document.getElementById('sage-cart-checkout-form')

            if (!(form instanceof HTMLFormElement)) {
                return
            }

            form.addEventListener('change', (event) => {
                const target = event.target

                if (!(target instanceof HTMLInputElement)) {
                    return
                }

                if (target.name === 'payment_method' || target.closest('.payu-list-banks')) {
                    this.hidePayuBankError(form)
                }
            })
        })
    }

    validatePayuBankSelection(form: HTMLFormElement): boolean {
        if (this.selectedPaymentMethod !== PAYU_LIST_BANKS_METHOD) {
            this.hidePayuBankError(form)
            return true
        }

        const selectedBank = form.querySelector<HTMLInputElement>(`${PAYU_LIST_BANKS_SELECTOR}:checked`)

        if (selectedBank instanceof HTMLInputElement) {
            this.hidePayuBankError(form)
            return true
        }

        const errorElement = form.querySelector<HTMLElement>(PAYU_LIST_BANKS_ERROR_SELECTOR)

        if (errorElement) {
            errorElement.style.display = 'block'
        }

        return false
    }

    hidePayuBankError(container: ParentNode = document): void {
        const errorElement = container.querySelector<HTMLElement>(PAYU_LIST_BANKS_ERROR_SELECTOR)

        if (!errorElement) {
            return
        }

        errorElement.style.display = 'none'
    }

    urlPath(url: string): string {
        try {
            return new URL(url, window.location.origin).pathname.replace(/\/+$/, '')
        } catch {
            return ''
        }
    }
}