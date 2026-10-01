@php
  $cartCount = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
  $orderButtonText = apply_filters('woocommerce_order_button_text', __('Buy and pay', 'sage-front'));
  $bottomNavigation = [
    [
      'id' => 'home',
      'name' => 'Home',
      'type' => 'link',
      'url' => home_url('/'),
      'target' => '_self',
      'count' => 0,
    ],
    [
      'id' => 'search',
      'name' => 'Search',
      'type' => 'button',
      'action' => 'search',
      'count' => 0,
    ],
    [
      'id' => 'cart',
      'name' => 'Cart',
      'type' => 'link',
      'url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart'),
      'target' => '_self',
      'count' => $cartCount,
    ],
    [
      'id' => 'menu',
      'name' => 'Menu',
      'type' => 'button',
      'action' => 'toggleMenu',
      'count' => 0,
    ],
  ];
@endphp
<footer class="bx-container grid gap-12 bg-[#03130B] py-5 pt-8 text-white">
  <div
    class="grid gap-14 md:grid-cols-[auto_auto] md:gap-x-10.5 md:gap-y-12 lg:flex lg:items-start lg:justify-between lg:gap-5"
  >
    <div class="order-1 flex items-center gap-8.75 md:flex-col md:items-start md:gap-3.5 lg:w-47">
      @if (!empty($logos->light))
        <a href="{{ home_url('/') }}" aria-label="{{ $siteName }}">
          <picture>
            @if (!empty($logos->lightLg))
              <source
                media="(min-width: 96rem)"
                srcset="{{ $logos->lightLg->src('full') }}"
                @if (($logos->lightLg->width() ?? 0) > 0) width="{{ $logos->lightLg->width() }}" @endif
                @if (($logos->lightLg->height() ?? 0) > 0) height="{{ $logos->lightLg->height() }}" @endif
              />
            @endif
            <img
              src="{{ $logos->light->src('medium') }}"
              alt="{{ $logos->light->alt() ?? $siteName }}"
              width="90"
              height="43"
              class="h-auto w-22.5 2xl:w-auto"
            />
          </picture>
        </a>
      @else
        <a
          href="{{ home_url('/') }}"
          class="text-[22px] leading-none font-semibold text-white"
          aria-label="{{ $siteName }}"
        >
          {{ $siteName }}
        </a>
      @endif

      <p class="text-gray-4 text-[15px] leading-4.5 text-balance">Kwiaty Bukiety - dostawa na terenie calej Polski</p>
    </div>

    <ul class="order-2 grid w-max items-center gap-5 md:grid-cols-[1fr_auto] lg:order-4">
      @foreach ($contacts as $item)
        <li class="font-semibold {{ $item['type'] === 'text' ? 'md:row-span-2 md:col-start-2 md:row-start-1' : '' }}">
          <div class="text-gray-4 flex items-center gap-3 text-[15px] leading-4.5">
            @switch ($item['type'])
              @case ('phone')
                <svg class="shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M21 16.92V19.92C21.0001 20.1985 20.942 20.4741 20.8294 20.7288C20.7168 20.9835 20.5522 21.2116 20.3464 21.3984C20.1407 21.5852 19.8979 21.7266 19.6338 21.8136C19.3696 21.9006 19.0901 21.9312 18.8134 21.9034C15.7428 21.5697 12.7931 20.5211 10.2049 18.8425C7.79628 17.3127 5.75464 15.2711 4.22492 12.8625C2.54013 10.2622 1.491 7.29613 1.16492 4.20999C1.13739 3.93412 1.16773 3.65559 1.25392 3.39207C1.3401 3.12855 1.48024 2.88602 1.66543 2.68017C1.85062 2.47433 2.07677 2.30976 2.32935 2.19703C2.58193 2.0843 2.85526 2.02588 3.13159 2.02568H6.13159C6.61482 2.02092 7.08328 2.19071 7.45178 2.50351C7.82028 2.8163 8.06413 3.25163 8.13825 3.72912C8.27643 4.77764 8.5322 5.80723 8.90159 6.79818C9.03425 7.14856 9.06315 7.52974 8.98488 7.89618C8.90662 8.26262 8.72445 8.59874 8.46075 8.86499L7.19159 10.1342C8.61336 12.6345 10.6846 14.7058 13.1849 16.1275L14.4541 14.8583C14.7203 14.5946 15.0565 14.4124 15.4229 14.3342C15.7893 14.2559 16.1705 14.2848 16.5209 14.4175C17.5119 14.7868 18.5414 15.0426 19.59 15.1808C20.0726 15.2556 20.512 15.5042 20.8256 15.8794C21.1391 16.2545 21.3051 16.7314 21.2925 17.22L21 16.92Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <a
                  href="tel:{{ preg_replace('/\s+/', '', $item['value']) }}"
                  class="text-sm md:text-base"
                  >{{ $item['value'] }}</a
                >
                @break
              @case ('email')
                <svg class="shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M4 6H20C20.5304 6 21.0391 6.21071 21.4142 6.58579C21.7893 6.96086 22 7.46957 22 8V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V8C2 7.46957 2.21071 6.96086 2.58579 6.58579C2.96086 6.21071 3.46957 6 4 6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M22 8L12 13L2 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <a
                  href="mailto:{{ $item['value'] }}"
                  class="text-sm whitespace-break-spaces md:text-base"
                  >{{ $item['value'] }}</a
                >
                @break
              @default
                <svg class="shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="1.5" />
                </svg>
                <span class="text-sm md:text-base">{{ $item['value'] }}</span>
            @endswitch
          </div>

          @if (!empty($item['details']))
            <p class="text-gray-3 mt-1.5 pl-9.25 text-xs md:text-sm">
              @foreach ($item['details'] as $detail)
                <span class="block">{{ $detail }}</span>
              @endforeach
            </p>
          @endif
        </li>
      @endforeach
    </ul>

    <div class="text-gray-4 order-3 col-span-full grid gap-12">
      @if (!empty($footerMenus['main']) || !empty($footerMenus['secondary']))
        <nav
          aria-label="Footer navigation"
          class="grid gap-14 text-[13px] leading-3.75 font-semibold uppercase md:grid-cols-2 lg:gap-12"
        >
          @if (!empty($footerMenus['main']))
            <ul class="columns-2 space-y-5 gap-x-6 lg:gap-x-12">
              @foreach ($footerMenus['main'] as $item)
                <li><a href="{{ $item['url'] }}" class="hover:text-white">{{ $item['label'] }}</a></li>
              @endforeach
            </ul>
          @endif

          @if (!empty($footerMenus['secondary']))
            <ul class="columns-2 space-y-5 gap-x-6 lg:gap-x-12">
              @foreach ($footerMenus['secondary'] as $item)
                <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
              @endforeach
            </ul>
          @endif
        </nav>
      @endif
    </div>
  </div>

  @if (!empty($socials))
    <ul class="flex flex-wrap justify-center gap-12">
      @foreach ($socials as $item)
        <li>
          <a
            href="{{ $item['url'] }}"
            target="{{ $item['target'] ?: '_blank' }}"
            rel="noopener noreferrer"
            aria-label="{{ $item['label'] }}"
            class="flex size-8 items-center justify-center text-white transition-opacity hover:opacity-70"
          >
            @switch ($item['icon'])
              @case ('instagram')
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M16.0364 0H15.9636C7.14713 0 0 7.14713 0 15.9636V16.0364C0 24.8529 7.14713 32 15.9636 32H16.0364C24.8529 32 32 24.8529 32 16.0364V15.9636C32 7.14713 24.8529 0 16.0364 0Z" fill="#BDBDBD" />
                  <path d="M20.9515 6.51025H11.0498C8.31428 6.51025 6.08887 8.73567 6.08887 11.4712V20.5298C6.08887 23.2653 8.31428 25.4907 11.0498 25.4907H20.9515C23.687 25.4907 25.9124 23.2653 25.9124 20.5298V11.4712C25.9124 8.73567 23.687 6.51025 20.9515 6.51025ZM7.83893 11.4712C7.83893 9.70095 9.27956 8.26032 11.0498 8.26032H20.9515C22.7217 8.26032 24.1624 9.70095 24.1624 11.4712V20.5298C24.1624 22.3 22.7217 23.7407 20.9515 23.7407H11.0498C9.27956 23.7407 7.83893 22.3 7.83893 20.5298V11.4712Z" fill="#141414" />
                  <path d="M16.0003 20.6138C18.5441 20.6138 20.6148 18.5442 20.6148 15.9993C20.6148 13.4543 18.5452 11.3848 16.0003 11.3848C13.4553 11.3848 11.3857 13.4543 11.3857 15.9993C11.3857 18.5442 13.4553 20.6138 16.0003 20.6138ZM16.0003 13.1359C17.5799 13.1359 18.8647 14.4207 18.8647 16.0004C18.8647 17.58 17.5799 18.8648 16.0003 18.8648C14.4206 18.8648 13.1358 17.58 13.1358 16.0004C13.1358 14.4207 14.4206 13.1359 16.0003 13.1359Z" fill="#141414" />
                  <path d="M21.0413 12.1302C21.7261 12.1302 22.2842 11.573 22.2842 10.8869C22.2842 10.2007 21.7272 9.64355 21.0413 9.64355C20.3554 9.64355 19.7983 10.2007 19.7983 10.8869C19.7983 11.573 20.3554 12.1302 21.0413 12.1302Z" fill="#141414" />
                </svg>
                @break
              @case ('facebook')
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M0 16.0893C0 24.044 5.77733 30.6587 13.3333 32V20.444H9.33333V16H13.3333V12.444C13.3333 8.444 15.9107 6.22267 19.556 6.22267C20.7107 6.22267 21.956 6.4 23.1107 6.57733V10.6667H21.0667C19.1107 10.6667 18.6667 11.644 18.6667 12.8893V16H22.9333L22.2227 20.444H18.6667V32C26.2227 30.6587 32 24.0453 32 16.0893C32 7.24 24.8 0 16 0C7.2 0 0 7.24 0 16.0893Z" fill="#BDBDBD" />
                </svg>
                @break
              @case ('whatsapp')
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M16.0364 0H15.9636C7.14713 0 0 7.14713 0 15.9636V16.0364C0 24.8529 7.14713 32 15.9636 32H16.0364C24.8529 32 32 24.8529 32 16.0364V15.9636C32 7.14713 24.8529 0 16.0364 0Z" fill="#BDBDBD" />
                  <path d="M18.8763 20.0941C14.7808 20.0941 11.4489 16.761 11.4478 12.6656C11.4489 11.6274 12.2942 10.7832 13.3301 10.7832C13.4366 10.7832 13.542 10.7922 13.6429 10.8101C13.8649 10.8471 14.0757 10.9222 14.2696 11.0355C14.2976 11.0523 14.3167 11.0792 14.3212 11.1106L14.7539 13.8382C14.7595 13.8708 14.7494 13.9021 14.7281 13.9257C14.4893 14.1903 14.1844 14.3809 13.8447 14.4762L13.681 14.5221L13.7427 14.6802C14.301 16.1018 15.4378 17.2375 16.8605 17.798L17.0186 17.8608L17.0645 17.6971C17.1598 17.3574 17.3504 17.0525 17.615 16.8137C17.6341 16.7957 17.6599 16.7868 17.6856 16.7868C17.6912 16.7868 17.6969 16.7868 17.7036 16.7879L20.4313 17.2206C20.4638 17.2263 20.4907 17.2442 20.5075 17.2722C20.6196 17.4662 20.6947 17.6781 20.7328 17.9C20.7508 17.9987 20.7586 18.103 20.7586 18.2117C20.7586 19.2487 19.9144 20.0929 18.8763 20.0941Z" fill="#141414" />
                  <path d="M26.1392 14.5154C25.9183 12.0198 24.7748 9.70473 22.9193 7.99727C21.0527 6.27972 18.631 5.3335 16.0984 5.3335C10.54 5.3335 6.01739 9.85608 6.01739 15.4146C6.01739 17.2801 6.53197 19.0974 7.50622 20.6804L5.3335 25.49L12.29 24.749C13.4997 25.2445 14.78 25.4956 16.0973 25.4956C16.4438 25.4956 16.7992 25.4777 17.1557 25.4407C17.4696 25.4071 17.7869 25.3577 18.0985 25.295C22.7534 24.3543 26.1515 20.223 26.1784 15.4684V15.4146C26.1784 15.1119 26.1649 14.8092 26.138 14.5154H26.1392ZM12.558 22.6379L8.70919 23.0482L9.85833 20.5022L9.6285 20.1939C9.61168 20.1715 9.59487 20.149 9.57581 20.1233C8.57801 18.7454 8.05108 17.1175 8.05108 15.4157C8.05108 10.9783 11.6611 7.36832 16.0984 7.36832C20.2555 7.36832 23.7792 10.6117 24.1189 14.752C24.1369 14.974 24.1469 15.1971 24.1469 15.4168C24.1469 15.4796 24.1458 15.5413 24.1447 15.6074C24.0595 19.3194 21.4664 22.472 17.8384 23.2747C17.5615 23.3364 17.2779 23.3835 16.9953 23.4137C16.7016 23.4474 16.4 23.4642 16.1007 23.4642C15.0345 23.4642 13.9986 23.2579 13.0199 22.8498C12.9111 22.8061 12.8046 22.759 12.7048 22.7108L12.5591 22.6402L12.558 22.6379Z" fill="#141414" />
                </svg>
                @break
              @default
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M18.7 13.3L12 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M14.8 10H21.2V16.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M21 18.7V22C21 22.5 20.8 23 20.4 23.4C20 23.8 19.5 24 19 24H10C9.5 24 9 23.8 8.6 23.4C8.2 23 8 22.5 8 22V13C8 12.5 8.2 12 8.6 11.6C9 11.2 9.5 11 10 11H13.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            @endswitch
          </a>
        </li>
      @endforeach
    </ul>
  @endif

  <div class="text-body-13 font-light text-gray-3 text-center">&copy; {{ date('Y') }} {{ $siteName }}</div>
</footer>

<div class="sticky inset-x-0 bottom-0 z-50 w-full md:hidden">
  @if (is_product() && wp_is_mobile())
    @include('woocommerce.single-product.add-to-cart-bar.mobile')
  @endif
  <nav class="bg-[#072114] px-6 py-3 md:hidden" aria-label="Bottom navigation">
    <ul class="flex items-center justify-between">
      @foreach ($bottomNavigation as $item)
        <li>
          @if ($item['type'] === 'link')
            <a
              href="{{ $item['url'] }}"
              target="{{ $item['target'] }}"
              aria-label="{{ $item['name'] }}"
              class="counter-for-{{ $item['id'] }} text-gray-6 relative block text-[14px] leading-4.75"
            >
              @if ($item['id'] === 'home')
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M9 22V12H15V22" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              @elseif ($item['id'] === 'cart')
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M8 22C8.55228 22 9 21.5523 9 21C9 20.4477 8.55228 20 8 20C7.44772 20 7 20.4477 7 21C7 21.5523 7.44772 22 8 22Z" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M19 22C19.5523 22 20 21.5523 20 21C20 20.4477 19.5523 20 19 20C18.4477 20 18 20.4477 18 21C18 21.5523 18.4477 22 19 22Z" stroke="#F2F2F2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M2.0498 2.0498H4.0498L6.7098 14.4698C6.80738 14.9247 7.06048 15.3313 7.42552 15.6197C7.79056 15.908 8.24471 16.0602 8.7098 16.0498H18.4898C18.945 16.0491 19.3863 15.8931 19.7408 15.6076C20.0954 15.3222 20.3419 14.9243 20.4398 14.4798L22.0898 7.0498H5.1198" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              @endif

              <span
                data-count="{{ $item['count'] }}"
                class="text-background absolute bottom-2 left-2 flex size-5.5 items-center justify-center rounded-full bg-[#EB5757] text-center text-[13px] leading-none font-semibold before:content-[attr(data-count)] data-[count='0']:hidden"
              ></span>
            </a>
          @elseif ($item['type'] === 'button')
            <button
              type="button"
              @click="{{ $item['action'] }}()"
              aria-label="{{ $item['name'] }}"
              class="counter-for-{{ $item['id'] }} text-gray-6 relative block text-[14px] leading-4.75"
            >
              @if ($item['id'] === 'search')
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M20.9999 20.9999L16.6499 16.6499" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              @elseif ($item['id'] === 'menu')
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M4 12H20" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M4 6H20" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M4 18H20" stroke="#F2F2F2" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              @endif
            </button>
          @endif
        </li>
      @endforeach
    </ul>
  </nav>
</div>
