@php
  global $product;

  if (!($product instanceof \WC_Product) || !$product->is_visible()) {
    return;
  }

  $item = \App\Catalog\Product::fromWooCommerce($product);
@endphp

@include('partials.product-card-grid',
  [
    'item' => $item,
    'wrapperTag' => 'li'
  ])
