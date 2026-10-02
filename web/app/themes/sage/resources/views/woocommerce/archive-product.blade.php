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

    @if ($archiveDescription !== '')
      <div class="mt-12 lg:mt-25">{!! $archiveDescription !!}</div>
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
