@php
  /**
   * The curated tile grid above a category's products.
   *
   * Rows arrive resolved by App\Catalog\CategoryTiles: the link, the caption and
   * the picture are already filled in from the tile's category wherever the
   * editor left them blank.
   *
   * @var array<int, array{link: string, name: string, image: int}> $tiles
   */
  $placeholder = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('large') : '';
@endphp

<nav class="mb-8 lg:mb-12" aria-label="{{ esc_attr__('Categories', 'sage-front') }}">
  <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:gap-5 xl:grid-cols-4">
    @foreach ($tiles as $tile)
      <li class="relative">
        <a
          href="{{ esc_url($tile['link']) }}"
          class="group block overflow-hidden rounded-2xl lg:rounded-[32px]"
        >
          <span class="relative block aspect-4/3">
            @if ($tile['image'] > 0)
              {!!
                wp_get_attachment_image($tile['image'], 'large', false, [
                    'class' => 'size-full object-cover transition duration-300 group-hover:scale-105',
                    'alt' => $tile['name'],
                    'loading' => 'lazy',
                    'sizes' => '(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw',
                ])
              !!}
            @elseif ($placeholder !== '')
              <img
                src="{{ esc_url($placeholder) }}"
                alt="{{ esc_attr($tile['name']) }}"
                loading="lazy"
                class="size-full object-cover"
              />
            @else
              <span class="bg-secondary block size-full" aria-hidden="true"></span>
            @endif

            {{-- The caption sits on the photo, as it does on the old page.
                 The old page dimmed the whole picture to 70% to keep white
                 text readable; a gradient over the bottom half does the same
                 for the caption and leaves the flower itself at full
                 brightness. It has to carry its own height -- padding alone
                 gave it a 20px band that the caption sat above, and white on a
                 white carnation was unreadable. --}}
            <span
              class="absolute inset-x-0 bottom-0 h-1/2 bg-linear-to-t from-black/75 via-black/35 to-transparent"
              aria-hidden="true"
            ></span>

            <span
              class="absolute right-2.5 bottom-2.5 left-2.5 text-right text-[14px] leading-tight font-bold text-white uppercase md:text-[20px] lg:text-[24px]"
            >
              {{ $tile['name'] }}
            </span>
          </span>
        </a>
      </li>
    @endforeach
  </ul>
</nav>
