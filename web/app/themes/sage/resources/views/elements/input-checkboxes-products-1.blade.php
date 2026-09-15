@props([
  'name' => 'product_ids',
  'value' => [],
  'id' => null,
  'class' => '',
  'label' => null,
  'icon' => null,
  'items' => []
])

@php
  $id = $id ?: $name;
  $inputName = $name && str_ends_with($name, '[]') ? $name : ($name ? $name . '[]' : 'product_ids[]');
  $selectedValues = array_map(
    'strval',
    array_values(array_filter(is_array($value) ? $value : [$value], fn($item) => $item !== null && $item !== '')),
  );

  $products = [];

  foreach ($items as $item) {
    if ($item instanceof \App\Catalog\Product) {
      $products[] = $item;
      continue;
    }

    if ($item instanceof \WC_Product) {
      $products[] = \App\Catalog\Product::fromWooCommerce($item);
      continue;
    }

    $productId = absint($item);

    if ($productId <= 0) {
      continue;
    }

    try {
      $products[] = \App\Catalog\Product::fromID($productId);
    } catch (\InvalidArgumentException $exception) {
      continue;
    }
  }
@endphp

<div @class($class)>
  @if ($label)
    @include('elements.label-product-setting')
  @endif

  <div class="grid gap-3 lg:grid-cols-3">
    @foreach ($products as $item)
      @php
        $optionId = ($id ?: $name ?: 'product-checkboxes') . '-' . $item->id;
        $isChecked = in_array((string) $item->id, $selectedValues, true);
      @endphp
      <label
        for="{{ $optionId }}"
        class="text-green-default has-checked:[&_.checkbox-toggle]:border-[#6E8F7E] has-checked:[&_.checkbox-toggle]:bg-[#6E8F7E] has-checked:[&_.checkbox-toggle]:text-white has-checked:[&_.checkbox-toggle_.icon-minus]:block has-checked:[&_.checkbox-toggle_.icon-plus]:hidden flex cursor-pointer rounded-2xl border border-[#C7C7C7] bg-white p-2 transition-colors has-checked:border-[#6E8F7E] has-checked:bg-[#F7FAF4]"
      >
        <input
          id="{{ $optionId }}"
          type="checkbox"
          name="{{ $inputName }}"
          value="{{ $item->id }}"
          class="addition-checkbox sr-only"
          data-addition-id="{{ $item->id }}"
          data-addition-price="{{ $item->product->get_price() }}"
          data-addition-name="{{ $item->name }}"
          @checked($isChecked)
        />

        <div class="flex min-w-0 flex-1 items-center gap-4">
          <img
            src="{{ $item->image?->src('medium') ?: wc_placeholder_img_src() }}"
            alt="{{ $item->image?->alt() ?: $item->name }}"
            @if (($item->image?->width() ?? 0) > 0) width="{{ $item->image->width() }}" @endif
            @if (($item->image?->height() ?? 0) > 0) height="{{ $item->image->height() }}" @endif
            class="h-16 w-12 shrink-0 object-contain"
          />

          <div class="min-w-0 flex-1">
            <div class="text-[16px] leading-6 font-medium text-[#404844]">{{ $item->name }}</div>
            <div class="text-[16px] leading-6 font-bold text-[#404844]">{!! $item->price !!}</div>
          </div>

          <span
            class="checkbox-toggle text-green-default flex size-10 shrink-0 items-center justify-center rounded-2xl border border-current bg-[#EEF4E8] transition-colors"
          >
            <svg class="icon-plus" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 4.16663V15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M15.8335 10H4.16683" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <svg class="icon-minus hidden" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M15.8335 10H4.16683" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </div>
      </label>
    @endforeach
  </div>
</div>
