@use('App\Support\Markup')
@php
  /*
   * The article page (Figma 3062-9079 phone, 3062-9080 tablet, 3062-9076
   * desktop).
   *
   * The body is editor content, and `prose-content` (resources/css/typography.css)
   * already carries this design's article scale and its alternating image
   * floats, so this template only draws what sits around it: the date and
   * title, the lead paragraph beside the hero image, and the related reading
   * block.
   *
   * The lead comes from the post's Excerpt field rather than from the first
   * paragraph of the body: get_the_excerpt() would fall back to trimming that
   * same paragraph and print it twice. A post saved without an excerpt simply
   * opens on the image, which is the phone layout of the design.
   */
  $sprite = get_template_directory_uri() . '/resources/icon/sprite-base.svg';
@endphp

<article @php(post_class('h-entry mx-container pt-5 pb-12 md:pt-6 lg:pt-8 lg:pb-20'))>
  {{-- The related block is the right-hand column of the desktop design and a
       band under the copy below lg. The columns are the design's own 1116 /
       499 split; `min-w-0` keeps a long word in the body from widening the
       article track and squeezing the sidebar. --}}
  <div class="lg:grid lg:grid-cols-[1116fr_499fr] lg:items-start lg:gap-16">
    <div class="min-w-0">
      <header class="flex flex-col gap-2">
        <p class="flex items-center gap-1 text-[13px] leading-5 font-semibold text-[#969998] md:gap-1.5 md:text-[16px] md:leading-6">
          <svg class="size-4 shrink-0" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
            <use href="{{ $sprite }}#calendar"></use>
          </svg>
          <time class="dt-published" datetime="{{ get_post_time('c', true) }}">{{ get_the_date('d.m.Y') }}</time>
        </p>

        <h1
          class="p-name text-gray-1 text-[16px] leading-normal font-semibold md:text-[32px] lg:text-[40px] lg:font-bold"
        >
          {!! $title !!}
        </h1>
      </header>

      @if (has_post_thumbnail() || has_excerpt())
        {{--
          The hero image and the lead swap places across the three designs: the
          image opens the page on phones, sits beside the lead on tablets and
          follows it on the desktop. That is one source order reordered, not
          three blocks -- the image is last everywhere except the phone layout.
        --}}
        <div class="mt-5 grid gap-4 md:mt-6 md:grid-cols-2 md:gap-3 lg:grid-cols-1 lg:gap-8">
          @if (has_post_thumbnail())
            <figure class="order-first m-0 md:order-last">
              {!!
                get_the_post_thumbnail(null, 'full', [
                  'class' => 'aspect-[370/296] w-full rounded-2xl object-cover md:aspect-[346/192] lg:aspect-[1113/592]',
                  'loading' => 'eager',
                  'fetchpriority' => 'high',
                  'sizes' => '(min-width: 1024px) 66vw, (min-width: 768px) 50vw, 100vw',
                ])
              !!}
            </figure>
          @endif

          @if (has_excerpt())
            <p class="text-green-dark order-last text-[13px] leading-5 md:order-first md:text-[16px] md:leading-6">
              {!! Markup::excerpt() !!}
            </p>
          @endif
        </div>
      @endif

      <div class="prose prose-content mt-8 md:mt-10 lg:mt-12">
        @php(the_content())
      </div>

      @if ($pagination())
        <nav class="page-nav text-body-13 md:text-body-16 mt-8" aria-label="{{ esc_attr__('Page', 'sage-front') }}">
          {!! $pagination !!}
        </nav>
      @endif
    </div>

    <aside class="mt-14 md:mt-20 lg:mt-0 lg:pt-8">
      @include('partials.article-related')
    </aside>
  </div>

  {{-- The design stops at the related block, but the posts carry 185 approved
       comments between them, so the template stays and only gets the spacing
       that keeps it off the block above. Its own styling is whatever
       partials/comments.blade.php prints -- there is no design for it. --}}
  <div class="mt-12 lg:mt-16">
    @php(comments_template())
  </div>
</article>
