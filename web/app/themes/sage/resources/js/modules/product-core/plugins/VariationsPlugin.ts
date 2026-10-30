import type { ProductVariation } from '../../../types/ProductVariations'

import type { ProductPlugin, ProductPurchaseStore } from '../types'

const ACTIVE_CLASS = 'active'

export class VariationsPlugin implements ProductPlugin {
    pluginName = 'variations'
    #buttonsWrapper: HTMLElement | null = null
    #buttons: HTMLButtonElement[] = []
    #variations: ProductVariation[] = []
    #store: ProductPurchaseStore | null = null
    #selectedAttributes = new Map<string, string>()

    init(store: ProductPurchaseStore): void {
        this.#store = store

        if (!window.productVariations || window.productVariations.length === 0) {
            console.error('VariationsPlugin: No product variations found on the page.')
            return
        }

        this.#variations = window.productVariations
        this.#buttonsWrapper = document.getElementById('product-variations')

        if (!this.#buttonsWrapper) {
            console.error('VariationsPlugin: No buttons wrapper found on the page.')
            return
        }

        this.#buttons = Array.from(
            this.#buttonsWrapper.querySelectorAll<HTMLButtonElement>('[data-attribute-name][data-attribute-value]'),
        )

        if (this.#buttons.length === 0) {
            console.error('VariationsPlugin: No variation buttons found on the page.')
            return
        }

        this.#buttonsWrapper.addEventListener('click', this.createClickHandler)

        // The server picks the opening size (shop default, else the cheapest) -- menu order
        // puts Deluxe first on most bouquets (KB-114).
        const defaultVariationId = window.product?.defaultVariationId
        const defaultVariation = this.#variations.find((variation) => variation.variation_id === defaultVariationId)
            ?? this.#variations[0]

        if (defaultVariation) {
            this.applyVariation(defaultVariation)
        }
    }

    destroy(): void {
        this.#buttonsWrapper?.removeEventListener('click', this.createClickHandler)
        this.#buttonsWrapper = null
        this.#buttons = []
        this.#variations = []
        this.#selectedAttributes.clear()
    }

    private createClickHandler = (event: PointerEvent): void => {
        const button = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-attribute-name][data-attribute-value]')

        if (!button || button.disabled) return

        const attributeName = button.dataset.attributeName
        const attributeValue = button.dataset.attributeValue

        if (!attributeName || !attributeValue) return

        const variation = this.getVariationForSelection(attributeName, attributeValue)

        if (!variation) return

        this.applyVariation(variation)
    }

    private applyVariation(variation: ProductVariation): void {
        this.syncSelectedAttributes(variation)
        this.syncButtonsState()

        if (this.#store) {
            this.#store.variation = variation
        }

        window.dispatchEvent(new CustomEvent<ProductVariation>('product:variation-change', {
            detail: variation,
        }))
    }

    private getVariationForSelection(attributeName: string, attributeValue: string): ProductVariation | undefined {
        const selectedAttributes = new Map(this.#selectedAttributes)
        selectedAttributes.set(attributeName, attributeValue)

        return this.findMatchingVariation(selectedAttributes)
            ?? this.#variations.find((variation) => variation.attributes[attributeName] === attributeValue)
    }

    private findMatchingVariation(selectedAttributes: Map<string, string>): ProductVariation | undefined {
        return this.#variations.find((variation) => {
            for (const [attributeName, attributeValue] of selectedAttributes) {
                if (variation.attributes[attributeName] !== attributeValue) {
                    return false
                }
            }

            return true
        })
    }

    private syncSelectedAttributes(variation: ProductVariation): void {
        this.#selectedAttributes.clear()

        Object.entries(variation.attributes).forEach(([attributeName, attributeValue]) => {
            if (attributeValue !== '') {
                this.#selectedAttributes.set(attributeName, attributeValue)
            }
        })
    }

    private syncButtonsState(): void {
        this.#buttons.forEach((button) => {
            const attributeName = button.dataset.attributeName
            const attributeValue = button.dataset.attributeValue
            const isActive = Boolean(
                attributeName
                && attributeValue
                && this.#selectedAttributes.get(attributeName) === attributeValue,
            )
            const isAvailable = Boolean(
                attributeName
                && attributeValue
                && this.isOptionAvailable(attributeName, attributeValue),
            )

            button.classList.toggle(ACTIVE_CLASS, isActive)
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false')
            button.disabled = !isAvailable
        })
    }

    private isOptionAvailable(attributeName: string, attributeValue: string): boolean {
        const selectedAttributes = new Map(this.#selectedAttributes)
        selectedAttributes.set(attributeName, attributeValue)

        return this.#variations.some((variation) => {
            for (const [selectedAttributeName, selectedAttributeValue] of selectedAttributes) {
                if (variation.attributes[selectedAttributeName] !== selectedAttributeValue) {
                    return false
                }
            }

            return true
        })
    }
}