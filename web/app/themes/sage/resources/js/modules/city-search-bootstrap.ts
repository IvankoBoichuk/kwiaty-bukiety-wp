/**
 * Loads the city autocomplete only where the field is.
 *
 * The production snippet printed its copy of this script into wp_footer on
 * every page of the site, for one input that exists on one. The theme ships a
 * single bundle, so the equivalent here is a dynamic import: Vite emits the
 * module as its own chunk and nothing fetches it unless the hub's search field
 * is in the document.
 */
const INPUT_SELECTOR = '.kb-city-search__input'

export async function initCitySearch(): Promise<void> {
    if (!document.querySelector(INPUT_SELECTOR)) {
        return
    }

    try {
        const { initCityAutocomplete } = await import('./city-autocomplete')

        initCityAutocomplete()
    } catch (error) {
        console.error(error)
    }
}
