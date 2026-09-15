@use('App\Catalog\Product')
@php
  /**
   * @var Product $item
   */
@endphp
<div class="swiper-slide product-card-3 flex flex-col items-start justify-center">
  <div class="relative w-full">
    <img
      src="{{ $item->image?->src('medium') ?: wc_placeholder_img_src() }}"
      alt="{{ $item->image?->alt() ?: $item->name }}"
      @if (($item->image?->width() ?? 0) > 0) width="{{ $item->image->width() }}" @endif
      @if (($item->image?->height() ?? 0) > 0) height="{{ $item->image->height() }}" @endif
      @if ($item->image?->srcset('medium')) srcset="{{ $item->image->srcset('medium') }}" @endif
      @if ($item->image?->img_sizes('medium')) sizes="{{ $item->image->img_sizes('medium') }}" @endif
      class="aspect-15/13 size-full object-cover"
    />

    @include('elements.badges', ['badges' => $item->badges])
  </div>

  <div class="text-dark-text pt-2">
    <a
      href="{{ $item->link }}"
      target="{{ $item->target }}"
      class="text-body-13 font-light truncate font-bold text-wrap uppercase before:absolute before:inset-0 md:text-lg lg:font-semibold"
    >
      {{ $item->name }}
    </a>
    <p class="text-body-13 font-light md:text-body-15">{!! $item->price !!}</p>
  </div>
</div>
