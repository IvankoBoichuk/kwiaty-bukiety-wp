@php
  // The account screens render through a shortcode, so the page title is never
  // printed by the page template. Prefer the title the shop gave the page, so
  // a renamed account page keeps its own wording.
  $accountPageId = function_exists('wc_get_page_id') ? (int) wc_get_page_id('myaccount') : 0;
  $accountTitle = $accountPageId > 0 ? (string) get_the_title($accountPageId) : '';

  if ($accountTitle === '') {
    $accountTitle = __('My account', 'woocommerce');
  }
@endphp

<h1 class="woocommerce-account-title">{{ $accountTitle }}</h1>
