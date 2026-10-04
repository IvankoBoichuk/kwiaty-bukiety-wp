@extends('layouts.app')

@section('content')
  {{-- The listing is one container-width child of #main, so the title block,
       the grid and the paging control keep the gutter of the design instead of
       picking up #main's own flex gap between them. --}}
  <section class="flex flex-col pt-5 pb-12 md:pt-6 lg:pt-8 lg:pb-20">
    @include('partials.blog-header')

    @if (have_posts())
      {{--
        Phones get a plain stack of identical cards; from md the first article
        of the first page leads the grid across its full width, and from lg it
        becomes the split hero of node 682-8192. The extra margin under it is
        what the design leaves between the lead and the grid (32px on tablet,
        64px on desktop) minus the row gap the grid already contributes.
      --}}
      <div class="mt-8 grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-y-5 lg:mt-10 lg:grid-cols-3">
        @php($position = 0)

        @while (have_posts())
          @php(the_post())

          @if ($position === 0 && !is_paged())
            @include('partials.blog-card-featured',
              [
                'wrapperClass' => 'md:col-span-2 md:mb-3 lg:col-span-3 lg:mb-11'
              ])
          @else
            @include('partials.blog-card', ['wrapperClass' => ''])
          @endif

          @php($position++)
        @endwhile
      </div>

      @include('partials.blog-pagination')
    @else
      <p class="text-body-15 md:text-body-16 text-gray-1 mt-8">
        {{ __('Sorry, no results were found.', 'sage-front') }}
      </p>
    @endif
  </section>
@endsection
