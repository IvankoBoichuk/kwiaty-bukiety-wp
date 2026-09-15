@php
  $wrapperTag = $wrapperTag ?? 'div';
  $wrapperAttributes = $wrapperAttributes ?? [];
  $wrapperClass = trim(
    (string) ($wrapperClass ??
      'product-card-slider swiper-slide border-background bg-background max-lg:shadow-100 flex flex-col items-start justify-center border'),
  );
@endphp

<{{ $wrapperTag }}
  @foreach ($wrapperAttributes as $attribute => $value)
    {{ $attribute }}="{{ esc_attr($value) }}"
  @endforeach
  @class([$wrapperClass])
>
  <div class="relative w-full">
    <img
      src="{{ esc_url($item->image?->src('medium') ?: wc_placeholder_img_src()) }}"
      alt="{{ esc_attr($item->image?->alt() ?: $item->name) }}"
      @if (($item->image?->width() ?? 0) > 0) width="{{ $item->image->width() }}" @endif
      @if (($item->image?->height() ?? 0) > 0) height="{{ $item->image->height() }}" @endif
      @if ($item->image?->srcset('medium')) srcset="{{ esc_attr($item->image->srcset('medium')) }}" @endif
      @if ($item->image?->img_sizes('medium')) sizes="{{ esc_attr($item->image->img_sizes('medium')) }}" @endif
      class="aspect-square size-full object-cover"
    />

    @include('elements.badges', ['badges' => $item->badges])
  </div>

  <div class="text-dark-text px-1 pt-2 pb-3">
    <a
      href="{{ esc_url($item->link) }}"
      target="{{ esc_attr($item->target) }}"
      class="truncate text-sm font-bold text-wrap uppercase before:absolute before:inset-0 md:text-lg lg:font-semibold"
    >
      {{ $item->name }}
    </a>
    <p class="text-body-13 font-light md:text-body-15">{!! $item->price !!}</p>
  </div>
</{{ $wrapperTag }}>
