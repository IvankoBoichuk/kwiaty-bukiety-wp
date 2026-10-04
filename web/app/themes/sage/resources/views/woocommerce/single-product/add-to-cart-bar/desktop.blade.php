@php
  $decreaseQuantityLabel = esc_attr__('Decrease quantity', 'sage-front');
  $increaseQuantityLabel = esc_attr__('Increase quantity', 'sage-front');
  $addingToCartText = __('Adding...', 'sage-front');
  $addToCartText = __('Add to cart', 'sage-front');
@endphp

<div x-data x-cloak class="bx-container shadow-card space-y-5 divide-y divide-[#E0E0D7] rounded-4xl bg-white p-5">
  <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 pb-5">
    <div class="text-green-default flex flex-1 gap-2">
      <span class="mt-0.5">{{ __('Total price:', 'sage-front') }}</span>
      <span class="h4-desktop" x-text="$store.productPurchase.formattedTotal"></span>
    </div>

    <div class="bg-background flex h-13 items-center gap-2.5 rounded-2xl border border-[#E0E0D7] px-4 py-1">
      <button
        type="button"
        class="flex size-5 items-center justify-center text-green-easy transition-all disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="$store.productPurchase.quantity <= 1 || $store.productPurchase.isSubmitting"
        aria-label="{{ $decreaseQuantityLabel }}"
        @click="$store.productPurchase.decrement()"
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M15.8333 10H4.16658" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <span class="text-green-default h4-desktop min-w-8 text-center" x-text="$store.productPurchase.quantity"></span>

      <button
        type="button"
        class="flex size-5 items-center justify-center text-green-easy transition-all disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="$store.productPurchase.isSubmitting"
        aria-label="{{ $increaseQuantityLabel }}"
        @click="$store.productPurchase.increment()"
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M10 4.16699V15.8337" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M15.8335 10H4.16683" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </div>

    <button
      type="button"
      class="text-gray-6 flex min-h-13 min-w-72 items-center justify-center gap-2.5 rounded-full bg-purple-dark px-9 py-3 text-center transition-all disabled:cursor-not-allowed disabled:opacity-60"
      :disabled="!$store.productPurchase.canSubmit || $store.productPurchase.isSubmitting"
      @click="$store.productPurchase.submit()"
    >
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M6.66683 18.3337C7.12707 18.3337 7.50016 17.9606 7.50016 17.5003C7.50016 17.0401 7.12707 16.667 6.66683 16.667C6.20659 16.667 5.8335 17.0401 5.8335 17.5003C5.8335 17.9606 6.20659 18.3337 6.66683 18.3337Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M15.8333 18.3337C16.2936 18.3337 16.6667 17.9606 16.6667 17.5003C16.6667 17.0401 16.2936 16.667 15.8333 16.667C15.3731 16.667 15 17.0401 15 17.5003C15 17.9606 15.3731 18.3337 15.8333 18.3337Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M1.7085 1.70801H3.37516L5.59183 12.058C5.67314 12.4371 5.88405 12.7759 6.18826 13.0162C6.49246 13.2565 6.87092 13.3833 7.2585 13.3747H15.4085C15.7878 13.3741 16.1556 13.2441 16.451 13.0062C16.7465 12.7683 16.9519 12.4368 17.0335 12.0663L18.4085 5.87467H4.26683" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>

      <span
        class="font-medium"
        x-text="$store.productPurchase.isSubmitting ? @js($addingToCartText) : @js($addToCartText)"
      ></span>
    </button>
  </div>

  <div class="flex flex-wrap items-center gap-x-10 gap-y-3 text-[#5A6561]">
    <div class="flex items-center gap-3">
      <svg width="30" height="20" class="shrink-0" viewBox="0 0 30 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#truck-wind"></use>
      </svg>
      <span>{{ __('Free shipping', 'sage-front') }}</span>
    </div>

    <div class="flex items-center gap-3">
      <svg width="32" height="22" class="shrink-0" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#time-wind"></use>
      </svg>
      <span>{{ __('Same day', 'sage-front') }}</span>
    </div>

    <div class="flex items-center gap-3">
      <svg width="28" height="22" class="shrink-0" viewBox="0 0 28 22" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#card"></use>
      </svg>
      <span>{{ __('Online payment', 'sage-front') }}</span>
    </div>
  </div>
</div>
