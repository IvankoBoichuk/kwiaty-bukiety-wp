import { createTranslator, type Strings } from './strings'

type CitySuggestion = {
    city: string
    voivodeship: string
    url: string
}

type ApiResponse = {
    data?: CitySuggestion[]
    count?: number
}

type CitySearchConfig = {
    endpoint?: string
    strings?: Strings
}

/**
 * The field this drives came over from production as raw HTML inside the
 * "Kwiaciarnie w Polsce" content, so the selectors are the ones that markup
 * already carries.
 */
const INPUT_SELECTOR = '.kb-city-search__input'
const LIST_SELECTOR = 'ul.kb-city-suggestions, ul[id^="kb-city-suggestions"]'
const CONFIG_ID = 'kb-city-search-config'

const DEFAULT_ENDPOINT = '/wp-json/sage/v1/cities'
const MIN_QUERY_LENGTH = 2
const DEBOUNCE_MS = 250
const LIMIT = 10

function readConfig(): CitySearchConfig {
    const block = document.getElementById(CONFIG_ID)

    if (!block) {
        return {}
    }

    try {
        const parsed: unknown = JSON.parse(block.textContent || '{}')

        return parsed && typeof parsed === 'object' ? (parsed as CitySearchConfig) : {}
    } catch {
        return {}
    }
}

class CityAutocomplete {
    private readonly input: HTMLInputElement
    private readonly list: HTMLUListElement
    private readonly status: HTMLParagraphElement
    private readonly endpoint: string
    private readonly translate: (key: string, fallback: string) => string

    private suggestions: CitySuggestion[] = []
    private activeIndex = -1
    private lastQuery = ''

    private debounceTimer: number | null = null
    private abortController: AbortController | null = null

    constructor(input: HTMLInputElement, config: CitySearchConfig, index: number) {
        this.input = input
        this.endpoint = config.endpoint || DEFAULT_ENDPOINT
        this.translate = createTranslator(config.strings)
        this.list = this.resolveList(index)
        this.status = this.createStatus()
    }

    public init(): void {
        this.input.setAttribute('role', 'combobox')
        this.input.setAttribute('aria-autocomplete', 'list')
        this.input.setAttribute('aria-expanded', 'false')
        this.input.setAttribute('aria-controls', this.list.id)
        // The browser's own dropdown would sit on top of the listbox.
        this.input.setAttribute('autocomplete', 'off')

        this.list.setAttribute('role', 'listbox')
        this.list.hidden = true

        this.input.addEventListener('input', () => this.onInput())
        this.input.addEventListener('keydown', (event) => this.onKeydown(event))

        // mousedown fires before the input loses focus; without this the blur
        // would close the list and the click would land on nothing.
        this.list.addEventListener('mousedown', (event) => event.preventDefault())
        this.list.addEventListener('click', (event) => this.onListClick(event))

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Node)) {
                return
            }

            if (!this.input.contains(event.target) && !this.list.contains(event.target)) {
                this.close()
            }
        })
    }

    private resolveList(index: number): HTMLUListElement {
        const scope: ParentNode = this.input.parentElement ?? document
        const existing = scope.querySelector(LIST_SELECTOR)

        const list =
            existing instanceof HTMLUListElement
                ? existing
                : document.createElement('ul')

        if (!list.isConnected) {
            list.classList.add('kb-city-suggestions')
            this.input.insertAdjacentElement('afterend', list)
        }

        if (!list.id) {
            list.id = index === 0 ? 'kb-city-suggestions' : `kb-city-suggestions-${index}`
        }

        return list
    }

    /**
     * A listbox that appears and disappears says nothing to a screen reader on
     * its own, so the count rides along in a live region.
     */
    private createStatus(): HTMLParagraphElement {
        const status = document.createElement('p')
        status.className = 'kb-city-search__status'
        status.setAttribute('role', 'status')
        status.setAttribute('aria-live', 'polite')

        this.list.insertAdjacentElement('afterend', status)

        return status
    }

    private onInput(): void {
        const query = this.input.value.trim()

        if (query.length < MIN_QUERY_LENGTH) {
            this.abortController?.abort()
            this.lastQuery = ''
            this.close()

            return
        }

        if (query === this.lastQuery) {
            return
        }

        this.debounce(() => void this.search(query))
    }

    private async search(query: string): Promise<void> {
        this.abortController?.abort()
        this.abortController = new AbortController()

        const url = `${this.endpoint}?search=${encodeURIComponent(query)}&limit=${LIMIT}`

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                signal: this.abortController.signal,
            })

            if (!response.ok) {
                throw new Error(`City search failed: ${response.status}`)
            }

            const payload: ApiResponse = await response.json()

            // The field moved on while the request was in flight.
            if (this.input.value.trim() !== query) {
                return
            }

            this.lastQuery = query
            this.render(Array.isArray(payload.data) ? payload.data : [])
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return
            }

            console.error(error)
        }
    }

    private render(suggestions: CitySuggestion[]): void {
        this.suggestions = suggestions
        this.activeIndex = -1
        this.list.replaceChildren()

        if (suggestions.length === 0) {
            this.list.append(this.createEmptyItem())
            this.open()
            this.announce(this.translate('noResults', 'No results'))

            return
        }

        suggestions.forEach((suggestion, index) => {
            this.list.append(this.createOption(suggestion, index))
        })

        this.open()
        this.announce(
            this.translate('suggestions', 'Suggestions: %d').replace(
                '%d',
                String(suggestions.length),
            ),
        )
    }

    private createOption(suggestion: CitySuggestion, index: number): HTMLLIElement {
        const option = document.createElement('li')
        option.id = `${this.list.id}-option-${index}`
        option.className = 'kb-city-suggestion'
        option.dataset.index = String(index)
        option.setAttribute('role', 'option')
        option.setAttribute('aria-selected', 'false')

        const city = document.createElement('span')
        city.className = 'kb-city-suggestion__city'
        city.textContent = suggestion.city

        option.append(city)

        if (suggestion.voivodeship) {
            const voivodeship = document.createElement('span')
            voivodeship.className = 'kb-city-suggestion__voivodeship'
            voivodeship.textContent = `(${suggestion.voivodeship})`

            option.append(voivodeship)
        }

        return option
    }

    private createEmptyItem(): HTMLLIElement {
        const empty = document.createElement('li')
        empty.className = 'kb-city-suggestions__empty'
        // Not an option: there is nothing here to choose, and the live region
        // has already said so.
        empty.setAttribute('role', 'presentation')
        empty.textContent = this.translate('noResults', 'No results')

        return empty
    }

    private onKeydown(event: KeyboardEvent): void {
        switch (event.key) {
            case 'ArrowDown': {
                event.preventDefault()

                // Re-opens a list that Escape or a click outside closed,
                // without making the visitor retype the query.
                const query = this.input.value.trim()

                if (this.list.hidden && query.length >= MIN_QUERY_LENGTH) {
                    this.lastQuery = ''
                    void this.search(query)
                    break
                }

                this.move(1)
                break
            }
            case 'ArrowUp':
                event.preventDefault()
                this.move(-1)
                break
            case 'Enter':
                if (this.activeIndex >= 0) {
                    event.preventDefault()
                    this.go(this.activeIndex)
                }
                break
            case 'Escape':
                if (!this.list.hidden) {
                    event.preventDefault()
                }
                this.close()
                break
            case 'Tab':
                this.close()
                break
            default:
                break
        }
    }

    private move(step: number): void {
        if (this.suggestions.length === 0) {
            return
        }

        if (this.list.hidden) {
            this.open()
        }

        const count = this.suggestions.length
        const next = this.activeIndex < 0 && step < 0
            ? count - 1
            : (this.activeIndex + step + count) % count

        this.setActive(next)
    }

    private setActive(index: number): void {
        this.options().forEach((option, position) => {
            const active = position === index
            option.setAttribute('aria-selected', active ? 'true' : 'false')
            option.classList.toggle('is-active', active)

            if (active) {
                this.input.setAttribute('aria-activedescendant', option.id)
                option.scrollIntoView({ block: 'nearest' })
            }
        })

        this.activeIndex = index
    }

    private onListClick(event: MouseEvent): void {
        const target = event.target instanceof Element
            ? event.target.closest<HTMLLIElement>('[role="option"]')
            : null

        if (!target?.dataset.index) {
            return
        }

        this.go(Number(target.dataset.index))
    }

    /**
     * The suggestions are plain options rather than links: an option with an
     * interactive descendant is not a combobox any more, and the whole point
     * here is that the keyboard works. Activation navigates instead.
     */
    private go(index: number): void {
        const suggestion = this.suggestions[index]

        if (!suggestion?.url) {
            return
        }

        this.close()
        window.location.assign(suggestion.url)
    }

    private options(): HTMLLIElement[] {
        return Array.from(this.list.querySelectorAll<HTMLLIElement>('[role="option"]'))
    }

    private open(): void {
        this.list.hidden = false
        this.input.setAttribute('aria-expanded', 'true')
    }

    private close(): void {
        this.list.hidden = true
        this.list.replaceChildren()
        this.suggestions = []
        this.activeIndex = -1
        this.input.setAttribute('aria-expanded', 'false')
        this.input.removeAttribute('aria-activedescendant')
        this.announce('')
    }

    private announce(message: string): void {
        this.status.textContent = message
    }

    private debounce(callback: () => void): void {
        if (this.debounceTimer) {
            window.clearTimeout(this.debounceTimer)
        }

        this.debounceTimer = window.setTimeout(callback, DEBOUNCE_MS)
    }
}

export function initCityAutocomplete(): void {
    const inputs = document.querySelectorAll<HTMLInputElement>(INPUT_SELECTOR)

    if (inputs.length === 0) {
        return
    }

    const config = readConfig()

    inputs.forEach((input, index) => {
        new CityAutocomplete(input, config, index).init()
    })
}
