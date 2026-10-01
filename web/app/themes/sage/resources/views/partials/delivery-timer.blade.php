@if (!empty($deliveryTimer))
  {{--
    The dark bar around this lives in sections.header, which also hangs the top
    bar navigation next to it. Only the timer itself is here, because
    resources/js/modules/delivery-timer.ts looks up __prompt, __time and __next
    inside the [data-delivery-timer] element and toggles the prompt's `hidden`
    attribute -- so these three classes and their nesting have to stay put.

    On the desktop design the prompt sits on one line with the countdown:
    heading, 24px gap, "order within", 6px gap, the clock.
  --}}
  <div
    class="delivery-timer flex flex-1 items-center justify-between gap-4 lg:flex-none lg:justify-start lg:gap-1.5"
    data-delivery-timer="{!! esc_attr(wp_json_encode($deliveryTimer, JSON_UNESCAPED_UNICODE)) !!}"
  >
    <div class="delivery-timer__prompt flex flex-col lg:flex-row lg:items-baseline lg:gap-6">
      <p class="delivery-timer__title text-gray-6 text-[16px] leading-4.75 lg:text-[18px] lg:leading-normal">{{ __('Flower delivery even today', 'sage-front') }}</p>
      <span
        class="delivery-timer__subtitle text-gray-4 text-[14px] leading-3.75 lg:text-[15px] lg:leading-normal"
        >{{ __('Order within:', 'sage-front') }}</span
      >
    </div>

    <div
      class="delivery-timer__time text-gray-6 text-[19px] leading-5.25 font-semibold lg:text-[18px] lg:leading-normal"
      aria-live="polite"
    >
      02:14:36
    </div>
    <p class="delivery-timer__next text-gray-6 [&>span]:font-medium [&>span]:text-white text-[16px] leading-4.75" hidden></p>
  </div>
@endif
