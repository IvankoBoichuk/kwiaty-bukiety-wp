@props([
  'name' => 'addition_ids',
  'value' => [],
  'id' => null,
  'class' => '',
  'label' => null,
  'icon' => null,
  'items' => []
])

@php
  $id = $id ?: $name;
  $inputName = $name && str_ends_with($name, '[]') ? $name : ($name ? $name . '[]' : 'addition_ids[]');
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

  <div class="grid grid-cols-2 gap-3 xl:grid-cols-3">
    @foreach ($products as $item)
      @php
        $optionId = ($id ?: $name ?: 'addition-checkboxes') . '-' . $item->id;
        $isChecked = in_array((string) $item->id, $selectedValues, true);
      @endphp
      <label
        for="{{ $optionId }}"
        class="group single-product-settings-option flex grid-cols-[80px_1fr] flex-col overflow-hidden p-0 lg:grid lg:gap-3 lg:rounded-2xl lg:p-3"
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

        <img
          src="{{ $item->image?->src('medium') ?: wc_placeholder_img_src() }}"
          alt="{{ $item->image?->alt() ?: $item->name }}"
          @if (($item->image?->width() ?? 0) > 0) width="{{ $item->image->width() }}" @endif
          @if (($item->image?->height() ?? 0) > 0) height="{{ $item->image->height() }}" @endif
          class="h-38.5 w-full rounded-xl object-cover lg:aspect-square lg:h-auto"
        />

        <div class="flex flex-1 flex-col justify-between gap-2 p-2 lg:p-0">
          <span
            class="text-body-15 text-gray-1 leading-4.5 font-semibold text-wrap group-has-checked:text-white"
            >{{ $item->name }}</span
          >

          <div class="flex items-center justify-between gap-2">
            <span
              class="text-gray-1 pl-1 text-[16px] font-bold group-has-checked:text-white"
              >{!! $item->price !!}</span
            >

            <span
              class="text-green-default flex items-center justify-center rounded-xl border border-current bg-[#A5AB5F]/25 p-2 transition-all group-has-checked:text-white"
            >
              <svg class="group-has-checked:hidden" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 4.16663V15.8333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M15.8335 10H4.16683" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>

              <svg class="hidden group-has-checked:block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.8335 10H4.16683" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
          </div>
        </div>
      </label>
    @endforeach
  </div>
</div>
