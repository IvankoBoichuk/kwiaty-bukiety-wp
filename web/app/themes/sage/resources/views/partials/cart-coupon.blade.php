{{--
  The coupon field, built out of the same card/input/button pieces as the rest
  of the checkout. WooCommerce's own form-coupon.php ships with none of the
  theme's classes, so it rendered as bare browser controls; this one talks to
  the Store API through the cart/checkout store instead of posting the page.
--}}
<div class="card mb-4 lg:rounded-4xl lg:p-8">
  <h2 class="text-green-default h3-mobile md:h4-desktop mb-5">{{ __('Coupon code', 'sage-front') }}</h2>

  <ul class="mb-3 flex flex-wrap gap-2" x-cloak x-show="$store.cartCheckout.coupons.length > 0">
    <template x-for="coupon in $store.cartCheckout.coupons" :key="coupon.code">
      <li
        class="border-green-easy text-green-default flex items-center gap-2 rounded-full border bg-white px-3 py-1.5 text-[13px] leading-4"
      >
        <span class="font-semibold uppercase" x-text="coupon.code"></span>
        <span class="text-green-easy" x-text="`-${coupon.discount.formatted}`"></span>
        <button
          type="button"
          class="text-green-easy"
          :disabled="$store.cartCheckout.isCouponLoading"
          @click="$store.cartCheckout.removeCoupon(coupon.code)"
          :aria-label="`{{ esc_js(__('Remove coupon', 'sage-front')) }} ${coupon.code}`"
        >
          <svg width="14" height="14">
            <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#close"></use>
          </svg>
        </button>
      </li>
    </template>
  </ul>

  <div class="flex gap-2">
    <label class="sr-only" for="sage-coupon-code">{{ __('Coupon code', 'sage-front') }}</label>
    <input
      id="sage-coupon-code"
      type="text"
      autocomplete="off"
      autocapitalize="characters"
      spellcheck="false"
      placeholder="{{ esc_attr__('Enter your code', 'sage-front') }}"
      class="bg-background text-green-default focus:border-green-easy min-w-0 flex-1 rounded-[14px] border border-[#DDD7CF] px-4 py-3 text-[14px] leading-5 uppercase placeholder:text-[#A4A094] placeholder:normal-case focus:outline-none"
      x-model="$store.cartCheckout.couponCode"
      :disabled="$store.cartCheckout.isCouponLoading"
      @keydown.enter.prevent="$store.cartCheckout.applyCoupon()"
      @input="$store.cartCheckout.couponError = ''"
    />
    <button
      type="button"
      class="bg-purple-dark shrink-0 rounded-full px-6 text-[13px] font-semibold text-white transition disabled:opacity-60"
      :disabled="$store.cartCheckout.isCouponLoading"
      @click="$store.cartCheckout.applyCoupon()"
    >
      {{ __('Apply', 'sage-front') }}
    </button>
  </div>

  <p
    class="mt-1 text-[12px] leading-4 text-[#C6463D]"
    x-cloak
    x-show="$store.cartCheckout.couponError"
    x-text="$store.cartCheckout.couponError"
  ></p>
</div>
