@php
  $cartCount = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
@endphp
<header id="header" class="bg-background sticky top-0 z-60 border-b border-[#426E59]">
  @include('partials.delivery-timer', ['deliveryTimer' => $deliveryTimer])
  @php($isMobileNavigation = wp_is_mobile())

  <div
    class="bg-background bx-container flex items-center justify-between gap-4 py-3 {{ $menu && !$isMobileNavigation ? 'lg:grid lg:grid-cols-[auto_1fr_auto] lg:gap-8' : '' }}"
  >
    @if (!empty($logos?->dark))
      <a href="{{ home_url('/') }}" class="text-lg font-semibold tracking-[0.16em] text-[#244734] uppercase">
        <img src="{{ $logos->dark->src('medium') }}" alt="{{ $logos->dark->alt() ?: $siteName }}" width="66" height="31" />
      </a>
    @endif

    @if ($menu)
      @unless ($isMobileNavigation)
        @include('partials.header-navigation-desktop', ['menu' => $menu])
      @endunless
    @endif

    <div class="flex items-center gap-3">
      <a href="/cart/" target="_self" aria-label="Cart" class="counter-for-cart text-gray-6 relative block text-[14px] leading-4.75">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M8 22C8.55228 22 9 21.5523 9 21C9 20.4477 8.55228 20 8 20C7.44772 20 7 20.4477 7 21C7 21.5523 7.44772 22 8 22Z" stroke="#0C3421" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M19 22C19.5523 22 20 21.5523 20 21C20 20.4477 19.5523 20 19 20C18.4477 20 18 20.4477 18 21C18 21.5523 18.4477 22 19 22Z" stroke="#0C3421" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M2.0498 2.05H4.0498L6.7098 14.47C6.80738 14.9249 7.06048 15.3315 7.42552 15.6199C7.79056 15.9082 8.24471 16.0604 8.7098 16.05H18.4898C18.945 16.0493 19.3863 15.8933 19.7408 15.6078C20.0954 15.3224 20.3419 14.9245 20.4398 14.48L22.0898 7.05H5.1198" stroke="#0C3421" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span data-count="{{ $cartCount }}" class="text-background absolute bottom-2 left-2 flex size-5.5 items-center justify-center rounded-full bg-[#EB5757] text-center text-[13px] leading-none font-semibold before:content-[attr(data-count)] data-[count='0']:hidden"></span>
      </a>
      @if (!empty($phone))
        <a
          href="{{ $phone['href'] }}"
          class="border-green-default bg-background text-green-default inline-flex items-center justify-center rounded-full border-2 px-5.5 py-1.5 text-[14px] font-semibold transition-all duration-200"
        >
          <span>{{ $phone['value'] }}</span>
        </a>
      @endif

      @if ($menu && $isMobileNavigation)
        @include('partials.header-navigation-mobile', ['menu' => $menu])
      @endif
    </div>
  </div>
</header>
