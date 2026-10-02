/**
 * Front-end copy, translated on the server.
 *
 * These strings used to go through @wordpress/i18n, which the Vite WordPress
 * plugin externalizes to the global `wp.i18n`. That global is not free: it put
 * hooks.js and i18n.js in the critical request path as render-blocking footer
 * scripts, and because the bundle dereferences `wp.i18n.__` while the module is
 * evaluating, neither could be dequeued or deferred. Two round trips for a
 * couple of dozen validation messages was the wrong trade.
 *
 * They now ride along in the config objects the page already prints, translated
 * by PHP against the theme's own catalogue. The English source stays here as
 * the fallback, so a key that is missing -- an old cached config, a page that
 * prints a partial payload -- degrades to readable copy instead of `undefined`.
 */
export type Strings = Record<string, string>

export function createTranslator(strings?: Strings) {
    return (key: string, fallback: string): string => {
        const value = strings?.[key]

        return typeof value === 'string' && value !== '' ? value : fallback
    }
}
