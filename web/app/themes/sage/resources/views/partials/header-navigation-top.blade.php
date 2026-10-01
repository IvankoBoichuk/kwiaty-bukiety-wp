{{--
  The secondary links in the dark bar above the logo row, from the
  `top_bar_navigation` menu location. Second-level items open in a small
  dropdown -- the design shows the caret on "Miasta", which is a city list, not
  a catalogue section, so it stays out of the primary mega menu.
--}}
<nav class="ms-auto max-lg:hidden" aria-label="{{ wp_get_nav_menu_name('top_bar_navigation') }}">
  <ul class="flex items-center gap-12 text-[16px] leading-6 text-white">
    @foreach ($menu as $item)
      <li
        class="relative"
        @if (!empty($item['children']))
          x-data="{ open: false }"
          @mouseenter="open = true"
          @mouseleave="open = false"
          @focusin="open = true"
          @focusout="if (!$el.contains($event.relatedTarget)) open = false;"
          @keydown.escape.window="open = false"
        @endif
      >
        <a
          href="{{ $item['url'] }}"
          @if (!empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif
          class="hover:text-gray-4 focus:text-gray-4 flex items-center gap-1.5 py-1 transition-colors duration-200 focus:outline-none"
          @if (!empty($item['children'])) :aria-expanded="open.toString()" @endif
        >
          <span>{{ $item['title'] }}</span>

          @if (!empty($item['children']))
            <svg
              class="size-4 shrink-0 transition-transform duration-200"
              :class="open ? 'rotate-180' : ''"
              viewBox="0 0 16 16"
              fill="none"
              aria-hidden="true"
            >
              <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          @endif
        </a>

        @if (!empty($item['children']))
          <ul
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-y-1 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-1 opacity-0"
            {{-- Above the primary mega menu, which is a sibling inside the same
                 header stacking context at z-50 and, coming later in the DOM,
                 would otherwise paint over this one.

                 The bar is sticky at the top of the viewport, so this list
                 always opens 60px down (48px bar + mt-3); capping it there lets
                 a long city list scroll instead of running off screen, the same
                 problem the mega menu had. --}}
            class="bg-primary absolute top-full right-0 z-70 mt-3 grid max-h-[calc(100dvh-5rem)] min-w-56 overflow-y-auto overscroll-contain border border-[#426E59] py-2 shadow-[0_24px_60px_rgba(12,52,33,0.24)]"
            x-cloak
          >
            @foreach ($item['children'] as $child)
              <li>
                <a
                  href="{{ $child['url'] }}"
                  @if (!empty($child['target'])) target="{{ $child['target'] }}" rel="noopener" @endif
                  class="hover:text-gray-4 focus:text-gray-4 block px-5 py-2 whitespace-nowrap transition-colors duration-200 focus:outline-none"
                >
                  {{ $child['title'] }}
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </li>
    @endforeach
  </ul>
</nav>
