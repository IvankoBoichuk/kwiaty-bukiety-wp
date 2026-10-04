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
   */
  $isBlockLayout = has_block('fa/section', get_post());
@endphp

@if ($isBlockLayout)
  @php(the_content())

  @if ($pagination())
    <nav class="page-nav text-body-13 md:text-body-16 mx-container mt-8" aria-label="Page">{!! $pagination !!}</nav>
  @endif
@else
  {{--
    The editor content of a text page. The <section> is what picks up the page
    gutter: app.css applies mx-container to every section in the base layer.
    The measure is capped at the width the design lays the article out in (node
    702-8385), so the copy does not run the full 1680px the container allows.
  --}}
  <section class="py-8 lg:py-12">
    <div class="mx-auto w-full max-w-260">
      {{--
        The page title and its featured image are printed here rather than by
        the page template, and each only when the page carries one: most text
        pages are saved without a thumbnail, and a page can be published with
        an empty title, so neither may leave an empty heading or a stray gap
        above the copy. They sit outside the prose wrapper because the article
        heading scale there is the one the editor's own headings use, which is
        smaller than the page title the design asks for.
      --}}
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

      <div class="prose prose-content">
        @php(the_content())
      </div>
    </div>

    @if ($pagination())
      <nav class="page-nav text-body-13 md:text-body-16 mx-auto mt-8 w-full max-w-260" aria-label="Page">
        {!! $pagination !!}
      </nav>
    @endif
  </section>
@endif
