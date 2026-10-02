@php
  /**
   * @var WC_Order|false $order
   */
  $deliveryDateRaw = (string) $order->get_meta('delivery_date');
  $deliveryTimeRaw = (string) $order->get_meta('delivery_time');

  $deliveryDate = $deliveryDateRaw !== '' ? strtotime($deliveryDateRaw) : false;
  $deliveryTimeLabel =
    $deliveryTimeRaw !== '' ? $deliveryTimeRaw : ($deliveryDate ? date_i18n('H:i', $deliveryDate) : '');
  $orderNumber = $order instanceof WC_Order ? $order->get_order_number() : '';
@endphp

<div class="woocommerce-order">
  @if ($order instanceof WC_Order)
    @php(do_action('woocommerce_before_thankyou', $order->get_id()))
    @if ($order->has_status('failed'))
      <div class="mx-auto flex w-full max-w-md flex-col gap-4 px-3 py-8">
        <p class="text-dark-text text-center text-[22px] leading-7 font-semibold">
          {{ __('Payment failed', 'woocommerce') }}
        </p>
        <p class="text-center text-[15px] leading-6 text-[#426E59]">
          {{
            __(
              'Please try to pay for the order again or choose a different payment method.',
              'sage-front',
            )
          }}
        </p>
        <div class="flex justify-center gap-3">
          @include('partials.button',
            [
              'text' => __('Pay for the order', 'woocommerce'),
              'link' => $order->get_checkout_payment_url(),
              'variant' => 'green',
              'size' => 'lg',
              'target' => '_self',
              'showIcon' => false
            ])
        </div>
      </div>
    @else
      <section
        class="relative -mt-2.5 flex flex-col gap-8 md:-mt-4 lg:mt-8 lg:grid lg:grid-cols-2 lg:items-center lg:gap-12"
      >
        {{-- The intrinsic size of resources/images/flowers-on-the-table.jpg. --}}
        <img
          src="{{ get_template_directory_uri() . '/resources/images/flowers-on-the-table.jpg' }}"
          alt="{{ esc_attr__('Order Confirmation', 'sage-front') }}"
          width="805"
          height="423"
          class="h-full w-screen max-w-max self-center object-cover lg:order-last lg:h-auto lg:w-full lg:max-w-full lg:self-auto"
        />

        <div class="flex grow flex-col gap-6 md:gap-10 lg:mx-auto lg:w-full lg:max-w-130 lg:grow-0">
          <div class="flex flex-col items-center gap-4">
            <h2 class="h2-mobile text-dark-text text-center md:h2-desktop lg:text-left">
              {{ __('Order Successful!', 'sage-front') }}
            </h2>
            <p class="text-3.5 text-center font-normal text-[#426E59] md:max-w-112.5 md:text-base md:text-[16px]">{{
              implode(' ', [
                __('Thank you for choosing us for your flower delivery.', 'sage-front'),
                __('Your order has been successfully placed, and a confirmation has been sent to your email address.', 'sage-front'),
              ])
            }}</p>
          </div>
          @if ($deliveryDate || $deliveryTimeLabel !== '')
            <div class="mb-6 flex flex-col gap-1 md:mb-0 lg:mb-5.5">
              <div class="text-3.5 text-center font-normal text-[#6D9586] md:text-base">
                {{ __('Expected delivery', 'sage-front') }}
              </div>
              <div class="text-body-15 text-dark-text flex justify-center gap-3 font-bold md:text-lg md:text-[22px]">
                @if ($deliveryDate)
                  <span> {{ date_i18n('d.m.Y', $deliveryDate) }} </span>
                @endif

                @if ($deliveryTimeLabel !== '')
                  <span class="time"> {{ $deliveryTimeLabel }} </span>
                @endif
              </div>
            </div>
          @endif
          <div class="mt-auto w-full md:mx-auto md:max-w-92 lg:mt-0">
            @include('partials.button',
              [
                'text' => __('Back to Home Page', 'sage-front'),
                'link' => home_url('/'),
                'variant' => 'border',
                'size' => 'lg',
                'target' => '_self',
                'showIcon' => false
              ])
          </div>
        </div>
      </section>
    @endif
    @php(do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()))
    @php(do_action('woocommerce_thankyou', $order->get_id()))
  @else
    <section class="bg-background">
      <div class="mx-auto flex w-full max-w-md flex-col gap-4 px-3 py-8 text-center">
        <h2 class="h2-mobile text-dark-text">{{ __('Thank You for Your Order', 'sage-front') }}</h2>
        <p class="text-3.5 font-normal text-[#426E59]">{{
          __(
            'Your order confirmation has been saved.',
            'sage-front',
          )
        }}</p>
        <div class="mx-auto">
          @include('partials.button',
            [
              'text' => __('Back to Home Page', 'sage-front'),
              'link' => home_url('/'),
              'variant' => 'border',
              'size' => 'lg',
              'target' => '_self',
              'showIcon' => false
            ])
        </div>
      </div>
    </section>
  @endif
</div>
