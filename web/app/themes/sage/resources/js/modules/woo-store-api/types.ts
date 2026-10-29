export type StoreApiMethod = 'GET' | 'POST' | 'PUT' | 'DELETE'

export type StoreApiQueryValue =
    | string
    | number
    | boolean
    | null
    | undefined

export type StoreApiQuery = Record<
    string,
    StoreApiQueryValue | StoreApiQueryValue[]
>

export interface StoreApiRequestOptions {
    query?: StoreApiQuery
    headers?: HeadersInit
    signal?: AbortSignal
}

export interface StoreApiClientOptions {
    baseUrl?: string
    nonce?: string
    cartToken?: string
    fetch?: typeof fetch
}

export interface StoreApiErrorData {
    status?: number
    cart?: CartResponse
    [key: string]: unknown
}

export interface StoreApiErrorResponse<TData = StoreApiErrorData> {
    code?: string
    message?: string
    data?: TData
}

export interface StoreApiImage {
    id?: number
    src?: string
    thumbnail?: string
    srcset?: string
    sizes?: string
    name?: string
    alt?: string
}

export interface StoreApiMoney {
    currency_code: string
    currency_symbol: string
    currency_minor_unit: number
    currency_decimal_separator: string
    currency_thousand_separator: string
    currency_prefix: string
    currency_suffix: string
}

export interface StoreApiQuantityLimits {
    minimum: number
    maximum: number
    multiple_of: number
    editable: boolean
}

export interface StoreApiItemVariation {
    attribute?: string
    value?: string
}

export interface StoreApiItemDataEntry {
    key?: string
    name?: string
    value?: string
    display?: string
}

export interface StoreApiItemPrices extends StoreApiMoney {
    price: string
    regular_price?: string
    sale_price?: string
    price_range?: unknown
    raw_prices?: {
        precision?: number
        price?: string
        regular_price?: string
        sale_price?: string
    }
}

export interface StoreApiItemTotals extends StoreApiMoney {
    line_subtotal?: string
    line_subtotal_tax?: string
    line_total: string
    line_total_tax?: string
}

export interface StoreApiCartItem {
    key: string
    id: number
    quantity: number
    quantity_limits?: StoreApiQuantityLimits
    name: string
    short_description?: string
    description?: string
    sku?: string
    low_stock_remaining?: number | null
    backorders_allowed?: boolean
    show_backorder_badge?: boolean
    sold_individually?: boolean
    permalink?: string
    images?: StoreApiImage[]
    variation?: StoreApiItemVariation[]
    item_data?: StoreApiItemDataEntry[]
    prices: StoreApiItemPrices
    totals: StoreApiItemTotals
    catalog_visibility?: string
    extensions?: Record<string, unknown>
}

export interface StoreApiCouponTotals extends StoreApiMoney {
    total_discount: string
    total_discount_tax?: string
}

export interface StoreApiCartCoupon {
    code: string
    discount_type?: string
    totals: StoreApiCouponTotals
}

export interface StoreApiTaxLine {
    name?: string
    price?: string
    rate?: string
}

export interface StoreApiCartTotals extends StoreApiMoney {
    total_items: string
    total_items_tax?: string
    total_fees?: string
    total_fees_tax?: string
    total_discount: string
    total_discount_tax?: string
    total_shipping: string
    total_shipping_tax?: string
    total_price: string
    total_tax?: string
    tax_lines?: StoreApiTaxLine[]
}

export interface StoreApiAddress {
    first_name?: string
    last_name?: string
    company?: string
    address_1?: string
    address_2?: string
    city?: string
    state?: string
    postcode?: string
    country?: string
    email?: string
    phone?: string
}

export interface StoreApiShippingRateItem {
    key?: string
    name?: string
    quantity?: number
}

export interface StoreApiShippingRate {
    rate_id: string
    name: string
    description?: string
    delivery_time?: string
    price: string
    taxes?: string
    instance_id?: number
    method_id?: string
    meta_data?: StoreApiItemDataEntry[]
    selected?: boolean
    currency_code?: string
    currency_symbol?: string
    currency_minor_unit?: number
    currency_decimal_separator?: string
    currency_thousand_separator?: string
    currency_prefix?: string
    currency_suffix?: string
}

export interface StoreApiShippingPackage {
    package_id: number
    name?: string
    destination?: StoreApiAddress
    items?: StoreApiShippingRateItem[]
    shipping_rates?: StoreApiShippingRate[]
}

export interface CartResponse {
    items: StoreApiCartItem[]
    coupons: StoreApiCartCoupon[]
    fees?: unknown[]
    totals: StoreApiCartTotals
    shipping_address?: StoreApiAddress
    billing_address?: StoreApiAddress
    needs_payment?: boolean
    needs_shipping?: boolean
    has_calculated_shipping?: boolean
    shipping_rates?: StoreApiShippingPackage[]
    items_count?: number
    items_weight?: number
    cross_sells?: unknown[]
    errors?: Array<{ code?: string; message?: string }>
    payment_methods?: string[]
    payment_requirements?: string[]
    extensions?: Record<string, unknown>
}

export interface CartAddItemRequest {
    id: number
    quantity: number
    variation?: StoreApiItemVariation[]
}

export interface CartUpdateItemRequest {
    key: string
    quantity: number
}

export interface CartRemoveItemRequest {
    key: string
}

export interface CartCouponRequest {
    code: string
}

export interface CartUpdateCustomerRequest {
    billing_address?: Partial<StoreApiAddress>
    shipping_address?: Partial<StoreApiAddress>
}

export interface CartSelectShippingRateRequest {
    package_id: number
    rate_id: string
}

export interface StoreApiPaymentResult {
    payment_status?: string
    payment_details?: Array<{ key?: string; value?: unknown }>
    redirect_url?: string
}

export interface CheckoutResponse {
    order_id: number
    status: string
    order_key: string
    order_number?: string
    customer_note?: string
    customer_id?: number
    billing_address?: StoreApiAddress
    shipping_address?: StoreApiAddress
    payment_method?: string
    payment_result?: StoreApiPaymentResult | null
    additional_fields?: Record<string, unknown>
    __experimentalCart?: CartResponse
    extensions?: Record<string, unknown>
}

export interface CheckoutUpdateRequest {
    additional_fields?: Record<string, unknown>
    payment_method?: string
    order_notes?: string
}

export interface CheckoutProcessPaymentDataItem {
    key: string
    value: unknown
}

export interface CheckoutOrderRequest {
    billing_address: StoreApiAddress
    shipping_address: StoreApiAddress
    additional_fields?: Record<string, unknown>
    customer_note?: string
    create_account?: boolean
    customer_password?: string
    payment_method: string
    payment_data?: CheckoutProcessPaymentDataItem[]
    extensions?: Record<string, unknown>
}

export interface OrderResponse extends CartResponse {
    id: number
    status: string
}

export interface OrderRequest {
    id: number
    key: string
    billing_email?: string
}