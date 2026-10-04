@use('App\Support\Markup')
@php
  /*
   * The two paging controls of the design.
   *
   * The desktop layout (745-8760) numbers the pages; the phone and tablet ones
   * (453-3524 / 680-7795) replace that with a single purple button under the
   * grid. Both drive the same `paged` query and both are plain links, so the
   * listing keeps working without JavaScript and the two never disagree about
   * where the reader is.
   */
  global $wp_query;

  $sprite = get_template_directory_uri() . '/resources/icon/sprite-base.svg';
  $totalPages = max(1, (int) ($wp_query->max_num_pages ?? 1));
  $currentPage = min($totalPages, max(1, (int) get_query_var('paged'), (int) get_query_var('page')));

  // Five numbers around the current page, with the first and last always
  // reachable and an ellipsis standing in for whatever that skips.
  $windowSize = 5;
  $windowStart = min(max(1, $currentPage - 2), max(1, $totalPages - $windowSize + 1));
  $windowEnd = min($totalPages, $windowStart + $windowSize - 1);

  $pages = [];

  if ($windowStart > 1) {
    $pages[] = 1;

    if ($windowStart > 2) {
      $pages[] = null;
    }
  }

  for ($page = $windowStart; $page <= $windowEnd; $page++) {
    $pages[] = $page;
  }

  if ($windowEnd < $totalPages) {
    if ($windowEnd < $totalPages - 1) {
      $pages[] = null;
    }

    $pages[] = $totalPages;
  }

  /* translators: %d is the page number. */
  $pageLabelFormat = __('Page %d', 'sage-front');

  $stepClass =
    'flex size-10 shrink-0 items-center justify-center rounded-xl border-2 border-[#C7C7C7] bg-white text-purple-easy transition-colors hover:text-purple';
  $steps = [
    ['icon' => 'pagination-first', 'page' => 1, 'label' => __('First page', 'sage-front'), 'group' => 'before'],
    [
      'icon' => 'pagination-prev',
      'page' => $currentPage - 1,
      'label' => __('Previous page', 'sage-front'),
      'group' => 'before',
    ],
    [
      'icon' => 'pagination-next',
      'page' => $currentPage + 1,
      'label' => __('Next page', 'sage-front'),
      'group' => 'after',
    ],
    ['icon' => 'pagination-last', 'page' => $totalPages, 'label' => __('Last page', 'sage-front'), 'group' => 'after'],
  ];
@endphp

@if ($totalPages > 1)
  <nav class="mt-8 lg:mt-12" aria-label="{{ esc_attr__('Blog pages', 'sage-front') }}">
    @if ($currentPage < $totalPages)
      <div class="flex justify-center lg:hidden">
        <a
          href="{{ esc_url(get_pagenum_link($currentPage + 1)) }}"
          @class([
            Markup::buttonClasses('purple', 'md', false),
            'h-[46px]! w-full! text-[13px]! font-normal md:h-13! md:w-[290px]!'
          ])
        >
          {{ __('More articles', 'sage-front') }}
        </a>
      </div>
    @endif

    <div class="hidden items-center justify-center gap-5 lg:flex">
      <div class="flex items-center gap-1">
        @foreach ($steps as $step)
          @continue($step['group'] !== 'before')

          @if ($step['page'] >= 1 && $step['page'] < $currentPage)
            <a
              href="{{ esc_url(get_pagenum_link($step['page'])) }}"
              class="{{ $stepClass }}"
              aria-label="{{ esc_attr($step['label']) }}"
            >
              <svg class="size-5" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <use href="{{ $sprite }}#{{ $step['icon'] }}"></use>
              </svg>
            </a>
          @else
            <span class="{{ $stepClass }} pointer-events-none opacity-40" aria-hidden="true">
              <svg class="size-5" viewBox="0 0 20 20" focusable="false">
                <use href="{{ $sprite }}#{{ $step['icon'] }}"></use>
              </svg>
            </span>
          @endif
        @endforeach
      </div>

      <div class="flex items-center gap-1">
        @foreach ($pages as $page)
          @if ($page === null)
            <span class="flex items-center gap-0.5 px-1 pt-3" aria-hidden="true">
              <span class="size-0.5 rounded-full bg-[#A1A9B3]"></span>
              <span class="size-0.5 rounded-full bg-[#A1A9B3]"></span>
              <span class="size-0.5 rounded-full bg-[#A1A9B3]"></span>
            </span>
          @elseif ($page === $currentPage)
            <span
              class="bg-purple flex size-10 shrink-0 items-center justify-center rounded-xl text-base leading-6 font-semibold text-white"
              aria-current="page"
            >
              {{ $page }}
            </span>
          @else
            <a
              href="{{ esc_url(get_pagenum_link($page)) }}"
              class="bg-background text-purple-easy hover:text-purple border-background flex size-10 shrink-0 items-center justify-center rounded-xl border-2 text-base leading-6 font-semibold transition-colors"
              aria-label="{{ esc_attr(sprintf($pageLabelFormat, $page)) }}"
            >
              {{ $page }}
            </a>
          @endif
        @endforeach
      </div>

      <div class="flex items-center gap-1">
        @foreach ($steps as $step)
          @continue($step['group'] !== 'after')

          @if ($step['page'] > $currentPage && $step['page'] <= $totalPages)
            <a
              href="{{ esc_url(get_pagenum_link($step['page'])) }}"
              class="{{ $stepClass }}"
              aria-label="{{ esc_attr($step['label']) }}"
            >
              <svg class="size-5" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <use href="{{ $sprite }}#{{ $step['icon'] }}"></use>
              </svg>
            </a>
          @else
            <span class="{{ $stepClass }} pointer-events-none opacity-40" aria-hidden="true">
              <svg class="size-5" viewBox="0 0 20 20" focusable="false">
                <use href="{{ $sprite }}#{{ $step['icon'] }}"></use>
              </svg>
            </span>
          @endif
        @endforeach
      </div>
    </div>
  </nav>
@endif
