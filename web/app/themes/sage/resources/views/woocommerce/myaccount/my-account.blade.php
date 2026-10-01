{{--
My Account page

This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account.php.

HOWEVER, on occasion WooCommerce will need to update template files and you
(the theme developer) will need to copy the new files to your theme to
maintain compatibility. We try to do this as little as possible, but it does
happen. When this occurs the version of the template file will be bumped and
the readme will list any important changes.

@see https://woocommerce.com/document/template-structure/
@package WooCommerce/Templates
@version 3.5.0
--}}

@include('partials.account-title')

<div class="woocommerce-MyAccount grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] lg:gap-10">
  @php(do_action('woocommerce_account_navigation'))

  <div class="woocommerce-MyAccount-content">
    @php(do_action('woocommerce_account_content'))
  </div>
</div>
