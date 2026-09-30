type CityRecord = {
    id: number
    name: string
    link: string
}

function normalizeCities(payload: unknown): CityRecord[] {
    if (Array.isArray(payload)) {
        return payload as CityRecord[]
    }

    if (payload && typeof payload === 'object') {
        return Object.values(payload) as CityRecord[]
    }

    return []
}

const WIDTH_PATTERN = [
    'flex-[1_1_28%]',
    'flex-[1_1_35%]',
    'flex-[1_1_25%]',
    'flex-[1_1_30%]',
    'flex-[1_1_32%]',
    'flex-[1_1_27%]',
    'flex-[1_1_33%]',
    'flex-[1_1_29%]',
    'flex-[1_1_26%]',
]

function createCityItem(city: CityRecord, index: number): HTMLLIElement {
    const item = document.createElement('li')
    item.className = WIDTH_PATTERN[index % WIDTH_PATTERN.length]

    const link = document.createElement('a')
    link.href = city.link
    link.className = 'bg-green-easy text-h4 flex h-full items-center justify-center rounded-2xl px-4 py-4.5 text-center text-white lg:px-20'
    link.textContent = city.name

    item.append(link)

    return item
}

function removeLoadMoreButton(button: HTMLButtonElement): void {
    button.closest('[data-cities-load-more]')?.remove()
}

async function loadMoreCities(button: HTMLButtonElement): Promise<void> {
    const list = button.closest('[data-cities-list]')
    if (!(list instanceof HTMLUListElement)) {
        return
    }

    const taxonomy = button.dataset.taxonomy
    if (!taxonomy) {
        removeLoadMoreButton(button)
        return
    }

    const initialCount = Number(button.dataset.initialCount ?? '0')
    const renderedCount = Number(button.dataset.renderedCount ?? '0')
    const totalCount = Number(button.dataset.totalCount ?? '0')
    const number = Number(button.dataset.number ?? '6')

    button.disabled = true

    try {
        // Only these keys are sent: the endpoint accepts far more, and the
        // section should not hand a visitor an arbitrary term query to edit.
        const params = new URLSearchParams({
            taxonomy,
            number: String(number),
            orderby: button.dataset.orderBy || 'name',
            order: button.dataset.order || 'asc',
            hide_empty: button.dataset.hideEmpty === '1' ? '1' : '0',
        })

        if (button.dataset.nameLike) {
            params.set('name__like', button.dataset.nameLike)
        }

        // Paging by offset would re-serve the terms the section pinned, which
        // are not necessarily the first page of this query. Exclude what is
        // already on screen instead.
        const excluded = (button.dataset.exclude || '')
            .split(',')
            .map((id) => id.trim())
            .filter(Boolean)

        excluded.forEach((id) => params.append('exclude[]', id))

        const response = await fetch(`/wp-json/sage/v1/categories?${params.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        })

        if (!response.ok) {
            throw new Error(`Failed to load cities: ${response.status}`)
        }

        const cities = normalizeCities(await response.json())

        if (cities.length === 0) {
            removeLoadMoreButton(button)
            return
        }

        cities.forEach((city, index) => {
            const item = createCityItem(city, initialCount + renderedCount + index)
            list.insertBefore(item, button.closest('[data-cities-load-more]'))
        })

        const nextRenderedCount = renderedCount + cities.length

        button.dataset.renderedCount = String(nextRenderedCount)
        button.dataset.exclude = [...excluded, ...cities.map((city) => String(city.id))].join(',')

        if (nextRenderedCount >= totalCount || cities.length < number) {
            removeLoadMoreButton(button)
            return
        }

        button.disabled = false
    } catch (error) {
        console.error(error)
        button.disabled = false
    }
}

export function initCitiesLoadMore(): void {
    const buttons = document.querySelectorAll<HTMLButtonElement>('[data-cities-button]')

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            void loadMoreCities(button)
        })
    })
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCitiesLoadMore)
} else {
    initCitiesLoadMore()
}