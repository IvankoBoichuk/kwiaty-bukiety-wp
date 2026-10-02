import type { ProductPurchaseConfig } from '@/types/ProductPurchaseConfig'
import { addProductToCart, configureProductWooStoreApi } from './api'
import { formatMoney, type MoneyFormatConfig } from '../money'
import type {
    CartPayload,
    ProductPlugin,
    ProductPurchaseAddition,
    ProductPurchaseStore
} from './types'
import { createTranslator } from '../strings'
import type { ProductVariation } from '@/types/ProductVariations'
import { animateCSS } from '../animate'

export class ProductStore implements ProductPurchaseStore {
    _basePrice: number
    isReady = true
    isSubmitting = false
    productId: number
    currencySymbol: string
    moneyFormat: MoneyFormatConfig
    isVariable: boolean
    quantity = 1
    additions: ProductPurchaseAddition[] = []
    deliveryDate = ''
    deliveryTime = ''
    deliveryDateError = ''
    deliveryTimeError = ''
    deliveryLocation = ''
    deliveryType = ''
    deceasedFullName = ''
    cardMessage = ''
    _variation: ProductVariation | null = null

    readonly #plugins = new Map<string, ProductPlugin>()

    private readonly t: (key: string, fallback: string) => string

    constructor(config: ProductPurchaseConfig) {
        this.t = createTranslator(config.i18n)

        this.productId = config.productId
        this._basePrice = config.basePrice
        this.currencySymbol = config.currencySymbol
        this.moneyFormat = {
            currencySymbol: config.currencySymbol,
            currencyPrefix: config.currencyPrefix,
            currencySuffix: config.currencySuffix,
            currencyDecimalSeparator: config.currencyDecimalSeparator,
            currencyThousandSeparator: config.currencyThousandSeparator,
            currencyMinorUnit: config.currencyMinorUnit,
        }
        this.isVariable = config.isVariable
        configureProductWooStoreApi(config.storeApiNonce)
    }

    get basePrice(): number {
        return this._basePrice
    }

    set basePrice(value: number) {
        this._basePrice = value
    }

    set variation(variation: ProductVariation | null) {
        this._variation = variation

        if (variation) {
            this.basePrice = variation.display_price
            this.isVariable = true
        }
    }

    get variation(): ProductVariation | null {
        return this._variation
    }

    get unitPrice(): number {
        return this._basePrice
    }

    get totalPrice(): number {
        const additionsTotal = this.additions.reduce((total, addition) => total + addition.price, 0)
        return (this.unitPrice + additionsTotal) * this.quantity
    }

    get formattedTotal(): string {
        return formatMoney(this.totalPrice, this.moneyFormat)
    }

    get canSubmit(): boolean {
        return true
    }

    increment(): void {
        this.quantity += 1
    }

    decrement(): void {
        this.quantity = Math.max(1, this.quantity - 1)
    }

    setQuantity(value: number): void {
        this.quantity = Math.max(1, Math.floor(value) || 1)
    }

    setAddition(addition: ProductPurchaseAddition, isSelected: boolean): void {
        const additions = this.additions.filter((item) => item.id !== addition.id)
        this.additions = isSelected ? [...additions, addition] : additions
    }

    hasAddition(additionId: number): boolean {
        return this.additions.some((addition) => addition.id === additionId)
    }

    setDeliveryDate(value: string): void {
        this.deliveryDate = value

        if (value.trim() !== '') {
            this.deliveryDateError = ''
        }
    }

    setDeliveryTime(value: string): void {
        this.deliveryTime = value

        if (value.trim() !== '') {
            this.deliveryTimeError = ''
        }
    }

    setCardMessage(value: string): void {
        this.cardMessage = value
    }

    private getFieldValue(name: string): string {
        const field = document.querySelector<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(`[name="${name}"]`)

        return field?.value.trim() ?? ''
    }

    private getCheckedFieldValue(name: string): string {
        return document.querySelector<HTMLInputElement>(`input[name="${name}"]:checked`)?.value.trim() ?? ''
    }

    private syncFuneralFieldsFromInputs(): void {
        this.deliveryLocation = this.getFieldValue('deliveryLocation')
        this.deliveryType = this.getCheckedFieldValue('deliveryType')
        this.deceasedFullName = this.getFieldValue('deceasedFullName')
    }

    getCartPayload(): CartPayload {
        this.syncDeliveryFieldsFromInputs()
        this.syncFuneralFieldsFromInputs()

        return {
            productId: this.productId,
            quantity: this.quantity,
            variationId: this._variation ? this._variation.variation_id : null,
            attributes: {},
            deliveryDate: this.deliveryDate,
            deliveryTime: this.deliveryTime,
            deliveryLocation: this.deliveryLocation,
            deliveryType: this.deliveryType,
            deceasedFullName: this.deceasedFullName,
            cardMessage: this.cardMessage,
            additionIds: this.additions
                .filter((addition) => addition.includeInPayload !== false)
                .map((addition) => addition.id),
        }
    }

    registerPlugin(plugin: ProductPlugin): void {
        this.#plugins.set(plugin.pluginName, plugin)
    }

    getPlugin(name: string): ProductPlugin | undefined {
        return this.#plugins.get(name)
    }

    initPlugins(store: ProductPurchaseStore): void {
        this.#plugins.forEach((plugin) => {
            plugin.init(store)
        })
    }

    destroy(): void {
        this.#plugins.forEach((plugin) => {
            plugin.destroy?.()
        })

        this.#plugins.clear()
    }

    private scrollToDeliveryDate(): void {
        document
            .querySelector<HTMLElement>('[data-delivery-date-section], [data-funeral-delivery-date-section]')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }

    private syncDeliveryFieldsFromInputs(): void {
        const deliveryDateInput = document.querySelector<HTMLInputElement>('[name="deliveryDate"]')
        const deliveryTimeInput = document.querySelector<HTMLInputElement>('[name="deliveryTime"]')

        if (this.deliveryDate.trim() === '' && deliveryDateInput?.value.trim()) {
            this.setDeliveryDate(deliveryDateInput.value)
        }

        if (this.deliveryTime.trim() === '' && deliveryTimeInput?.value.trim()) {
            this.setDeliveryTime(deliveryTimeInput.value)
        }
    }

    private reportDeliveryInputError(selector: string, message: string): void {
        const input = document.querySelector<HTMLInputElement>(selector)

        if (!input) {
            return
        }

        input.setCustomValidity(message)
        input.reportValidity()

        const clearError = (): void => {
            input.setCustomValidity('')
            input.removeEventListener('input', clearError)
            input.removeEventListener('change', clearError)
        }

        input.addEventListener('input', clearError)
        input.addEventListener('change', clearError)
    }

    private validateBeforeSubmit(): boolean {
        let isValid = true

        this.syncDeliveryFieldsFromInputs()

        if (this.deliveryDate.trim() === '') {
            this.deliveryDateError = this.t('deliveryDateRequired', 'Choose a delivery date')
            isValid = false
        } else {
            this.deliveryDateError = ''
        }

        if (this.deliveryTime.trim() === '') {
            this.deliveryTimeError = this.t('deliveryTimeRequired', 'Choose a delivery time')
            isValid = false
        } else {
            this.deliveryTimeError = ''
        }

        if (!isValid) {
            this.scrollToDeliveryDate()

            if (this.deliveryDate.trim() === '') {
                this.reportDeliveryInputError('[name="deliveryDate"]', this.deliveryDateError)
            } else if (this.deliveryTime.trim() === '') {
                this.reportDeliveryInputError('[name="deliveryTime"]', this.deliveryTimeError)
            }
        }

        return isValid
    }

    async submit(): Promise<void> {
        if (this.isSubmitting || !this.canSubmit) {
            return
        }

        if (!this.validateBeforeSubmit()) {
            return
        }

        this.isSubmitting = true

        try {
            const result = await addProductToCart(this.getCartPayload())
            document.querySelectorAll<HTMLElement>('.counter-for-cart').forEach((element) => {
                element.querySelector('[data-count]')?.setAttribute('data-count', String(result.cartCount || 0));
                animateCSS(element, 'heartBeat')
            })
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Unable to add product to cart.')
        } finally {
            this.isSubmitting = false
        }
    }
}