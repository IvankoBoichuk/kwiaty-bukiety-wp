@use('App\Support\Markup')
@php
  // The catalogue button of the design: 240x52, 15px semibold. The size
  // overrides have to be important, because the utilities they replace come
  // from the shared button helper and sit in the same layer.
  $nextUrl = trim((string) ($nextUrl ?? ''));
  $label = __('Show more', 'sage-front');
@endphp

@if ($nextUrl !== '')
  <div class="mt-8 flex justify-center" data-products-load-more>
    <button
      type="button"
      data-products-load-more-button
      data-next-url="{{ esc_url($nextUrl) }}"
      data-default-label="{{ esc_attr($label) }}"
      data-loading-label="{{ esc_attr(__('Loading...', 'sage-front')) }}"
      @class([Markup::buttonClasses('border', 'md', false), 'h-13! w-60! text-[15px]!'])
    >
      <span>{{ $label }}</span>
    </button>
  </div>
@endif
