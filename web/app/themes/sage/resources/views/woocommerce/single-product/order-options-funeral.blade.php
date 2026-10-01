@use('App\Catalog\Settings')
<div
  class="flex flex-col gap-8"
  data-delivery-schedule="{!! esc_attr(wp_json_encode($deliverySchedule, JSON_UNESCAPED_UNICODE)) !!}"
>
  @include('woocommerce.single-product.shop-notices')

  <div class="grid grid-cols-2 gap-x-3 gap-y-6 lg:gap-6">
    {{-- Delivery date --}}
    <div data-funeral-delivery-date-section>
      @include('elements.input-date-1',
        [
          'name' => 'deliveryDate',
          'min' => $deliverySchedule['dateOptions'][0]['value'] ?? date('Y-m-d'),
          'label' => __('Delivery date', 'sage-front'),
          'icon' => 'calendar'
        ])

      <p
        x-show="$store.productPurchase.deliveryDateError"
        x-text="$store.productPurchase.deliveryDateError"
        class="mt-1 text-[12px] leading-4 text-[#D54C4C]"
      ></p>
    </div>

    <div>
      @include('elements.input-time-1',
        [
          'name' => 'deliveryTime',
          'label' => __('Delivery time', 'sage-front'),
          'icon' => 'clock'
        ])

      <p
        x-show="$store.productPurchase.deliveryTimeError"
        x-text="$store.productPurchase.deliveryTimeError"
        class="mt-1 text-[12px] leading-4 text-[#D54C4C]"
      ></p>
    </div>

    {{-- Delivery location --}}
    @include('elements.input-select-1',
      [
        'name' => 'deliveryLocation',
        'label' => __('Delivery location', 'sage-front'),
        'icon' => 'location',
        'options' => Settings::locationsOptions(),
        'class' => 'max-lg:col-span-2'
      ])

    {{-- Delivery type --}}
    @include('elements.input-radio-btns-1',
      [
        'name' => 'deliveryType',
        'label' => __('Delivery type', 'sage-front'),
        'icon' => 'opened-box',
        'options' => Settings::deliveryOptions(),
        'class' => 'max-lg:col-span-2'
      ])

    {{-- Deceased's full name --}}
    @include('elements.input-text-1',
      [
        'name' => 'deceasedFullName',
        'label' => __('Deceased\'s full name', 'sage-front'),
        'icon' => 'account',
        'class' => 'max-lg:col-span-2'
      ])

    {{-- Ribbon message (20 PLN) --}}
    @include('elements.input-text-1',
      [
        'id' => 'card_message_payed',
        'name' => 'cardMessage',
        'label' => __('Ribbon message (20 PLN)', 'sage-front'),
        'icon' => 'message',
        'class' => 'max-lg:col-span-2'
      ])

    {{-- Add-ons --}}
    @include('elements.input-checkboxes-products-1',
      [
        'name' => 'additionIds',
        'label' => __('Add-ons', 'sage-front'),
        'icon' => 'plus',
        'items' => Settings::funeralAdditionsProducts(),
        'class' => 'col-span-2'
      ])
  </div>
</div>
