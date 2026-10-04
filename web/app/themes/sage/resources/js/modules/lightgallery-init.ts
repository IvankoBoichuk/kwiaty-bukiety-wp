/**
 * LightGallery Initialization with Lazy Loading
 * Load and initialize lightbox gallery only when needed
 */

let lightGalleryLoaded = false
const galleryInstances = new Map<HTMLElement, unknown>()

async function loadLightGallery(gallery: HTMLElement): Promise<void> {
    if (galleryInstances.has(gallery)) {
        return
    }

    const [{ default: lightGallery }, { default: lgZoom }, { default: lgThumbnail }] = await Promise.all([
        import('lightgallery'),
        import('lightgallery/plugins/zoom'),
        import('lightgallery/plugins/thumbnail'),
    ])

    if (!lightGalleryLoaded) {
        await import('../../css/lightgallery.css')
        lightGalleryLoaded = true
    }

    const instance = lightGallery(gallery, {
        selector: '.lightgallery-item',
        plugins: [lgZoom, lgThumbnail],
        speed: 500,
        download: false,
        counter: true,
        closable: true,
        showCloseIcon: true,
        closeOnTap: true,
        escKey: true,
        keyPress: true,
        zoom: true,
        thumbnail: true,
        animateThumb: true,
        // lightGallery merges `strings` shallowly, so a partial object would
        // blank the keys it leaves out -- all seven have to be here. The three
        // visible controls take their label from the markup, which is where the
        // translated copy already lives; the rest keep the English defaults.
        strings: {
            closeGallery: gallery.dataset.lgCloseLabel || 'Close gallery',
            previousSlide: gallery.dataset.lgPrevLabel || 'Previous slide',
            nextSlide: gallery.dataset.lgNextLabel || 'Next slide',
            toggleMaximize: 'Toggle maximize',
            download: 'Download',
            playVideo: 'Play video',
            mediaLoadingFailed: 'Oops... Failed to load content...',
        },
    })

    galleryInstances.set(gallery, instance)
}

export function initLightGallery(): void {
    const galleries = document.querySelectorAll<HTMLElement>('.product-gallery-swiper')

    galleries.forEach((gallery) => {
        const links = gallery.querySelectorAll<HTMLAnchorElement>('.lightgallery-item')

        links.forEach((link) => {
            link.addEventListener('click', async (event) => {
                event.preventDefault()

                if (!galleryInstances.has(gallery)) {
                    await loadLightGallery(gallery)
                }

                const instance = galleryInstances.get(gallery) as { openGallery: (index: number) => void } | undefined
                if (instance) {
                    const index = Array.from(links).indexOf(link)
                    instance.openGallery(index)
                }
            })
        })
    })
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLightGallery)
} else {
    initLightGallery()
}