import type { Strings } from '../strings'
import type { CartResponse as WooStoreApiCartResponse } from '../woo-store-api'

export interface CartCheckoutMoney {
    amount: number
    formatted: string
}

export interface CartCheckoutTotalLine {
    label: string
    amount: CartCheckoutMoney
}

export interface CartCheckoutItem {
    key: string
    productId: number
    name: string
    url: string
    quantity: number
    lineTotal: CartCheckoutMoney
    unitPrice: CartCheckoutMoney
    image?: string
    imageAlt?: string
    summary?: string
}

export interface CartCheckoutCoupon {
    code: string
    discount: CartCheckoutMoney
}

export interface CartCheckoutPaymentMethod {
    id: string
    title: string
    description: string
}

export interface CartCheckoutRoutes {
    updateItem: string
    removeItem: string
}

export interface CartCheckoutConfig {
    currencySymbol: string
    cartUrl: string
    checkoutUrl: string
    items: CartCheckoutItem[]
    totals: {
        subtotal: CartCheckoutTotalLine
        shipping: CartCheckoutTotalLine
        discount: CartCheckoutTotalLine
        total: CartCheckoutTotalLine
    }
    coupons?: CartCheckoutCoupon[]
    paymentMethods: CartCheckoutPaymentMethod[]
    selectedPaymentMethod: string
    routes: CartCheckoutRoutes
    storeApiNonce: string
    recipientFullName?: string
    shippingFirstName?: string
    shippingLastName?: string
    i18n?: Strings
}

export type CartCheckoutStoreApiCartResponse = WooStoreApiCartResponse

export interface CartCheckoutStoreContract {
    currentStep: number
    isLoading: boolean
    isSubmitting: boolean
    validationErrors: Record<string, string>
    items: CartCheckoutItem[]
    totals: CartCheckoutConfig['totals']
    coupons: CartCheckoutCoupon[]
    couponCode: string
    couponError: string
    isCouponLoading: boolean
    paymentMethods: CartCheckoutPaymentMethod[]
    selectedPaymentMethod: string
    recipientFullName: string
    shippingFirstName: string
    shippingLastName: string
    formattedTotal: string
    cartCount: number
    isCartEmpty: boolean
    goToStep(step: number): void
    nextStep(): void
    previousStep(): void
    validateInfoStep(): boolean
    clearFieldError(fieldName?: string): void
    syncRecipientName(value: string): void
    updateQuantity(itemKey: string, quantity: number, productId?: number): Promise<void>
    removeItem(itemKey: string, productId?: number): Promise<void>
    applyCoupon(): Promise<void>
    removeCoupon(code: string): Promise<void>
    submitOrder(event: SubmitEvent): Promise<void>
}