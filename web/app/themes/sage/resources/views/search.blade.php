@extends('layouts.app')

@section('content')
  @php
    // The header search deliberately queries without post_type=product (see
    // partials/header-search.blade.php), so products land here rather than in
    // the WooCommerce archive. They are rendered with the catalogue card and
    // grid; anything else keeps a plain list of links.
    global $wp_query;

    $products = collect();
    $otherPosts = [];

    if (have_posts()) {
      $productIds = [];

      while (have_posts()) {
        the_post();

        if (get_post_type() === 'product') {
          $productIds[] = get_the_ID();

          continue;
        }

        $otherPosts[] = get_post();
      }

      rewind_posts();

      $products = collect($productIds)
        ->map(fn($id) => function_exists('wc_get_product') ? (wc_get_product($id) ?: null) : null)
        ->filter(fn($product) => $product instanceof \WC_Product && $product->is_visible())
        ->map(fn($product) => \App\Catalog\Product::fromWooCommerce($product));
    }

    $currentPage = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    $maxPages = $wp_query instanceof \WP_Query ? (int) $wp_query->max_num_pages : 1;

    // Load more appends the next page's products only, so it is used when the
    // page holds nothing else; a mixed result set keeps real pagination.
    $nextPageUrl = empty($otherPosts) && $currentPage < $maxPages ? (string) get_pagenum_link($currentPage + 1) : '';
  @endphp

  <div class="mx-container flex flex-col pb-12 lg:pb-20">
    <header class="woocommerce-products-header">
      <h1 class="woocommerce-products-header__title page-title">
        {{
          sprintf(
            __('Search Results for %s', 'sage-front'),
            get_search_query(),
          )
        }}
      </h1>
    </header>

    @if (!have_posts())
      <x-alert type="warning"> {!! __('Sorry, no results were found.', 'sage-front') !!} </x-alert>

      <div class="mt-6 max-w-xl">{!! get_search_form(false) !!}</div>
    @endif

    @if ($products->isNotEmpty())
      <ul class="products grid grid-cols-2 gap-x-3 gap-y-6 md:grid-cols-3 lg:gap-y-8 xl:grid-cols-4" data-products-list>
        @foreach ($products as $item)
          @include('partials.product-card-grid',
            [
              'item' => $item,
              'wrapperTag' => 'li'
            ])
        @endforeach
      </ul>

      @include('partials.products-load-more', ['nextUrl' => $nextPageUrl])
    @endif

    @if (!empty($otherPosts))
      <ul @class(['flex flex-col gap-6', 'mt-12 lg:mt-25' => $products->isNotEmpty()])>
        @foreach ($otherPosts as $otherPost)
          <li class="text-dark-text">
            <a href="{{ esc_url(get_permalink($otherPost)) }}" class="text-body-16 font-bold uppercase hover:underline">
              {{ get_the_title($otherPost) }}
            </a>
            <p class="text-body-15 mt-1">{{ wp_strip_all_tags(get_the_excerpt($otherPost)) }}</p>
          </li>
        @endforeach
      </ul>

      <div class="search-pagination mt-12">{!! get_the_posts_navigation() !!}</div>
    @endif
  </div>
@endsection
