@php
  /*
   * "Będziesz zainteresowany" -- the related reading block (Figma 518-3458
   * phone, 688-9427 tablet, 702-8411 desktop).
   *
   * It is the right-hand column of the desktop article and a full-width band
   * under the copy below lg; the article template owns that placement, this
   * partial only draws the list.
   *
   * The heading does not grow monotonically with the viewport -- 22 / 40 / 32 --
   * because the desktop one sits in a 499px column while the tablet one spans
   * the page. That is the design, not a typo.
   */
  $currentId = get_the_ID();
  $categories = wp_get_post_categories($currentId);

  $query = [
    'post_type' => 'post',
    'posts_per_page' => 4,
    'post__not_in' => [$currentId],
    'ignore_sticky_posts' => true,
    'no_found_rows' => true,
  ];

  $related = new WP_Query($categories === [] ? $query : $query + ['category__in' => $categories]);

  // A post filed on its own in a category would otherwise print an empty block,
  // so the newest posts stand in.
  if (!$related->have_posts() && $categories !== []) {
    $related = new WP_Query($query);
  }
@endphp

@if ($related->have_posts())
  <section class="mx-0 flex flex-col gap-6">
    <h2
      class="text-green-default text-[22px] leading-normal font-extrabold md:text-[40px] md:font-bold lg:text-[32px] lg:font-semibold"
    >
      {{ __('You may also like', 'sage-front') }}
    </h2>

    <div class="flex flex-col gap-2 lg:gap-3">
      @while ($related->have_posts())
        @php($related->the_post())

        <a
          href="{{ esc_url(get_permalink()) }}"
          class="bg-background group flex h-16 items-center overflow-hidden md:h-22 md:pr-3"
        >
          <div class="h-full w-[73px] shrink-0 overflow-hidden rounded-xl md:w-[124px]">
            @if (has_post_thumbnail())
              {!!
                get_the_post_thumbnail(null, 'medium', [
                  'class' => 'size-full object-cover',
                  'loading' => 'lazy',
                  'sizes' => '124px',
                ])
              !!}
            @else
              <div class="bg-secondary size-full" aria-hidden="true"></div>
            @endif
          </div>

          <div class="flex min-w-0 flex-1 items-center px-3 py-2">
            <p
              class="text-gray-1 line-clamp-2 text-justify text-[14px] leading-normal font-semibold group-hover:underline md:line-clamp-3 md:text-[18px] md:leading-[26px] md:font-medium"
            >
              {!! get_the_title() !!}
            </p>
          </div>
        </a>
      @endwhile
    </div>
  </section>

  @php(wp_reset_postdata())
@endif
