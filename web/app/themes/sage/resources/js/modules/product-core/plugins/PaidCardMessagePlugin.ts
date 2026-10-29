import type { ProductPlugin, ProductPurchaseAddition, ProductPurchaseStore } from '../types'

const PAID_CARD_MESSAGE_SELECTOR = '#card_message_payed'
const PAID_CARD_MESSAGE_ADDITION_ID = -20

/**
 * Set in Products -> Funeral settings. Shown here only: the cart charges it
 * server-side (App\Shop\FuneralCartItem), so it stays out of the payload.
 */
function ribbonPrice(): number {
    const price = Number(document.querySelector<HTMLElement>('[data-ribbon-price]')?.dataset.ribbonPrice ?? '0')

    return Number.isFinite(price) && price > 0 ? price : 0
}

export class PaidCardMessagePlugin implements ProductPlugin {
    pluginName = 'paidCardMessage'
    #input: HTMLInputElement | null = null
    #store: ProductPurchaseStore | null = null

    init(store: ProductPurchaseStore): void {
        this.#input = document.querySelector<HTMLInputElement>(PAID_CARD_MESSAGE_SELECTOR)

        if (!this.#input) {
            return
        }

        this.#store = store
        this.syncState()
        this.#input.addEventListener('input', this.syncState)
    }

    destroy(): void {
        this.#input?.removeEventListener('input', this.syncState)
        this.#input = null
        this.#store = null
    }

    private syncState = (): void => {
        if (!this.#input || !this.#store) {
            return
        }

        const value = this.#input.value
        const price = ribbonPrice()
        const hasPaidMessage = value.trim() !== '' && price > 0

        this.#store.setCardMessage(value)

        const paidCardMessageAddition: ProductPurchaseAddition = {
            id: PAID_CARD_MESSAGE_ADDITION_ID,
            name: 'Ribbon message',
            price,
            includeInPayload: false,
        }

        this.#store.setAddition(paidCardMessageAddition, hasPaidMessage)
    }
}