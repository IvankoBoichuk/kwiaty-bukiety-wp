import type { Strings } from '@/modules/strings'

export interface ProductPurchaseConfig {
    productId: number
    basePrice: number
    currencySymbol: string
    currencyPrefix: string
    currencySuffix: string
    currencyDecimalSeparator: string
    currencyThousandSeparator: string
    currencyMinorUnit: number
    isVariable: boolean
    storeApiNonce: string
    i18n?: Strings
}