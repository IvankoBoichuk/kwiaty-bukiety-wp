@php
    use Frontenda\Blocks\SectionContext;

    /** @var SectionContext $context */
    $media = $context->media();
    $rawMediaAttributes = $media?->attributes()['media'] ?? null;
    $mediaAttributes = is_array($rawMediaAttributes) ? $rawMediaAttributes : [];
    $galleryImages = is_array($mediaAttributes['gallery']['images'] ?? null)
        ? $mediaAttributes['gallery']['images']
        : [];
    $attachmentIds = array_values(array_filter(
        array_map(
            static fn ($image): int => absint(is_array($image) ? ($image['id'] ?? 0) : 0),
            $galleryImages,
        ),
        static fn (int $attachmentId): bool => wp_attachment_is_image($attachmentId),
    ));
@endphp

<section {!! $context->wrapperAttributes() !!}>
    @if ($attachmentIds !== [])
        <div
            class="swiper photogallery-swiper"
            data-swiper-prev-label="{{ esc_attr__('Previous image', 'sage-front') }}"
            data-swiper-next-label="{{ esc_attr__('Next image', 'sage-front') }}"
            {{-- %s is swapped for Swiper's own {{index}} token in swiper-init.ts;
                 spelling it here would end this Blade echo early. --}}
            data-swiper-bullet-label="{{ esc_attr__('Go to image %s', 'sage-front') }}"
        >
            <div class="swiper-wrapper">
                @foreach ($attachmentIds as $attachmentId)
                    <div class="swiper-slide">
                        {!! wp_get_attachment_image($attachmentId, 'large', false, [
                            'class' => 'aspect-17/19 w-full object-cover md:aspect-3/4',
                                'alt' => get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: __('Bouquet', 'sage-front'),
                            'loading' => 'lazy',
                            'decoding' => 'async',
                            'sizes' => '(min-width: 1024px) 22vw, (min-width: 640px) 30vw, 44vw',
                        ]) !!}
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-5 flex items-center justify-center gap-4">
            <button
                type="button"
                data-photogallery-prev
                class="text-green-easy hidden size-10 items-center justify-center rounded-xl border-2 border-[#E0E0D7] transition-colors hover:bg-white [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-40 lg:flex"
                    aria-label="{{ esc_attr__('Previous', 'sage-front') }}"
            >
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div class="swiper-pagination photogallery-pagination" aria-label="{{ esc_attr__('Gallery navigation', 'sage-front') }}"></div>

            <button
                type="button"
                data-photogallery-next
                class="text-green-easy hidden size-10 items-center justify-center rounded-xl border-2 border-[#E0E0D7] transition-colors hover:bg-white [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-40 lg:flex"
                    aria-label="{{ esc_attr__('Next', 'sage-front') }}"
            >
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </div>
    @endif
</section>
