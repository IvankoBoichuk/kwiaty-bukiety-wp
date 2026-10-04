import { animateCSS } from './animate'

const CART_COUNT_ENDPOINT = '/wp-json/sage/v1/cart-count'

/**
 * The badge sits in the header and the mobile bottom bar, both of which are
 * part of every page and therefore part of the page cache. The templates
 * render 0 and the real count is fetched from the REST route, which is not
 * cached -- see App\Api\CartCount.
 */
const CART_COUNTER_SELECTOR = '.counter-for-cart'

/**
 * A visitor who never put anything in a cart carries none of these cookies,
 * and the 0 the page was rendered with is already right for them -- which is
 * most of the traffic a cached page serves, so they are not asked at all.
 * Logged-in customers keep a cart between sessions and are always asked.
 */
const CART_COOKIES = [
    'wp_woocommerce_session_',
    'woocommerce_items_in_cart',
    'woocommerce_cart_hash',
    'wordpress_logged_in_',
]

export function setCartCount(count: number): void {
    document.querySelectorAll<HTMLElement>(CART_COUNTER_SELECTOR).forEach((counter) => {
        const badge = counter.querySelector<HTMLElement>('[data-count]')

        if (!badge || badge.dataset.count === String(count)) {
            return
        }

        badge.dataset.count = String(count)
    })
}

export function announceCartCount(count: number): void {
    setCartCount(count)

    document.querySelectorAll<HTMLElement>(CART_COUNTER_SELECTOR).forEach((counter) => {
        animateCSS(counter, 'heartBeat')
    })
}

export async function initCartCount(): Promise<void> {
    // A page restored from the back/forward cache is not re-rendered and this
    // module is not re-run, so the badge would keep the count it was left
    // with -- which is the stale one when the customer went back to a page
    // they had opened before adding anything.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            void refreshCartCount()
        }
    })

    await refreshCartCount()
}

async function refreshCartCount(): Promise<void> {
    // No cart cookie means an empty cart, which on a first load matches the 0
    // the page carries and on a restored page clears a count the customer has
    // since checked out or emptied elsewhere.
    if (!hasCart()) {
        setCartCount(0)

        return
    }

    const count = await fetchCartCount()

    if (count !== null) {
        setCartCount(count)
    }
}

function hasCart(): boolean {
    return CART_COOKIES.some((cookie) => document.cookie.includes(cookie))
}

async function fetchCartCount(): Promise<number | null> {
    try {
        const response = await fetch(CART_COUNT_ENDPOINT, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        })

        if (!response.ok) {
            return null
        }

        const payload = await response.json() as { count?: unknown }

        return typeof payload?.count === 'number' ? payload.count : null
    } catch {
        return null
    }
}
