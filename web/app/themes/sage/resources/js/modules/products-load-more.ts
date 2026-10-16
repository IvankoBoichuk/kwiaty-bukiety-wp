function removeLoadMoreControl(link: HTMLAnchorElement): void {
    link.closest('[data-products-load-more]')?.remove()
}

function getLinkLabelElement(link: HTMLAnchorElement): HTMLSpanElement | null {
    const label = link.querySelector('span')

    return label instanceof HTMLSpanElement ? label : null
}

function setLinkState(link: HTMLAnchorElement, loading: boolean): void {
    if (loading) {
        link.setAttribute('aria-disabled', 'true')
    } else {
        link.removeAttribute('aria-disabled')
    }

    const label = getLinkLabelElement(link)
    if (!label) {
        return
    }

    label.textContent = loading
        ? link.dataset.loadingLabel ?? link.dataset.defaultLabel ?? ''
        : link.dataset.defaultLabel ?? label.textContent ?? ''
}

function parseProductsDocument(html: string): {
    items: HTMLLIElement[]
    nextUrl: string
} | null {
    const parser = new DOMParser()
    const document = parser.parseFromString(html, 'text/html')
    const list = document.querySelector('[data-products-list]')

    if (!(list instanceof HTMLUListElement)) {
        return null
    }

    const items = Array.from(list.children).filter(
        (item): item is HTMLLIElement => item instanceof HTMLLIElement,
    )

    const nextLink = document.querySelector<HTMLAnchorElement>(
        '[data-products-load-more-link]',
    )

    return {
        items,
        nextUrl: nextLink?.getAttribute('href') ?? '',
    }
}

async function loadMoreProducts(link: HTMLAnchorElement): Promise<void> {
    const list = document.querySelector('[data-products-list]')
    if (!(list instanceof HTMLUListElement)) {
        removeLoadMoreControl(link)
        return
    }

    const nextUrl = link.getAttribute('href')
    if (!nextUrl) {
        removeLoadMoreControl(link)
        return
    }

    setLinkState(link, true)

    try {
        const response = await fetch(nextUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            throw new Error(`Failed to load products: ${response.status}`)
        }

        const payload = parseProductsDocument(await response.text())

        if (!payload || payload.items.length === 0) {
            removeLoadMoreControl(link)
            return
        }

        payload.items.forEach((item) => {
            list.append(item)
        })

        if (payload.nextUrl === '') {
            removeLoadMoreControl(link)
            return
        }

        link.setAttribute('href', payload.nextUrl)
        setLinkState(link, false)
    } catch (error) {
        console.error(error)
        setLinkState(link, false)
    }
}

function initProductsLoadMore(): void {
    const links = document.querySelectorAll<HTMLAnchorElement>(
        '[data-products-load-more-link]',
    )

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            // A modified or middle click means "open the page": let the href do it.
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return
            }

            event.preventDefault()

            if (link.getAttribute('aria-disabled') === 'true') {
                return
            }

            void loadMoreProducts(link)
        })
    })
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductsLoadMore)
} else {
    initProductsLoadMore()
}
