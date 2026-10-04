@php
  $cartCount = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
  $isMobileNavigation = wp_is_mobile();

  // On the front page the logo would link to the page it sits on, so it is
  // rendered as plain markup there instead of a self-referencing link.
  $isHome = is_front_page();
  $logoTag = $isHome ? 'span' : 'a';
@endphp
<header id="header" class="bg-background sticky top-0 z-60 border-b border-[#426E59]">
  {{-- The dark bar: delivery promise and countdown on the left, the secondary
       menu on the right. 48px tall on the desktop design, which --header-top-height
       accounts for. --}}
  <div class="bg-primary">
    <div class="bx-container flex items-center justify-between gap-6 py-2 lg:py-3">
      @include('partials.delivery-timer', ['deliveryTimer' => $deliveryTimer])

      @if ($topBarMenu && !$isMobileNavigation)
        @include('partials.header-navigation-top', ['menu' => $topBarMenu])
      @endif
    </div>
  </div>

  <div class="bg-background bx-container flex items-center justify-between gap-4 py-3 lg:h-23 lg:gap-12 lg:py-0">
    <div class="flex min-w-0 items-center gap-4 lg:gap-12">
      @if (!empty($logos?->dark))
        <{{ $logoTag }}
          @unless ($isHome) href="{{ home_url('/') }}" @endunless
          class="shrink-0 text-lg font-semibold tracking-[0.16em] text-[#244734] uppercase"
        >
          <picture>
            @if (!empty($logos->darkLg))
              <source
                media="(min-width: 96rem)"
                srcset="{{ $logos->darkLg->src('full') }}"
                @if (($logos->darkLg->width() ?? 0) > 0) width="{{ $logos->darkLg->width() }}" @endif
                @if (($logos->darkLg->height() ?? 0) > 0) height="{{ $logos->darkLg->height() }}" @endif
              />
            @endif
            <img
              src="{{ $logos->dark->src('medium') }}"
              alt="{{ $logos->dark->alt() ?: $siteName }}"
              width="66"
              height="31"
              class="h-auto w-16.5 2xl:w-auto"
            />
          </picture>
        </{{ $logoTag }}>
      @endif

      @if ($menu)
        @unless ($isMobileNavigation)
          @include('partials.header-navigation-desktop', ['menu' => $menu])
        @endunless
      @endif
    </div>

    <div class="flex shrink-0 items-center gap-3 lg:gap-6">
      {{-- Search is a desktop affordance in the design; on small screens the
           footer's bottom bar carries it instead. --}}
      <button
        type="button"
        data-search-toggle
        @click="search()"
        :aria-expanded="isSearchOpen.toString()"
        aria-controls="header-search-panel"
        aria-label="{{ __('Search', 'sage-front') }}"
        class="text-green-dark hidden transition-colors duration-200 hover:text-[#426E59] lg:block"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <a
        href="{{ function_exists('wc_get_cart_url') ? esc_url(wc_get_cart_url()) : '/cart/' }}"
        target="_self"
        aria-label="{{ __('Cart', 'sage-front') }}"
        class="counter-for-cart text-gray-6 relative block text-[14px] leading-4.75"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M8 22C8.55228 22 9 21.5523 9 21C9 20.4477 8.55228 20 8 20C7.44772 20 7 20.4477 7 21C7 21.5523 7.44772 22 8 22Z" stroke="#0C3421" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M19 22C19.5523 22 20 21.5523 20 21C20 20.4477 19.5523 20 19 20C18.4477 20 18 20.4477 18 21C18 21.5523 18.4477 22 19 22Z" stroke="#0C3421" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M2.0498 2.05H4.0498L6.7098 14.47C6.80738 14.9249 7.06048 15.3315 7.42552 15.6199C7.79056 15.9082 8.24471 16.0604 8.7098 16.05H18.4898C18.945 16.0493 19.3863 15.8933 19.7408 15.6078C20.0954 15.3224 20.3419 14.9245 20.4398 14.48L22.0898 7.05H5.1198" stroke="#0C3421" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span
          data-count="{{ $cartCount }}"
          class="text-background absolute bottom-2 left-2 flex size-5.5 items-center justify-center rounded-full bg-[#EB5757] text-center text-[13px] leading-none font-semibold before:content-[attr(data-count)] data-[count='0']:hidden"
        ></span>
      </a>
      @if (!empty($phone))
        <a
          href="{{ $phone['href'] }}"
          class="border-green-default bg-background text-green-default inline-flex items-center justify-center rounded-full border-2 px-5.5 py-1.5 text-[14px] font-semibold transition-all duration-200 lg:h-9.5 lg:w-38 lg:px-2 lg:py-0"
        >
          <span>{{ $phone['value'] }}</span>
        </a>
      @endif

      @if ($menu && $isMobileNavigation)
        @include('partials.header-navigation-mobile', ['menu' => $menu, 'topBarMenu' => $topBarMenu])
      @endif
    </div>
  </div>

  @include('partials.header-search')
</header>
