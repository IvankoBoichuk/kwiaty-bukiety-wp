@extends('layouts.app')

@section('content')
  @php
    // The custom cart/checkout replaces the stock checkout form, but the two
    // endpoints that live under the same page -- order-pay and order-received
    // -- still have to go through WooCommerce's own shortcode output, or
    // paying for a pending order lands on an empty cart.
    $isCheckoutEndpoint =
      (function_exists('is_order_received_page') && is_order_received_page()) ||
      (function_exists('is_checkout_pay_page') && is_checkout_pay_page());
  @endphp

  @if ($isCheckoutEndpoint)
    @if (have_posts())
      @while (have_posts())
        @php(the_post())
        @php(the_content())
      @endwhile
    @else
      {!! do_shortcode('[woocommerce_checkout]') !!}
    @endif
  @else
    @include('woocommerce.cart.cart')
  @endif
@endsection
