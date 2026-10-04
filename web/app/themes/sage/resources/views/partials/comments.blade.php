@php
  /*
     * The comment thread of the article page.
     *
     * There is no design for it, so it borrows the shapes the article already
     * uses: the heading of the related block, the card border of the blog grid and
     * the form fields of the account screens.
     *
     * The arbitrary-variant classes on the list are there because the <ol
     * class="children"> around a set of replies is printed by Walker_Comment, not
     * by partials/comment -- styling it from here keeps the nesting rule next to
     * the list it indents instead of in a stylesheet nothing else needs.
     */
@endphp

@if (!post_password_required())
  <section id="comments" class="comments mt-12 flex flex-col gap-6 lg:mt-16">
    @if ($responses())
      <h2
        class="text-green-default text-[20px] leading-normal font-extrabold md:text-[28px] md:font-bold lg:text-[32px] lg:font-semibold"
      >
        {!! $title !!}
      </h2>

      <ol
        class="comment-list [&_.children]:mt-4 [&_.children]:flex [&_.children]:list-none [&_.children]:flex-col [&_.children]:gap-4 [&_.children]:border-l [&_.children]:border-[#E0E0D7] [&_.children]:pt-0 [&_.children]:pl-3 md:[&_.children]:pl-6 m-0 flex list-none flex-col gap-4 p-0"
      >
        {!! $responses !!}
      </ol>

      @if ($paginated())
        <nav aria-label="{{ esc_attr__('Comments', 'sage-front') }}">
          <ul
            class="pager text-body-13 md:text-body-16 [&_a]:text-green-default [&_a]:font-semibold [&_a]:underline [&_a]:hover:no-underline m-0 flex list-none flex-wrap items-center justify-between gap-3 p-0"
          >
            @if ($previous())
              <li class="previous">{!! $previous !!}</li>
            @endif

            @if ($next())
              <li class="next ml-auto">{!! $next !!}</li>
            @endif
          </ul>
        </nav>
      @endif
    @endif

    @if ($closed())
      <x-alert type="warning"> {!! __('Comments are closed.', 'sage-front') !!} </x-alert>
    @endif

    @php(comment_form($formArgs()))
  </section>
@endif
