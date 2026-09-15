/**
 * Swiper Initialization
 * Initialize all Swiper sliders on the page
 */

import Swiper from 'swiper'
import { A11y, Grid, Pagination, Navigation, Thumbs } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/grid'
import 'swiper/css/pagination'
import 'swiper/css/navigation'

function initEventsSwipers(): void {
    const swipers = document.querySelectorAll<HTMLElement>('.events-swiper')

    swipers.forEach((swiperEl) => {
        new Swiper(swiperEl, {
            modules: [Pagination, Navigation],
            spaceBetween: 12,
            slidesPerView: 1.3,
            slidesPerGroup: 1,
            breakpoints: {
                640: {
                    slidesPerView: 2,
                    slidesPerGroup: 2,
                },
                1024: {
                    slidesPerView: 4,
                    slidesPerGroup: 4,
                },
            },
            pagination: {
                el: swiperEl.querySelector('.swiper-pagination') as HTMLElement,
                clickable: true,
            },
            navigation: {
                prevEl: `#${swiperEl.id}-prev`,
                nextEl: `#${swiperEl.id}-next`,
            },
        })
    })
}

function initProductGallery(): void {
    const swipers = document.querySelectorAll<HTMLElement>('.product-gallery-swiper')

    swipers.forEach((swiperEl) => {
        const galleryEl = swiperEl.closest<HTMLElement>('.product-gallery')
        const prevEl = galleryEl?.querySelector<HTMLElement>('.product-gallery-prev')
        const nextEl = galleryEl?.querySelector<HTMLElement>('.product-gallery-next')
        const thumbsEl = galleryEl?.querySelector<HTMLElement>('.product-gallery-thumbs')
        const thumbsSwiper = thumbsEl
            ? new Swiper(thumbsEl, {
                spaceBetween: 12,
                slidesPerView: 5.2,
                watchSlidesProgress: true,
                slideToClickedSlide: true,
                breakpoints: {
                    1024: {
                        slidesPerView: 6.2,
                    },
                },
            })
            : undefined

        new Swiper(swiperEl, {
            modules: [Navigation, Thumbs],
            spaceBetween: 16,
            slidesPerView: 1,
            navigation: {
                nextEl,
                prevEl,
            },
            thumbs: thumbsSwiper
                ? {
                    swiper: thumbsSwiper,
                }
                : undefined,
        })
    })
}

function initPhotogalleries(): void {
    const swipers = document.querySelectorAll<HTMLElement>('.photogallery-swiper')

    swipers.forEach((swiperEl) => {
        const galleryEl = swiperEl.closest<HTMLElement>('.fa-section-block--photogallery-1')
        const prevEl = galleryEl?.querySelector<HTMLElement>('[data-photogallery-prev]')
        const nextEl = galleryEl?.querySelector<HTMLElement>('[data-photogallery-next]')
        const paginationEl = galleryEl?.querySelector<HTMLElement>('.photogallery-pagination')

        new Swiper(swiperEl, {
            modules: [A11y, Grid, Navigation, Pagination],
            spaceBetween: 8,
            slidesPerView: 2.2,
            slidesPerGroup: 1,
            grid: {
                rows: 2,
                fill: 'column',
            },
            navigation: {
                nextEl,
                prevEl,
            },
            pagination: {
                el: paginationEl,
                clickable: true,
                dynamicBullets: true,
                dynamicMainBullets: 3,
            },
            a11y: {
                prevSlideMessage: 'Poprzednie zdjęcie',
                nextSlideMessage: 'Następne zdjęcie',
                paginationBulletMessage: 'Przejdź do zdjęcia {{index}}',
            },
            breakpoints: {
                640: {
                    slidesPerView: 3.2,
                },
                768: {
                    slidesPerView: 3.2,
                    grid: {
                        rows: 1,
                    },
                },
                1024: {
                    spaceBetween: 16,
                    slidesPerView: 5,
                    grid: {
                        rows: 1,
                    },
                },
            },
        })
    })
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initEventsSwipers()
        initProductGallery()
        initPhotogalleries()
    })
} else {
    initEventsSwipers()
    initProductGallery()
    initPhotogalleries()
}
