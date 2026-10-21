@use('App\Support\PageSections')
@php
  /*
   * A page assembled from fa/section blocks must not be wrapped: those sections
   * bring their own container, several of them bleed out to the window edge,
   * and the prose rules would restyle copy the block templates already style.
   * Wrapping them in the article measure crushes the layout, so they print as
   * the blocks rendered them.
   *
   * The content itself answers the question, so it is read from there rather
   * than from a page template an editor has to remember to pick: forget that
   * once and the page silently renders in the wrong shape, while a page that
   * gains or loses its sections corrects itself here.
   *
   * A page that opens on a section is a section page. A page that opens on
   * copy is a text page, and a section added to it -- the FAQ block under the
   * "Kwiaciarnie w Polsce" copy -- prints bare between the runs of copy, each
   * of which keeps the article measure.
   */
  $content = get_the_content();
  $isBlockLayout = PageSections::startsWithSection($content);
  $runs = $isBlockLayout ? [] : (PageSections::split($content) ?: [['section' => false, 'content' => '']]);
  $firstText = array_search(false, array_column($runs, 'section'), true);
@endphp

@if ($isBlockLayout)
  @php(the_content())

  @if ($pagination())
    <nav class="page-nav text-body-13 md:text-body-16 mx-container mt-8" aria-label="Page">{!! $pagination !!}</nav>
  @endif
@else
  @foreach ($runs as $index => $run)
    @if ($run['section'])
      {!! PageSections::render($run['content']) !!}
    @else
      {{--
        A run of editor copy. The <section> is what picks up the page gutter:
        app.css applies mx-container to every section in the base layer. The
        measure is capped at the width the design lays the article out in (node
        702-8385), so the copy does not run the full 1680px the container allows.
      --}}
      <section class="py-8 lg:py-12">
        <div class="mx-auto w-full max-w-260">
          {{--
            The page title and its featured image are printed above the first
            run of copy, and each only when the page carries one: most text
            pages are saved without a thumbnail, and a page can be published
            with an empty title, so neither may leave an empty heading or a
            stray gap above the copy. They sit outside the prose wrapper because
            the article heading scale there is the one the editor's own headings
            use, which is smaller than the page title the design asks for.
          --}}
          @if ($index === $firstText)
            @if ($title)
              <h1 class="h2-mobile md:h2-desktop text-green-default mb-5 md:mb-8">{!! $title !!}</h1>
            @endif

            @if (has_post_thumbnail())
              {!!
                get_the_post_thumbnail(null, 'large', [
                  'class' => 'mb-5 md:mb-8 w-full rounded-2xl object-cover',
                ])
              !!}
            @endif
          @endif

          <div class="prose prose-content">
            {!! PageSections::render($run['content']) !!}
          </div>
        </div>
      </section>
    @endif
  @endforeach

  @if ($pagination())
    <nav class="page-nav text-body-13 md:text-body-16 mx-auto mt-8 w-full max-w-260" aria-label="Page">
      {!! $pagination !!}
    </nav>
  @endif
@endif
