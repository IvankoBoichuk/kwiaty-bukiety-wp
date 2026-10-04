@php
  $wrapperClass = $wrapperClass ?? 'bg-background bx-container flex items-center justify-between gap-5 py-3';
@endphp

<div x-data x-cloak class="{{ $wrapperClass }}">
  <div class="flex shrink-0 items-center gap-3">
    <button
      type="button"
      class="text-green-default border-green-easy flex size-9 items-center justify-center rounded-xl border transition-all disabled:opacity-50"
      aria-label="Zmniejsz ilosc"
      @click="$store.productPurchase.decrement()"
    >
      <svg width="20" height="20" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7 14H21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <span class="text-gray-1 h3-mobile text-center" x-text="$store.productPurchase.quantity"></span>

    <button
      type="button"
      class="text-green-default border-green-easy flex size-9 items-center justify-center rounded-xl border transition-all"
      aria-label="Zwieksz ilosc"
      @click="$store.productPurchase.increment()"
    >
      <svg width="20" height="20" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M14 7V21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M7 14H21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>
  </div>

  <button
    type="button"
    class="text-gray-6 flex max-w-3xs flex-1 items-center justify-between gap-2.5 rounded-full bg-purple-dark px-6 py-3 text-left transition-all disabled:cursor-not-allowed disabled:opacity-60"
    :disabled="!$store.productPurchase.canSubmit || $store.productPurchase.isSubmitting"
    @click="$store.productPurchase.submit()"
  >
    <span
      class="text-[13px] leading-none"
      x-text="$store.productPurchase.isSubmitting ? 'Dodawanie...' : 'Dodaj do koszyka'"
    ></span>
    <span class="text-[16px] font-bold text-nowrap" x-text="$store.productPurchase.formattedTotal"></span>
  </button>
</div>
