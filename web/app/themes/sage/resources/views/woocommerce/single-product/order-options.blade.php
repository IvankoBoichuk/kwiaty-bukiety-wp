<div
  class="grid gap-8 lg:gap-6 2xl:grid-cols-2"
  data-delivery-schedule="{!! esc_attr(wp_json_encode($deliverySchedule, JSON_UNESCAPED_UNICODE)) !!}"
>
  <div class="2xl:col-span-2">
    @include('woocommerce.single-product.shop-notices')
  </div>

  {{-- Delivery date --}}
  <div data-delivery-date-section>
    @include('elements.label-product-setting',
      [
        'label' => __('Delivery date', 'sage-front'),
        'icon' => 'calendar'
      ])

    <div
      class="flex gap-1.5"
      :class="$store.productPurchase.deliveryDateError ? 'rounded-2xl ring-1 ring-[#D54C4C] p-1' : ''"
    >
      @foreach ($deliverySchedule['dateOptions'] as $dateOption)
        <button
          type="button"
          class="delivery-date-option single-product-settings-option flex-1"
          data-date-value="{{ $dateOption['value'] }}"
        >
          {{ $dateOption['label'] }}
        </button>
      @endforeach
      {{-- The native input overlays the button instead of sitting inside it.
           A form control nested in a <button> is invalid markup that Safari
           never lets the user reach, and Safari also ignores showPicker() on a
           control it considers untouchable, so the only reliable way to open
           its date picker is to let the tap land on the input itself. The
           button stays underneath purely as the visible, styleable surface and
           is taken out of the tab order so keyboard users land on the input. --}}
      <div class="relative flex flex-1">
        <button
          type="button"
          class="group delivery-date-option delivery-date-custom single-product-settings-option flex w-full items-center justify-center gap-1.5"
          data-date-option="custom"
          tabindex="-1"
        >
          <span class="delivery-date-label">{{ __('Custom date', 'sage-front') }}</span>
          <svg class="stroke-gray-3 group-hover:stroke-white group-[.active]:stroke-white" width="20" height="20" aria-hidden="true">
            <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#chevron-right"></use>
          </svg>
        </button>
        <input
          id="delivery-date-input"
          type="date"
          class="delivery-date-input absolute inset-0 h-full w-full cursor-pointer bg-transparent opacity-0"
          data-date-input
          aria-label="{{ __('Custom date', 'sage-front') }}"
          min="{{ ($deliverySchedule['dateOptions'][0]['value'] ?? date('Y-m-d')) }}"
        />
      </div>
    </div>

    <p
      x-show="$store.productPurchase.deliveryDateError"
      x-text="$store.productPurchase.deliveryDateError"
      class="mt-1 text-[12px] leading-4 text-[#D54C4C]"
    ></p>
  </div>

  {{-- Delivery time --}}
  <div>
    @include('elements.label-product-setting',
      [
        'label' => __('Delivery time', 'sage-front'),
        'icon' => 'clock'
      ])

    <div
      class="flex flex-wrap gap-1.5"
      :class="$store.productPurchase.deliveryTimeError ? 'rounded-2xl ring-1 ring-[#D54C4C] p-1' : ''"
    >
      @foreach ($deliverySchedule['timeOptions'] ?? [] as $timeSlot)
        <button
          type="button"
          class="delivery-time-option single-product-settings-option flex-1"
          data-time-slot="{{ $timeSlot['value'] }}"
          data-slot-start="{{ $timeSlot['start'] }}"
          data-slot-end="{{ $timeSlot['end'] }}"
        >
          {{ $timeSlot['label'] }}
        </button>
      @endforeach
    </div>

    <p
      x-show="$store.productPurchase.deliveryTimeError"
      x-text="$store.productPurchase.deliveryTimeError"
      class="mt-1 text-[12px] leading-4 text-[#D54C4C]"
    ></p>
  </div>

  {{-- Tresc bileciku --}}
  <div class="col-span-full">
    @include('elements.label-product-setting',
      [
        'label' => __('Card message', 'sage-front'),
        'icon' => 'message'
      ])

    <textarea
      class="single-product-settings-option focus:border-green-easy max-lg:text-body-13 min-h-23 w-full cursor-text resize-none font-light placeholder:text-[#404844] focus:outline-none"
      placeholder="{{ __('Leave empty if you don\'t need a card', 'sage-front') }}"
      name="card-message"
    ></textarea>
  </div>
</div>
