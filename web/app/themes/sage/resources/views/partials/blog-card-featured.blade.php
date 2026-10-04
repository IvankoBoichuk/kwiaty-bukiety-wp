@use('App\Support\Markup')
@php
  /*
   * The lead article of the first page.
   *
   * It is the same card as the grid below it until lg: the phone design has no
   * lead at all (every card there is identical), and the tablet one is the
   * card stretched across both columns with a wider photo. Only the desktop
   * design (682-8192 / 682-8213) breaks the shape -- the photo moves to the
   * left half, the card chrome drops away and the date leaves the photo for a
   * grey line above the title -- so the split is an lg-only override on one
   * markup tree rather than two trees fighting over `hidden`.
   */
  $sprite = get_template_directory_uri() . '/resources/icon/sprite-base.svg';
  $wrapperClass = trim((string) ($wrapperClass ?? ''));
@endphp

<article
  @php(post_class( trim( 'group bg-background relative flex flex-col overflow-hidden rounded-3xl border border-[#E0E0D7] lg:grid lg:grid-cols-2 lg:items-start lg:gap-6 lg:overflow-visible lg:rounded-none lg:border-0 lg:bg-transparent ' . $wrapperClass ) ))
>
  <div
    class="relative aspect-370/304 w-full overflow-hidden md:aspect-704/447 lg:aspect-835/480 lg:rounded-[32px]"
  >
    @if (has_post_thumbnail())
      {!!
        get_the_post_thumbnail(null, 'large', [
          'class' => 'size-full object-cover',
          'loading' => 'eager',
          'fetchpriority' => 'high',
          'sizes' => '(min-width: 1024px) 50vw, 100vw',
        ])
      !!}
    @else
      <div class="bg-secondary size-full" aria-hidden="true"></div>
    @endif

    {{-- Over the photo up to the tablet layout; the desktop one prints it in
         the right-hand column instead. --}}
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

  <div class="flex flex-1 flex-col gap-4 p-3 lg:gap-0 lg:p-0">
    <time
      datetime="{{ get_post_time('c', true) }}"
      class="hidden items-center gap-2 text-[16px] leading-6 text-[#969998] lg:flex"
    >
      <svg class="size-4 shrink-0" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <use href="{{ $sprite }}#calendar"></use>
      </svg>
      {{ get_the_date('d.m.Y') }}
    </time>

    <div class="flex flex-col gap-2 lg:mt-8 lg:gap-4">
      <h2
        class="text-gray-1 line-clamp-2 text-[16px] leading-normal font-semibold md:text-[22px] md:font-bold lg:text-[32px] lg:font-semibold"
      >
        <a href="{{ esc_url(get_permalink()) }}" class="before:absolute before:inset-0">{!! get_the_title() !!}</a>
      </h2>

      <p class="text-body-13 md:text-body-16 line-clamp-2 text-[#5A6561] lg:line-clamp-5">{!! Markup::excerpt() !!}</p>
    </div>

    <span
      @class([
        Markup::buttonClasses('border', 'md', false),
        'group-hover:bg-secondary mt-auto h-13 w-full text-[14px]! md:text-base! lg:mt-6 lg:w-[280px]'
      ])
      aria-hidden="true"
    >
      {{ __('Read more', 'sage-front') }}
    </span>
  </div>
</article>
