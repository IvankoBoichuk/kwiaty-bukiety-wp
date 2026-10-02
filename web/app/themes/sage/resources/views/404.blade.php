@extends('layouts.app')

@section('content')
  {{--
    The oversized numeral is the design's decorative-word treatment, but it is
    laid out in flow rather than through .section__subtitle: that utility pins
    itself at -top-24 to sit behind a section heading, which on a page this
    short would ride up into the header. aria-hidden because the h1 already
    says what happened -- the numeral only repeats it.
  --}}
  <section class="flex flex-col items-center gap-6 py-10 text-center md:gap-8 lg:py-16">
    <p
      class="font-heading text-green-easy -mb-2 text-[96px] leading-none font-light select-none md:-mb-4 md:text-[140px] lg:text-[180px]"
      aria-hidden="true"
    >404</p>

    <div class="flex flex-col items-center gap-4">
      <h1 class="h2-mobile text-dark-text md:h2-desktop">{{ __('Page not found', 'sage-front') }}</h1>

      <p class="text-body-15 max-w-130 text-[#426E59] md:text-[16px]">
        {{
          __(
            'The page you were looking for has been moved or never existed. Search for a bouquet below, or start again from the home page.',
            'sage-front',
          )
        }}
      </p>
    </div>

    {{-- Same control styling as the header search panel, so the two match. --}}
    <form role="search" method="get" action="{{ home_url('/') }}" class="flex w-full max-w-130 items-center gap-3">
      <label for="error-404-search" class="sr-only">{{ _x('Search for:', 'label', 'sage-front') }}</label>

      <input
        id="error-404-search"
        type="search"
        name="s"
        value="{{ get_search_query() }}"
        placeholder="{!! esc_attr_x('Search &hellip;', 'placeholder', 'sage-front') !!}"
        class="text-green-dark placeholder:text-gray-3 focus:border-green-default h-9.5 min-w-0 flex-1 rounded-full border border-[#426E59] bg-white px-5 text-[14px] focus:outline-none"
      />

      <button
        type="submit"
        class="border-green-default bg-green-default text-background inline-flex h-9.5 shrink-0 items-center justify-center rounded-full border-2 px-6 text-[14px] font-semibold transition-opacity duration-200 hover:opacity-90"
      >
        {{ _x('Search', 'submit button', 'sage-front') }}
      </button>
    </form>

    <div class="flex w-full max-w-130 flex-col items-stretch gap-3 md:flex-row md:justify-center">
      @include('partials.button',
        [
          'text' => __('Back to Home Page', 'sage-front'),
          'link' => home_url('/'),
          'variant' => 'border',
          'size' => 'lg',
          'target' => '_self',
          'showIcon' => false
        ])

      @if (function_exists('wc_get_page_permalink') && ($shopUrl = wc_get_page_permalink('shop')))
        @include('partials.button',
          [
            'text' => __('Browse bouquets', 'sage-front'),
            'link' => $shopUrl,
            'variant' => 'green',
            'size' => 'lg',
            'target' => '_self',
            'showIcon' => false
          ])
      @endif
    </div>
  </section>
@endsection
