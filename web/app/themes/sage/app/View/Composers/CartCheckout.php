<?php

namespace App\View\Composers;

use App\Checkout\CartCheckout as CartCheckoutService;
use Roots\Acorn\View\Composer;

class CartCheckout extends Composer
{
    /**
     * @var array<int, string>
     */
    protected static $views = [
        'woocommerce.cart.cart',
        'page-cart',
        'page-checkout',
    ];

    protected function with(): array
    {
        $checkout = new CartCheckoutService();

        return [
            'cartCheckout' => $checkout->toArray(),
            'checkoutInstance' => $checkout->checkout(),
            'orderButtonText' => apply_filters(
                'woocommerce_order_button_text',
                __('Buy and pay', 'sage-front'),
            ),
        ];
    }
}
