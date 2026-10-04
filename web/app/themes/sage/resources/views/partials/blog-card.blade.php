@use('App\Support\Markup')
@php
  /*
   * One article in the blog grid (Figma 507-3152 phone, 673-7939 tablet,
   * 682-8284 desktop).
   *
   * The three widths are the same card with a different image ratio, type
   * scale and excerpt depth, so they are one template rather than three. The
   * image carries an explicit ratio per breakpoint instead of the design's
   * fixed card height: the columns are fluid between the breakpoints the
   * design was drawn at, and a fixed height would stretch the photo into a
   * tower at 1024px while the 1680px layout still looked right.
   *
   * The date badge is drawn over the photo on phones and tablets only -- the
   * desktop card drops it (682-8266 has no overlay).
   */
  $sprite = get_template_directory_uri() . '/resources/icon/sprite-base.svg';
  $wrapperClass = trim((string) ($wrapperClass ?? ''));
@endphp

<article
  @php(post_class( trim( 'group bg-background relative flex h-full flex-col overflow-hidden rounded-3xl border border-[#E0E0D7] lg:rounded-[32px] ' . $wrapperClass ) ))
>
  <div class="relative aspect-[370/304] w-full overflow-hidden md:aspect-[346/255] lg:aspect-[552/381]">
    @if (has_post_thumbnail())
      {!!
        get_the_post_thumbnail(null, 'large', [
          'class' => 'size-full object-cover',
          'loading' => 'lazy',
          'sizes' => '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw',
        ])
      !!}
    @else
      <div class="bg-secondary size-full" aria-hidden="true"></div>
    @endif

    <time
      datetime="{{ get_post_time('c', true) }}"
      class="absolute top-2 right-2 flex items-center gap-1 rounded-full bg-black/30 px-2 py-1 text-[13px] leading-5 text-white md:gap-2 md:bg-black/40 md:px-3 md:py-2 md:text-[16px] md:leading-6 lg:hidden"
    >
      <svg class="size-4 shrink-0" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <use href="{{ $sprite }}#calendar"></use>
      </svg>
      {{ get_the_date('d.m.Y') }}
    </time>
  </div>

  <div class="flex flex-1 flex-col gap-4 p-3">
    <div class="flex flex-col gap-2">
      <h2 class="text-gray-1 line-clamp-2 text-[16px] leading-normal font-semibold md:text-[22px] md:font-bold">
        {{-- The pseudo-element makes the whole card the link target, so the
             "read more" control below can stay a non-interactive span. --}}
        <a href="{{ esc_url(get_permalink()) }}" class="before:absolute before:inset-0">{!! get_the_title() !!}</a>
      </h2>

      <p class="text-body-13 md:text-body-16 line-clamp-2 text-[#5A6561] md:line-clamp-3 lg:line-clamp-2">
        {!! Markup::excerpt() !!}
      </p>
    </div>

    <span
      @class([
        Markup::buttonClasses('border', 'md', false),
        'group-hover:bg-secondary mt-auto h-13 w-full text-[14px]! md:text-base! lg:w-[214px]'
      ])
      aria-hidden="true"
    >
      {{ __('Read more', 'sage-front') }}
    </span>
  </div>
</article>
