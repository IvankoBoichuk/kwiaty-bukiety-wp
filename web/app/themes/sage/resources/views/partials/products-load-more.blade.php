@use('App\Support\Markup')
@php
  // The catalogue button of the design: 240x52, 15px semibold. The size
  // overrides have to be important, because the utilities they replace come
  // from the shared button helper and sit in the same layer.
  //
  // It is a real link to the next page, not a <button>: crawlers do not click,
  // so a button left every product past page one of a category without a
  // single internal link. The script takes over a plain click and appends the
  // next page in place; everything else (no JS, a new tab) follows the href.
  $nextUrl = trim((string) ($nextUrl ?? ''));
  $label = __('Show more', 'sage-front');
@endphp

@if ($nextUrl !== '')
  <div class="mt-8 flex justify-center" data-products-load-more>
    <a
      href="{{ esc_url($nextUrl) }}"
      data-products-load-more-link
      data-default-label="{{ esc_attr($label) }}"
      data-loading-label="{{ esc_attr(__('Loading...', 'sage-front')) }}"
      @class([ Markup::buttonClasses('border', 'md', false), 'h-13! w-60! text-[15px]! aria-disabled:pointer-events-none' ])
    >
      <span>{{ $label }}</span>
    </a>
  </div>
@endif
