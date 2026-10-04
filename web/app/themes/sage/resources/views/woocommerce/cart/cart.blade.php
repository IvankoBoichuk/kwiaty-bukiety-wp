@php
  $placeholderImage = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_thumbnail') : '';
  $fields = $cartCheckout['fields'] ?? [];
  $paymentGateways = $cartCheckout['paymentGateways'] ?? [];
  $canUseCoupons = function_exists('wc_coupons_enabled') && wc_coupons_enabled();

  remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);

  $renderField = static function (string $key) use ($fields, $checkoutInstance) {
    if (!($checkoutInstance instanceof \WC_Checkout) || empty($fields[$key])) {
      return '';
    }

    $field = $fields[$key];

    return woocommerce_form_field($key, $field, $field['value'] ?? $checkoutInstance->get_value($key));
  };

  $renderFieldWithError = static function (string $key) use ($renderField) {
    $fieldMarkup = $renderField($key);

    if ($fieldMarkup === '') {
      return '';
    }

    return '<div>' .
      $fieldMarkup .
      '<p class="mt-1 text-[12px] leading-4 text-[#C6463D]" x-cloak x-show="$store.cartCheckout.validationErrors[' .
      "'{$key}'" .
      ']" x-text="$store.cartCheckout.validationErrors[' .
      "'{$key}'" .
      ']"></p></div>';
  };
@endphp

@php
  $orderButtonText = apply_filters('woocommerce_order_button_text', __('Buy and pay', 'sage-front'));
@endphp

@php
  do_action('woocommerce_before_cart');
@endphp

<section class="bg-background flex flex-col pt-5 pb-7 lg:pb-20" x-data>
  @php
    woocommerce_output_all_notices();
  @endphp

  <script>
    window.cartCheckoutConfig = @json($cartCheckout['config'] ?? []);
  </script>

  <div
    class="shadow-card mb-4 grid grid-cols-3 gap-2 overflow-hidden rounded-xl border border-[#C7C7C7] bg-white/80 p-1"
  >
    <button
      type="button"
      class="cart-checkout-step-tab rounded-lg px-3 py-2 text-[13px] font-semibold transition"
      :class="$store.cartCheckout.currentStep === 1 ? 'bg-[#6E8F7E] text-white' : 'text-green-default'"
      @click="$store.cartCheckout.goToStep(1)"
    >
      {{ __('Cart', 'sage-front') }}
    </button>
    <button
      type="button"
      class="cart-checkout-step-tab rounded-lg px-3 py-2 text-[13px] font-semibold transition"
      :class="$store.cartCheckout.currentStep === 2 ? 'bg-[#6E8F7E] text-white' : 'text-green-default'"
      :disabled="$store.cartCheckout.isCartEmpty"
      @click="$store.cartCheckout.goToStep(2)"
    >
      {{ __('Info', 'sage-front') }}
    </button>
    <button
      type="button"
      class="cart-checkout-step-tab rounded-lg px-3 py-2 text-[13px] font-semibold transition"
      :class="$store.cartCheckout.currentStep === 3 ? 'bg-[#6E8F7E] text-white' : 'text-green-default'"
      :disabled="$store.cartCheckout.isCartEmpty"
      @click="$store.cartCheckout.goToStep(3)"
    >
      {{ __('Payment', 'sage-front') }}
    </button>
  </div>

  <form
    id="sage-cart-checkout-form"
    method="post"
    action="{{ esc_url($cartCheckout['checkoutUrl'] ?? wc_get_checkout_url()) }}"
    @submit.prevent="$store.cartCheckout.submitOrder($event)"
    class="checkout woocommerce-checkout"
    enctype="multipart/form-data"
  >
    @php
      do_action('woocommerce_before_checkout_form', $checkoutInstance);
    @endphp
    <input type="hidden" name="ship_to_different_address" value="1" />
    {!!
      wp_nonce_field(
        'woocommerce-process_checkout',
        'woocommerce-process-checkout-nonce',
      )
    !!}

    <div class="relative grid gap-5 lg:grid-cols-[1fr_532px] lg:gap-8">
      <div class="">
        <header
          class="mb-5 flex items-center justify-between"
          :class="{
            'hidden': $store.cartCheckout.isCartEmpty,
            'lg:hidden': $store.cartCheckout.currentStep === 3,
          }"
        >
          <h1 class="h2-mobile lg:h3-desktop"
          x-text="
            $store.cartCheckout.currentStep === 1
              ? '{{ __('Cart', 'sage-front') }} (' + $store.cartCheckout.cartCount + ')'
              : $store.cartCheckout.currentStep === 2
                ? '{{ esc_js(__('Checkout', 'sage-front')) }}'
                : '{{ esc_js(__('Your order', 'sage-front')) }}'
          "></h1>
        </header>
        <div
          x-cloak
          x-show="$store.cartCheckout.currentStep === 1"
          class="cart-checkout-step-panel"
          x-transition:enter="transform-gpu transition duration-350 ease-[cubic-bezier(0.22,1,0.36,1)]"
          x-transition:enter-start="translate-y-5 scale-[0.985] opacity-0"
          x-transition:enter-end="translate-y-0 scale-100 opacity-100"
          x-transition:leave="pointer-events-none absolute inset-0 transform-gpu transition duration-180 ease-in"
          x-transition:leave-start="translate-y-0 scale-100 opacity-100"
          x-transition:leave-end="-translate-y-3 scale-[0.99] opacity-0"
        >
          <div class="space-y-4 divide-y divide-[#E9E1D8] @container" x-show="!$store.cartCheckout.isCartEmpty">
            <div
              class="hidden mb-5 border-none text-body-16 text-green-default @4xl:grid @4xl:grid-cols-[6.875rem_minmax(0,1fr)_9rem_7rem_7rem_2rem] @4xl:items-center @4xl:gap-5"
              aria-hidden="true"
            >
              <span>{{ __('Image', 'sage-front') }}</span>
              <span>{{ __('Details', 'sage-front') }}</span>
              <span class="text-center">{{ __('Quantity', 'sage-front') }}</span>
              <span class="text-right">{{ __('Price', 'sage-front') }}</span>
              <span class="text-right">{{ __('Total', 'sage-front') }}</span>
              <span></span>
            </div>

            <template x-for="item in $store.cartCheckout.items" :key="item.key">
              <article class="flex gap-3 pb-4 last:pb-0 @4xl:grid @4xl:grid-cols-[6.875rem_minmax(0,1fr)_9rem_7rem_7rem_2rem] @4xl:items-center @4xl:gap-5">
                <div class="aspect-110/120 flex-none w-27.5 overflow-hidden bg-[#EFEAE3] md:w-35 @4xl:w-full">
                  <img
                    :src="item.image || '{{ esc_js($placeholderImage) }}'"
                    :alt="item.imageAlt || item.name"
                    class="size-full object-cover"
                    width="110" height="120"
                  />
                </div>

                <div class="min-w-0 flex-1 flex flex-col justify-between gap-3 @4xl:block">
                  <div class="flex items-start gap-3 @4xl:block">
                    <div class="min-w-0 flex-1">
                      <a
                        :href="item.url || '#'"
                        class="text-green-default mb-1 text-body-15 md:text-body-16 font-bold"
                        x-text="item.name"
                      ></a>
                      <p
                        class="text-green-default/75 text-body-13 md:text-body-14"
                        x-show="item.summary"
                        x-text="item.summary"
                      ></p>
                    </div>

                    <button
                      type="button"
                      class="text-green-easy @4xl:hidden"
                      :disabled="$store.cartCheckout.isLoading"
                      @click="$store.cartCheckout.removeItem(item.key)"
                      aria-label="{{ esc_attr(__('Remove item', 'sage-front')) }}"
                    >
                      <svg width="20" height="20">
                        <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#close"></use>
                      </svg>
                    </button>
                  </div>

                  <div class="flex items-end justify-between gap-3 @4xl:hidden">
                    <div class="text-green-dark text-[16px] font-bold md:text-[20px]" x-text="item.lineTotal.formatted"></div>

                    <div class="flex items-center gap-3">
                      <button
                        type="button"
                        class="text-green-easy border-green-easy inline-flex size-9 items-center justify-center rounded-[14px] border text-[28px] leading-none"
                        :disabled="$store.cartCheckout.isLoading"
                        @click="$store.cartCheckout.updateQuantity(item.key, item.quantity - 1)"
                      >
                        <svg width="20" height="20">
                          <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#decrement"></use>
                        </svg>
                      </button>

                      <span
                        class="text-green-dark min-w-4 text-center h3-mobile"
                        x-text="item.quantity"
                      ></span>

                      <button
                        type="button"
                        class="text-green-easy border-green-easy inline-flex size-9 items-center justify-center rounded-[14px] border text-[28px] leading-none"
                        :disabled="$store.cartCheckout.isLoading"
                        @click="$store.cartCheckout.updateQuantity(item.key, item.quantity + 1)"
                      >
                        <svg width="20" height="20">
                          <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#increment"></use>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="hidden @4xl:flex @4xl:justify-center">
                  <div class="flex items-center gap-2.5 px-4 py-3 border border-[#E0E0D7] rounded-xl">
                    <button
                      type="button"
                      class="text-green-easy"
                      :disabled="$store.cartCheckout.isLoading"
                      @click="$store.cartCheckout.updateQuantity(item.key, item.quantity - 1)"
                    >
                      <svg width="20" height="20">
                        <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#decrement"></use>
                      </svg>
                    </button>

                    <span
                      class="text-green-dark min-w-4 text-center h4-desktop"
                      x-text="item.quantity"
                    ></span>

                    <button
                      type="button"
                      class="text-green-easy"
                      :disabled="$store.cartCheckout.isLoading"
                      @click="$store.cartCheckout.updateQuantity(item.key, item.quantity + 1)"
                    >
                      <svg width="20" height="20">
                        <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#increment"></use>
                      </svg>
                    </button>
                  </div>
                </div>

                <div class="hidden text-right h4-desktop text-gray-1 @4xl:block" x-text="item.unitPrice.formatted"></div>

                <div class="hidden text-right h4-desktop text-gray-1 @4xl:block" x-text="item.lineTotal.formatted"></div>

                <button
                  type="button"
                  class="hidden text-green-easy @4xl:inline-flex @4xl:items-center @4xl:justify-self-end"
                  :disabled="$store.cartCheckout.isLoading"
                  @click="$store.cartCheckout.removeItem(item.key)"
                  aria-label="{{ esc_attr(__('Remove item', 'sage-front')) }}"
                >
                  <svg width="20" height="20">
                    <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#close"></use>
                  </svg>
                </button>
              </article>
            </template>
          </div>

          <div class="card text-center lg:p-8 lg:rounded-4xl" x-show="$store.cartCheckout.isCartEmpty">
            <h2 class="text-green-default mb-3 text-[20px] font-semibold">{{ __('Your cart is empty', 'sage-front') }}</h2>
            <a
              href="{{ esc_url(wc_get_page_permalink('shop')) }}"
              class="border-green-default bg-green-dark inline-flex rounded-full border px-6 py-3 text-[13px] font-semibold text-white"
            >
              {{ __('Back to shop', 'sage-front') }}
            </a>
          </div>
        </div>

        <div
          x-cloak
          x-show="$store.cartCheckout.currentStep === 2"
          class="cart-checkout-step-panel"
          data-cart-checkout-info-step
          x-transition:enter="transform-gpu transition duration-350 ease-[cubic-bezier(0.22,1,0.36,1)]"
          x-transition:enter-start="translate-y-5 scale-[0.985] opacity-0"
          x-transition:enter-end="translate-y-0 scale-100 opacity-100"
          x-transition:leave="pointer-events-none absolute inset-0 transform-gpu transition duration-180 ease-in"
          x-transition:leave-start="translate-y-0 scale-100 opacity-100"
          x-transition:leave-end="-translate-y-3 scale-[0.99] opacity-0"
        >
          <div
            class="space-y-3.5"
            @input="$store.cartCheckout.clearFieldError($event.target.name)"
            @change="$store.cartCheckout.clearFieldError($event.target.name)"
          >
            <div class="card lg:p-8 lg:rounded-4xl">
              <h2 class="text-green-default h3-mobile md:h4-desktop mb-5">{{ __('Recipient details', 'sage-front') }}</h2>
              <div class="grid gap-3 xl:grid-cols-2 xl:gap-y-8 xl:gap-x-4">
                {!! $renderFieldWithError('shipping_type_of_place') !!} {!! $renderFieldWithError('shipping_address_1') !!} {!! $renderFieldWithError('shipping_postcode') !!} {!! $renderFieldWithError('shipping_city') !!} {!! $renderFieldWithError('shipping_first_name') !!} {!! $renderFieldWithError('shipping_place_name') !!} {!! $renderFieldWithError('shipping_phone') !!}
              </div>
            </div>

            <div class="card lg:p-8 lg:rounded-4xl">
              <h2 class="text-green-default h3-mobile md:h4-desktop mb-5">{{ __('Sender details', 'sage-front') }}</h2>
              <div class="grid gap-3 xl:grid-cols-2 xl:gap-y-8 xl:gap-x-4">
                {!! $renderFieldWithError('billing_first_name') !!} {!! $renderFieldWithError('billing_last_name') !!} {!! $renderFieldWithError('billing_phone') !!} {!! $renderFieldWithError('billing_email') !!} {!! $renderFieldWithError('billing_nip') !!}
              </div>
            </div>

            <div class="card lg:p-8 lg:rounded-4xl">
              <h2 class="text-green-default h3-mobile md:h4-desktop mb-5">{{ __('Delivery notes', 'sage-front') }}</h2>
              <div class="grid gap-3">{!! $renderFieldWithError('order_comments') !!}</div>
            </div>
          </div>
        </div>

        <div
          x-cloak
          x-show="$store.cartCheckout.currentStep === 3"
          class="cart-checkout-step-panel"
          x-transition:enter="transform-gpu transition duration-350 ease-[cubic-bezier(0.22,1,0.36,1)]"
          x-transition:enter-start="translate-y-5 scale-[0.985] opacity-0"
          x-transition:enter-end="translate-y-0 scale-100 opacity-100"
          x-transition:leave="pointer-events-none absolute inset-0 transform-gpu transition duration-180 ease-in"
          x-transition:leave-start="translate-y-0 scale-100 opacity-100"
          x-transition:leave-end="-translate-y-3 scale-[0.99] opacity-0"
        >
          @if ($canUseCoupons)
            @include('partials.cart-coupon')
          @endif

          <div class="mb-5 lg:hidden">
            <div class="space-y-3">
              <template x-for="item in $store.cartCheckout.items" :key="`summary-${item.key}`">
                <div
                  class="flex items-start justify-between gap-3 border-b border-[#E9E1D8] pb-3 last:border-b-0 last:pb-0"
                >
                  <div class="text-green-default min-w-0 flex-1 text-[14px] leading-5">
                    <span class="text-green-easy mr-2" x-text="`x${item.quantity}`"></span>
                    <span x-text="item.name"></span>
                  </div>
                  <div
                    class="text-green-default text-[14px] leading-5 font-semibold"
                    x-text="item.lineTotal.formatted"
                  ></div>
                </div>
              </template>
            </div>

            <div class="text-green-default mt-3 space-y-3 border-t border-[#E0E0D7] pt-4 text-[15px] leading-5">
              <div class="flex items-center justify-between">
                <span x-text="$store.cartCheckout.totals.shipping.label"></span>
                <span x-text="$store.cartCheckout.totals.shipping.amount.formatted"></span>
              </div>
              <div class="flex items-center justify-between">
                <span x-text="$store.cartCheckout.totals.discount.label"></span>
                <span x-text="$store.cartCheckout.totals.discount.amount.formatted"></span>
              </div>
              <div class="flex items-center justify-between border-y border-[#E0E0D7] py-4 font-bold">
                <span x-text="$store.cartCheckout.totals.total.label"></span>
                <span x-text="$store.cartCheckout.totals.total.amount.formatted"></span>
              </div>
            </div>
          </div>

          <div class="card lg:p-8 lg:rounded-4xl">
            <h2 class="text-green-default h3-mobile md:h4-desktop mb-5">{{ __('Payment method', 'sage-front') }}</h2>

            @foreach ($paymentGateways as $gateway)
              @php
                if (!($gateway instanceof \WC_Payment_Gateway)) {
                  continue;
                }

                ob_start();
                $gateway->payment_fields();
                $paymentFieldsMarkup = trim((string) ob_get_clean());
              @endphp
              <div class="mb-4 last:mb-0">
                <label class="flex cursor-pointer items-center gap-3 px-2 py-2">
                  <input
                    type="radio"
                    name="payment_method"
                    value="{{ esc_attr($gateway->id) }}"
                    x-model="$store.cartCheckout.selectedPaymentMethod"
                    @checked(($cartCheckout['selectedPaymentMethod'] ?? '') === $gateway->id)
                    class="border-green-easy accent-green-easy size-4"
                  />
                  <div
                    class="text-green-default leading-6"
                    :class="$store.cartCheckout.selectedPaymentMethod === '{{ esc_js($gateway->id) }}' ? 'text-[15px] font-bold' : ''"
                  >
                    {!! wp_kses_post($gateway->get_title()) !!}
                  </div>
                </label>
                <div>
                  @if ($paymentFieldsMarkup !== '')
                    <div
                      class="pay-method text-green-default/80 text-[13px] leading-5"
                      x-show="$store.cartCheckout.selectedPaymentMethod === '{{ esc_js($gateway->id) }}'"
                    >
                      {!! $paymentFieldsMarkup !!}
                    </div>
                  @endif
                </div>
              </div>
            @endforeach

            @php
              do_action('woocommerce_checkout_terms_and_conditions');
            @endphp
            <div class="sr-only">
              <input type="hidden" name="woocommerce_checkout_update_totals" value="1" />
            </div>
          </div>
          @php
            do_action('woocommerce_after_checkout_form', $checkoutInstance);
          @endphp
        </div>
      </div>

      <div
        x-data="{ hasCartCheckout: Boolean(window.cartCheckoutConfig) }"
        x-cloak
        x-show="hasCartCheckout"
        class="sticky bottom-(--header-bottom-height) flex items-center justify-between gap-5 py-3 bg-background border-t border-[#E0E0D7] lg:card lg:p-8 lg:rounded-4xl lg:self-start lg:top-(--header-top-height) lg:bottom-[unset]"
      >
        <div class="flex w-full items-center gap-4 lg:flex-col lg:items-stretch">
          <div class="max-lg:hidden">
            <span class="h4-desktop mb-5 block">{{ __('Total', 'sage-front') }}</span>
            <div
              class="space-y-3 mb-5 border-b border-[#E0E0D7] pb-5"
              x-show="$store.cartCheckout.currentStep !== 1 && !$store.cartCheckout.isCartEmpty"
            >
              <template x-for="item in $store.cartCheckout.items" :key="`summary-${item.key}`">
                <div class="flex gap-3">
                  {{-- Image --}}
                  <div class="flex-none w-18.75 h-21 overflow-hidden border border-[#F7F7F6]">
                    <img
                      :src="item.image || '{{ esc_js($placeholderImage) }}'"
                      :alt="item.imageAlt || item.name"
                      class="size-full object-cover"
                      width="75" height="84"
                    />
                  </div>
                  {{-- Details --}}
                  <div class="text-body-16 flex-1">
                    <div class="flex gap-1 items-center">
                      <span class="text-gray-4 min-w-5 text-[13px] leading-3.75 font-semibold" x-text="`x${item.quantity}`"></span>
                      <span class="font-bold text-green-default" x-text="item.name"></span>
                    </div>
                    <span
                      class="text-[#426E59]"
                      x-show="item.summary"
                      x-text="item.summary">
                    </span>
                  </div>
                  {{-- Price --}}
                  <div class="text-gray-1 self-end font-bold" x-text="item.lineTotal.formatted"></div>
                </div>
              </template>
            </div>
            <div class="text-green-default space-y-3 text-body-16">
              <div class="flex items-center justify-between">
                <span x-text="$store.cartCheckout.totals.shipping.label"></span>
                <span class="h4-desktop" x-text="$store.cartCheckout.totals.shipping.amount.formatted"></span>
              </div>
              <div class="flex items-center justify-between">
                <span x-text="$store.cartCheckout.totals.discount.label"></span>
                <span class="h4-desktop" x-text="$store.cartCheckout.totals.discount.amount.formatted"></span>
              </div>
              <div class="flex items-center justify-between">
                <span x-text="$store.cartCheckout.totals.total.label"></span>
                <span class="h4-desktop" x-text="$store.cartCheckout.totals.total.amount.formatted"></span>
              </div>
            </div>
          </div>

          <div
            class="min-w-0 flex-1 text-green-default text-center text-body-15 md:text-body-16 lg:hidden"
            x-show="$store.cartCheckout.currentStep === 1 && !$store.cartCheckout.isCartEmpty"
          >
            {!! sprintf(__('Total: %s', 'sage-front'), '<span class="text-green-dark text-[16px] md:text-[20px] font-extrabold" x-text="$store.cartCheckout.formattedTotal"></span>') !!}
            @if (!empty($cartCheckout['deliverySummary']))
              <p class="text-green-default/75 text-xs" x-show="!$store.cartCheckout.isCartEmpty">
                {!! wp_kses_post($cartCheckout['deliverySummary']) !!}
              </p>
            @endif
          </div>

          <button
            type="button"
            class="bg-purple-dark flex-1 rounded-full px-6 py-4 text-[13px] font-semibold text-white transition disabled:opacity-60"
            x-show="$store.cartCheckout.currentStep === 1 && !$store.cartCheckout.isCartEmpty"
            :disabled="$store.cartCheckout.isLoading"
            @click="$store.cartCheckout.goToStep(2)"
          >
            {{ __('Checkout', 'sage-front') }}
          </button>

          <button
            type="button"
            class="bg-purple-dark flex-1 rounded-full px-6 py-4 text-[13px] font-semibold text-white transition disabled:opacity-60"
            x-show="$store.cartCheckout.currentStep === 2"
            @click="
              if ($store.cartCheckout.validateInfoStep()) {
                $store.cartCheckout.goToStep(3);
              }
            "
          >
            {{ __('Continue to payment', 'sage-front') }}
          </button>

          <button
            type="submit"
            form="sage-cart-checkout-form"
            class="bg-purple-dark flex-1 rounded-full px-6 py-4 text-[13px] font-semibold text-white transition disabled:opacity-60"
            x-show="$store.cartCheckout.currentStep === 3"
            :disabled="$store.cartCheckout.isSubmitting"
          >
            <span
              x-text="$store.cartCheckout.isSubmitting ? '{{ esc_js(__('Processing...', 'sage-front')) }}' : '{{ esc_js($orderButtonText) }}'"
            ></span>
          </button>
        </div>
      </div>
    </div>
  </form>
</section>

@php
  do_action('woocommerce_after_cart');
@endphp
