import type { ProductVariation } from "@/types/ProductVariations"

export interface ProductPurchaseAddition {
    id: number
    name: string
    price: number
    includeInPayload?: boolean
}

export interface ProductPurchaseStore {
    isReady: boolean
    isSubmitting: boolean
    productId: number
    basePrice: number
    currencySymbol: string
    isVariable: boolean
    variation: ProductVariation | null
    quantity: number
    // selectedVariationId: number | null
    // selectedVariation: ProductVariation | null
    additions: ProductPurchaseAddition[]
    deliveryDate: string
    deliveryTime: string
    deliveryDateError: string
    deliveryTimeError: string
    deliveryLocation: string
    deliveryType: string
    deceasedFullName: string
    cardMessage: string
    unitPrice: number
    totalPrice: number
    formattedTotal: string
    canSubmit: boolean
    increment(): void
    decrement(): void
    setQuantity(value: number): void
    // setVariation(variation: ProductVariation): void
    setAddition(addition: ProductPurchaseAddition, isSelected: boolean): void
    hasAddition(additionId: number): boolean
    setDeliveryDate(value: string): void
    setDeliveryTime(value: string): void
    setCardMessage(value: string): void
    submit(): Promise<void>
}

export interface CartPayload {
    productId: number
    quantity: number
    variationId: number | null
    attributes: Record<string, string>
    deliveryDate: string
    deliveryTime: string
    deliveryLocation: string
    deliveryType: string
    deceasedFullName: string
    cardMessage: string
    additionIds: number[]
}

export interface CartResponse {
    status?: string
    message?: string
    cartCount?: number
    cartUrl?: string
}

export interface DeliveryTimeSlot {
    value?: string
    label?: string
    start: number
    end: number
}

export interface DeliveryDateOption {
    value: string
    label: string
}

export interface DeliverySchedule {
    dateOptions?: DeliveryDateOption[]
    timeOptions?: DeliveryTimeSlot[]
    timeOptionsByDate?: Record<string, DeliveryTimeSlot[]>
}

/**
 * What the product page cannot be rendered with, because the page cache would
 * then serve it for a week: the schedule as of now and a fresh Store API
 * nonce. See App\Api\DeliverySchedule.
 */
export interface DeliveryContext {
    schedule: DeliverySchedule
    storeApiNonce: string
}

export interface ProductPlugin {
    pluginName: string
    init(store: ProductPurchaseStore): void
    destroy?(): void
}
