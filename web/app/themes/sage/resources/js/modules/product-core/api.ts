import { createWooStoreApiToolkit } from '../woo-store-api'
import type { StoreApiItemVariation } from '../woo-store-api'
import type { CartPayload, CartResponse, DeliveryContext } from './types'

const DELIVERY_CONTEXT_ENDPOINT = '/wp-json/sage/v1/delivery-schedule'

let deliveryContextRequest: Promise<DeliveryContext | null> | null = null

let wooStoreApi = createWooStoreApiToolkit()

function normalizeVariation(attributes: CartPayload['attributes']): StoreApiItemVariation[] {
    return Object.entries(attributes)
        .filter(([, value]) => value !== '')
        .map(([attribute, value]) => ({
            attribute,
            value,
        }))
}

export function configureProductWooStoreApi(nonce: string): void {
    if (nonce === '') {
        return
    }

    wooStoreApi.setNonce(nonce)
}

/**
 * The per-request half of the product page, fetched once per page view: the
 * delivery schedule as it stands now and a nonce that has not expired. The
 * nonce is applied here so every caller benefits; the schedule is returned for
 * DeliveryPlugin to re-render the calendar with.
 *
 * Resolves to null when the request fails, leaving the page on the values it
 * was rendered with -- stale on a cached copy, but the server rejects a stale
 * slot and the client retries an expired nonce, so neither is silently wrong.
 */
export function fetchDeliveryContext(): Promise<DeliveryContext | null> {
    deliveryContextRequest ??= requestDeliveryContext()

    return deliveryContextRequest
}

async function requestDeliveryContext(): Promise<DeliveryContext | null> {
    try {
        const response = await fetch(DELIVERY_CONTEXT_ENDPOINT, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        })

        if (!response.ok) {
            return null
        }

        const payload = await response.json() as Partial<DeliveryContext>

        if (typeof payload?.storeApiNonce === 'string') {
            configureProductWooStoreApi(payload.storeApiNonce)
        }

        if (!payload?.schedule) {
            return null
        }

        return {
            schedule: payload.schedule,
            storeApiNonce: payload.storeApiNonce ?? '',
        }
    } catch {
        return null
    }
}

export async function addProductToCart(payload: CartPayload): Promise<CartResponse> {
    const response = await wooStoreApi.cartItems.add({
        id: payload.variationId || payload.productId,
        quantity: payload.quantity,
        variation: normalizeVariation(payload.attributes),
        deliveryDate: payload.deliveryDate,
        deliveryTime: payload.deliveryTime,
        deliveryLocation: payload.deliveryLocation,
        deliveryType: payload.deliveryType,
        deceasedFullName: payload.deceasedFullName,
        cardMessage: payload.cardMessage,
        additionIds: payload.additionIds,
    })

    return {
        status: 'ok',
        cartCount: response.items_count || response.items.reduce((total, item) => total + item.quantity, 0),
        cartUrl: '/cart/',
    }
}