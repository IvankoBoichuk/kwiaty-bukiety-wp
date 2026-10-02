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
    <div class="prose prose-content mx-auto w-full max-w-[1040px]">
      @php(the_content())
    </div>

    @if ($pagination())
      <nav class="page-nav text-body-13 md:text-body-16 mx-auto mt-8 w-full max-w-[1040px]" aria-label="Page">
        {!! $pagination !!}
      </nav>
    @endif
  </section>
@endif
