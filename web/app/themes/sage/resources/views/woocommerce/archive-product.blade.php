{{--
The Template for displaying product archives, including the main shop page which is a post type archive

This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.

HOWEVER, on occasion WooCommerce will need to update template files and you
(the theme developer) will need to copy the new files to your theme to
maintain compatibility. We try to do this as little as possible, but it does
happen. When this occurs the version of the template file will be bumped and
the readme will list any important changes.

@see https://docs.woocommerce.com/document/template-structure/
@package WooCommerce/Templates
@version 3.4.0
--}}

@extends('layouts.app')

@section('content')
  @php
    do_action('get_header', 'shop');
    do_action('woocommerce_before_main_content');

    // The archive description renders after the loop in the design, and it is
    // optional, so it is captured first: a wrapper carrying its top margin
    // would otherwise leave a gap on the categories that have no text.
    ob_start();
    do_action('woocommerce_archive_description');
    $archiveDescription = trim((string) ob_get_clean());

    $categoryFaq = \App\Catalog\Faq::forCurrentTerm();

    // Page 1 only, like the FAQ below: the tiles are the same on
    // every page of the loop, and repeating them would put the one
    // block of internal links on the category into each paged URL.
    $categoryTiles = is_paged() ? [] : \App\Catalog\CategoryTiles::forCurrentTerm();
  @endphp

  {{-- The whole catalogue is one container-width child of #main. That keeps it
       on the 1680px grid of the design, and it stops #main's own flex gap
       (up to 140px on xl) from landing between the title, the grid, the
       button and the description. --}}
  <div class="mx-container flex flex-col pb-12 lg:pb-20">
    <header class="woocommerce-products-header">
      @if (apply_filters('woocommerce_show_page_title', true))
        <h1 class="woocommerce-products-header__title page-title">{!! woocommerce_page_title(false) !!}</h1>
      @endif
    </header>

    {{-- The curated tiles of the category come before its products, the
         order the old site used: the visitor picks the kind of flower
         first and lands on its own catalogue. --}}
    @if ($categoryTiles !== [])
      @include('woocommerce.category-tiles', ['tiles' => $categoryTiles])
    @endif

    @if (woocommerce_product_loop())
      @php
        do_action('woocommerce_before_shop_loop');
        woocommerce_product_loop_start();
      @endphp
      @if (wc_get_loop_prop('total'))
        @while (have_posts())
          @php
            the_post();
            do_action('woocommerce_shop_loop');
            wc_get_template_part('content', 'product');
          @endphp
        @endwhile
      @endif
      @php
        woocommerce_product_loop_end();
        do_action('woocommerce_after_shop_loop');
      @endphp
    @else
      @php
        do_action('woocommerce_no_products_found');
      @endphp
    @endif

    {{-- `prose` sits on this wrapper rather than on .term-description, the div
         WooCommerce hardcodes around the text (wc-template-functions.php, no
         filter for its class). Reaching it with @apply prose emitted a second
         full copy of the plugin's rule set -- 14KB of raw CSS on the stylesheet
         that blocks the first render of every page. The plugin scopes its rules
         as :where() descendants, so dressing the ancestor reaches the same
         elements and the copy is not needed. --}}
    @if ($archiveDescription !== '')
      <div class="prose prose-a:text-[#2F80ED] prose-a:no-underline prose-a:hover:underline mt-12 max-w-full lg:mt-25">
        {!! $archiveDescription !!}
      </div>
    @endif

    {{-- Page 1 only, so the paged URLs stay free of duplicate FAQ markup. --}}
    @if (!is_paged() && !empty($categoryFaq))
      <div class="mt-12 lg:mt-20">
        @include('woocommerce.category-faq', ['faq' => $categoryFaq])
      </div>
    @endif
  </div>

  @php
    do_action('woocommerce_after_main_content');
    do_action('get_sidebar', 'shop');
    do_action('get_footer', 'shop');
  @endphp
@endsection
