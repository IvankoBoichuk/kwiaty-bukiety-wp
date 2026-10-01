{{--
  The panel behind the header's search icon and the mobile bottom bar's search
  button. Both call search() on the body-level `mobileMenu` component, so the
  state lives there rather than here.

  Clicks on either toggle are excluded from the outside-click handler: the
  toggle fires first and opens the panel, and the document listener would then
  close it again in the same click.
--}}
<div
  id="header-search-panel"
  x-show="isSearchOpen"
  x-transition:enter="transition ease-out duration-200"
  x-transition:enter-start="-translate-y-2 opacity-0"
  x-transition:enter-end="translate-y-0 opacity-100"
  x-transition:leave="transition ease-in duration-150"
  x-transition:leave-start="translate-y-0 opacity-100"
  x-transition:leave-end="-translate-y-2 opacity-0"
  @keydown.escape.window="closeSearch()"
  @click.outside="if (!$event.target.closest('[data-search-toggle]')) closeSearch();"
  class="bg-background absolute inset-x-0 top-full z-70 border-b border-[#426E59] shadow-[0_24px_60px_rgba(12,52,33,0.14)]"
  x-cloak
>
  <form role="search" method="get" action="{{ home_url('/') }}" class="bx-container flex items-center gap-3 py-4">
    <label for="header-search" class="sr-only">{{ _x('Search for:', 'label', 'sage-front') }}</label>

    <input
      id="header-search"
      type="search"
      name="s"
      value="{{ get_search_query() }}"
      placeholder="{!! esc_attr_x('Search &hellip;', 'placeholder', 'sage-front') !!}"
      class="text-green-dark placeholder:text-gray-3 focus:border-green-default h-9.5 min-w-0 flex-1 rounded-full border border-[#426E59] bg-white px-5 text-[14px] focus:outline-none"
    />

    {{-- Deliberately no post_type=product: ?s=&post_type=product renders a
         blank document on this install (head and scripts, no <main>), while the
         plain query goes through search.blade.php and lists products anyway,
         since WooCommerce leaves them searchable. --}}

    <button
      type="submit"
      class="border-green-default bg-green-default text-background inline-flex h-9.5 shrink-0 items-center justify-center rounded-full border-2 px-6 text-[14px] font-semibold transition-opacity duration-200 hover:opacity-90"
    >
      {{ _x('Search', 'submit button', 'sage-front') }}
    </button>

    <button
      type="button"
      @click="closeSearch()"
      aria-label="{{ __('Close', 'sage-front') }}"
      class="text-green-dark hover:text-green-easy shrink-0 transition-colors duration-200"
    >
      <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6l-12 12" />
      </svg>
    </button>
  </form>
</div>
