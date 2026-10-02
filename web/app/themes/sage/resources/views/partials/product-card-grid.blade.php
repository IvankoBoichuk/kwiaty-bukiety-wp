@use('App\Catalog\Product')
@php
  /**
   * @var Product $item
   */
  $wrapperTag = $wrapperTag ?? 'div';
  $wrapperAttributes = $wrapperAttributes ?? [];
  $wrapperClass = trim((string) ($wrapperClass ?? 'product-card-grid relative flex flex-col items-start'));

  // The card sits in a grid that is 2 columns on phones, 3 from md and 4 on
  // the desktop breakpoint, so the slot is never wider than half the viewport.
  // The `large` size is the smallest one that still covers the ~411px slot of
  // the desktop design -- `medium` is 200px wide and was being upscaled.
  $imageSizes = $imageSizes ?? '(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw';
@endphp

<{{ $wrapperTag }}
  @foreach ($wrapperAttributes as $attribute => $value)
    {{ $attribute }}="{{ esc_attr($value) }}"
  @endforeach
  @class([$wrapperClass])
>
  <div class="relative w-full">
    <img
      src="{{ esc_url($item->image?->src('large') ?: wc_placeholder_img_src()) }}"
      alt="{{ esc_attr($item->image?->alt() ?: $item->name) }}"
      @if (($item->image?->width() ?? 0) > 0) width="{{ $item->image->width() }}" @endif
      @if (($item->image?->height() ?? 0) > 0) height="{{ $item->image->height() }}" @endif
      @if ($item->image?->srcset('large')) srcset="{{ esc_attr($item->image->srcset('large')) }}" @endif
      sizes="{{ esc_attr($imageSizes) }}"
      loading="lazy"
      class="aspect-15/13 size-full object-cover"
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

  <div class="text-dark-text w-full pt-2">
    <a
      href="{{ esc_url($item->link) }}"
      target="{{ esc_attr($item->target) }}"
      class="block truncate text-sm font-bold uppercase before:absolute before:inset-0 md:text-base"
    >
      {{ $item->name }}
    </a>
    <p class="text-body-13 md:text-body-15">{!! $item->price !!}</p>
  </div>
</{{ $wrapperTag }}>
