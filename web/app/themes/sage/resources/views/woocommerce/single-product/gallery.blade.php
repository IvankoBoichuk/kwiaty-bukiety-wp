<div class="product-gallery relative -mx-3 md:-mx-8 lg:col-start-1 lg:row-start-1 lg:mx-0">
  <div class="relative min-w-0">
    <div
      class="swiper product-gallery-swiper"
      data-lg-close-label="{{ esc_attr__('Close gallery', 'sage-front') }}"
      data-lg-prev-label="{{ esc_attr__('Previous image', 'sage-front') }}"
      data-lg-next-label="{{ esc_attr__('Next image', 'sage-front') }}"
    >
      <div class="swiper-wrapper">
        @foreach ($gallery as $image)
          <div class="swiper-slide">
            <a href="{{ esc_url($image['src']) }}" class="lightgallery-item block">
              <div class="relative w-full">
                <img
                  src="{{ esc_url($image['src']) }}"
                  alt="{{ esc_attr($image['alt'] ?: $title) }}"
                  @if (($image['width'] ?? 0) > 0) width="{{ (int) $image['width'] }}" @endif
                  @if (($image['height'] ?? 0) > 0) height="{{ (int) $image['height'] }}" @endif
                  @if (!empty($image['srcset'])) srcset="{{ esc_attr($image['srcset']) }}" @endif
                  @if (!empty($image['sizes'])) sizes="{{ esc_attr($image['sizes']) }}" @endif
                  class="aspect-394/274 h-full w-full object-cover lg:aspect-814/671"
                />
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </div>

    <button
      type="button"
      class="product-gallery-prev absolute top-1/2 left-3 z-10 -translate-y-1/2"
      aria-label="{{ esc_attr__('Previous image', 'sage-front') }}"
    >
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M14 0.5C6.54416 0.5 0.5 6.54416 0.5 14C0.5 21.4558 6.54416 27.5 14 27.5C21.4558 27.5 27.5 21.4558 27.5 14C27.5 6.54416 21.4558 0.5 14 0.5Z" fill="white" fill-opacity="0.25" />
        <path d="M14 0.5C6.54416 0.5 0.5 6.54416 0.5 14C0.5 21.4558 6.54416 27.5 14 27.5C21.4558 27.5 27.5 21.4558 27.5 14C27.5 6.54416 21.4558 0.5 14 0.5Z" stroke="#FCF9F6" />
        <path d="M16.5 19L11.5 14L16.5 9" stroke="#FCF9F6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>
    <button
      type="button"
      class="product-gallery-next absolute top-1/2 right-3 z-10 -translate-y-1/2"
      aria-label="{{ esc_attr__('Next image', 'sage-front') }}"
    >
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="0.5" y="0.5" width="27" height="27" rx="13.5" fill="white" fill-opacity="0.25" />
        <rect x="0.5" y="0.5" width="27" height="27" rx="13.5" stroke="#FCF9F6" />
        <path d="M11.5 19L16.5 14L11.5 9" stroke="#FCF9F6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>
  </div>

  @if (count($gallery) > 1)
    <div class="mt-3 hidden lg:block">
      <div class="swiper product-gallery-thumbs overflow-hidden">
        <div class="swiper-wrapper">
          @foreach ($gallery as $image)
            <div
              class="swiper-slide [&.swiper-slide-thumb-active]:opacity-100 [&.swiper-slide-thumb-active]:border-green-dark cursor-pointer overflow-hidden rounded-sm border border-transparent bg-white opacity-60 transition-opacity duration-200"
            >
              <img
                src="{{ esc_url($image['src']) }}"
                alt="{{ esc_attr($image['alt'] ?: $title) }}"
                @if (($image['width'] ?? 0) > 0) width="{{ (int) $image['width'] }}" @endif
                @if (($image['height'] ?? 0) > 0) height="{{ (int) $image['height'] }}" @endif
                @if (!empty($image['srcset'])) srcset="{{ esc_attr($image['srcset']) }}" @endif
                @if (!empty($image['sizes'])) sizes="{{ esc_attr($image['sizes']) }}" @endif
                class="aspect-square h-full w-full object-cover"
              />
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif
</div>
