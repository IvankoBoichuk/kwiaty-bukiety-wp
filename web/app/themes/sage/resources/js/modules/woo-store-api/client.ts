import type {
    StoreApiClientOptions,
    StoreApiErrorResponse,
    StoreApiMethod,
    StoreApiQuery,
    StoreApiRequestOptions,
} from './types'

export class WooStoreApiError<TData = unknown> extends Error {
    readonly code?: string
    readonly status: number
    readonly data?: TData

    constructor(message: string, options: { code?: string, status: number, data?: TData }) {
        super(message)
        this.name = 'WooStoreApiError'
        this.code = options.code
        this.status = options.status
        this.data = options.data
    }
}

export class WooStoreApiClient {
    protected readonly fetchImpl: typeof fetch
    protected readonly baseUrl: string
    protected nonce: string
    protected cartToken: string

    constructor(options: StoreApiClientOptions = {}) {
        const defaultFetchOwner = typeof window !== 'undefined' ? window : globalThis
        this.fetchImpl = options.fetch || defaultFetchOwner.fetch.bind(defaultFetchOwner)
        this.baseUrl = (options.baseUrl || '/wp-json/wc/store/v1').replace(/\/+$/, '')
        this.nonce = options.nonce || ''
        this.cartToken = options.cartToken || ''
    }

    getNonce(): string {
        return this.nonce
    }

    setNonce(nonce: string): void {
        this.nonce = nonce
    }

    getCartToken(): string {
        return this.cartToken
    }

    setCartToken(cartToken: string): void {
        this.cartToken = cartToken
    }

    async get<TResponse>(path: string, options: StoreApiRequestOptions = {}): Promise<TResponse> {
        return this.request<TResponse>('GET', path, undefined, options)
    }

    async post<TResponse, TBody = undefined>(path: string, body?: TBody, options: StoreApiRequestOptions = {}): Promise<TResponse> {
        return this.request<TResponse, TBody>('POST', path, body, options)
    }

    async put<TResponse, TBody = undefined>(path: string, body?: TBody, options: StoreApiRequestOptions = {}): Promise<TResponse> {
        return this.request<TResponse, TBody>('PUT', path, body, options)
    }

    async delete<TResponse, TBody = undefined>(path: string, body?: TBody, options: StoreApiRequestOptions = {}): Promise<TResponse> {
        return this.request<TResponse, TBody>('DELETE', path, body, options)
    }

    protected async request<TResponse, TBody = undefined>(
        method: StoreApiMethod,
        path: string,
        body?: TBody,
        options: StoreApiRequestOptions = {},
    ): Promise<TResponse> {
        const sentNonce = this.nonce
        let attempt = await this.send<TResponse, TBody>(method, path, body, options)

        // The nonce the page was rendered with expires after 24 hours, and the
        // product pages are page cached for a week, so a first click on a
        // cached page used to fail with "Nonce is invalid." and only work on
        // the second. The rejection itself carries a usable nonce, which
        // captureResponseTokens() has already stored, so the retry below is
        // what the customer's second click used to be. Retrying cannot double
        // up: the nonce is checked before the route touches the cart, so the
        // rejected attempt changed nothing.
        if (this.shouldRetryWithFreshNonce(attempt, sentNonce)) {
            attempt = await this.send<TResponse, TBody>(method, path, body, options)
        }

        if (!attempt.response.ok) {
            const errorPayload = (attempt.payload || {}) as StoreApiErrorResponse

            throw new WooStoreApiError(errorPayload.message || 'Woo Store API request failed.', {
                code: errorPayload.code,
                status: this.resolveStatus(attempt.response.status, errorPayload.data),
                data: errorPayload.data,
            })
        }

        return attempt.payload as TResponse
    }

    protected async send<TResponse, TBody = undefined>(
        method: StoreApiMethod,
        path: string,
        body?: TBody,
        options: StoreApiRequestOptions = {},
    ): Promise<{ response: Response, payload?: StoreApiErrorResponse | TResponse }> {
        const response = await this.fetchImpl(this.buildUrl(path, options.query), {
            method,
            credentials: 'same-origin',
            headers: this.buildHeaders(options.headers),
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: options.signal,
        })

        this.captureResponseTokens(response)

        return {
            response,
            payload: await this.parseJson<StoreApiErrorResponse | TResponse>(response),
        }
    }

    protected shouldRetryWithFreshNonce<TResponse>(
        attempt: { response: Response, payload?: StoreApiErrorResponse | TResponse },
        sentNonce: string,
    ): boolean {
        if (attempt.response.ok || this.nonce === '' || this.nonce === sentNonce) {
            return false
        }

        const code = (attempt.payload as StoreApiErrorResponse | undefined)?.code

        return code === 'woocommerce_rest_invalid_nonce'
            || code === 'woocommerce_rest_missing_nonce'
    }

    protected buildUrl(path: string, query?: StoreApiQuery): string {
        const url = new URL(`${this.baseUrl}/${path.replace(/^\/+/, '')}`, window.location.origin)

        if (!query) {
            return url.toString()
        }

        Object.entries(query).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((entry) => {
                    if (entry !== null && entry !== undefined) {
                        url.searchParams.append(key, String(entry))
                    }
                })

                return
            }

            if (value !== null && value !== undefined) {
                url.searchParams.set(key, String(value))
            }
        })

        return url.toString()
    }

    protected buildHeaders(headers?: HeadersInit): Headers {
        const result = new Headers(headers)

        if (!result.has('Accept')) {
            result.set('Accept', 'application/json')
        }

        if (!result.has('Content-Type')) {
            result.set('Content-Type', 'application/json')
        }

        if (this.nonce !== '' && !result.has('Nonce')) {
            result.set('Nonce', this.nonce)
        }

        if (this.cartToken !== '' && !result.has('Cart-Token')) {
            result.set('Cart-Token', this.cartToken)
        }

        return result
    }

    protected captureResponseTokens(response: Response): void {
        const nextNonce = response.headers.get('Nonce')
        const nextCartToken = response.headers.get('Cart-Token')

        if (nextNonce) {
            this.nonce = nextNonce
        }

        if (nextCartToken) {
            this.cartToken = nextCartToken
        }
    }

    protected async parseJson<TPayload>(response: Response): Promise<TPayload | undefined> {
        const contentType = response.headers.get('Content-Type') || ''

        if (!contentType.toLowerCase().includes('application/json')) {
            return undefined
        }

        return await response.json() as TPayload
    }

    protected resolveStatus(status: number, data: unknown): number {
        if (!data || typeof data !== 'object') {
            return status
        }

        const nestedStatus = 'status' in data ? data.status : undefined

        return typeof nestedStatus === 'number' ? nestedStatus : status
    }
}