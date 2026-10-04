@php
  /*
   * The previous / next article links at the foot of the copy.
   *
   * get_previous_post() is the *older* post of the two, which is why the
   * labels do not follow the function names: a reader thinks in terms of the
   * article before and after the one they are on, not in publication order.
   *
   * Both links stay inside the same category as the current post when it has
   * one, so the pair reads as a thread through a topic rather than a walk
   * through the whole blog; on a post filed nowhere the adjacent posts of the
   * blog stand in, which is what WordPress returns anyway.
   */
  $sprite = get_template_directory_uri() . '/resources/icon/sprite-base.svg';
  $inSameTerm = has_term('', 'category');

  $links = array_values(
    array_filter(
      [
        [
          'post' => get_previous_post($inSameTerm),
          'label' => __('Previous article', 'sage-front'),
          'icon' => 'pagination-prev',
          'next' => false,
        ],
        [
          'post' => get_next_post($inSameTerm),
          'label' => __('Next article', 'sage-front'),
          'icon' => 'pagination-next',
          'next' => true,
        ],
      ],
      fn(array $link): bool => $link['post'] instanceof WP_Post,
    ),
  );
@endphp

@if ($links !== [])
  <nav
    class="mt-10 grid gap-3 border-t border-[#E0E0D7] pt-6 md:grid-cols-2 lg:mt-12"
    aria-label="{{ esc_attr__('Articles', 'sage-front') }}"
  >
    @foreach ($links as $link)
      <a
        href="{{ esc_url((string) get_permalink($link['post'])) }}"
        rel="{{ $link['next'] ? 'next' : 'prev' }}"
        @class([
          'group bg-background hover:border-green-easy flex items-center gap-3 rounded-2xl border border-[#E0E0D7] p-3 transition-colors',
          'md:col-start-2 md:flex-row-reverse md:text-right' => $link['next']
        ])
      >
        <span
          class="text-purple-easy group-hover:text-purple flex size-10 shrink-0 items-center justify-center rounded-xl border-2 border-[#C7C7C7] bg-white transition-colors"
          aria-hidden="true"
        >
          <svg class="size-5" viewBox="0 0 20 20" focusable="false">
            <use href="{{ $sprite }}#{{ $link['icon'] }}"></use>
          </svg>
        </span>

        <span class="flex min-w-0 flex-col gap-0.5">
          <span class="text-body-13 text-[#969998]">{{ $link['label'] }}</span>
          <span
            class="text-gray-1 line-clamp-2 text-[14px] leading-normal font-semibold group-hover:underline md:text-[16px]"
          >
            {!! get_the_title($link['post']) !!}
          </span>
        </span>
      </a>
    @endforeach
  </nav>
@endif
