@php
  $wrapperTag = $wrapperTag ?? 'div';
  $wrapperAttributes = $wrapperAttributes ?? [];
  $wrapperClass = trim(
    (string) ($wrapperClass ??
      'product-card-slider swiper-slide border-background bg-background max-lg:shadow-100 flex flex-col items-start justify-center border'),
  );

  // The browser picks this card's image as the LCP element on pages that open
  // with the slider, so the slide that holds it asks to be fetched first. The
  // partial cannot tell where it sits, so the caller opts in -- and only for
  // one image per page, or the hint is spread across several and helps none.
  $isLcpCandidate = (bool) ($isLcpCandidate ?? false);
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
      @if ($isLcpCandidate) fetchpriority="high" loading="eager" @endif
      class="aspect-square size-full object-cover"
    />

    {{-- The wrapper class has to be passed explicitly: @include hands the
         partial every variable in this scope, so the card's own $wrapperClass
         would otherwise win over the badge default. --}}
    @include('elements.badges',
      [
        'badges' => $item->badges,
        'wrapperClass' => 'absolute top-1 left-1 flex flex-wrap gap-1.5'
      ])
  </div>

  <div class="text-dark-text px-1 pt-2 pb-3">
    <a
      href="{{ esc_url($item->link) }}"
      target="{{ esc_attr($item->target) }}"
      class="truncate text-sm font-bold text-wrap uppercase before:absolute before:inset-0 md:text-lg lg:font-semibold"
    >
      {{ $item->name }}
    </a>
    <p class="text-body-13 md:text-body-15 font-light">{!! $item->price !!}</p>
  </div>
</{{ $wrapperTag }}>
