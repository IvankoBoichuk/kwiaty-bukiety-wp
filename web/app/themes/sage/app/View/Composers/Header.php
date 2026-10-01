<?php

namespace App\View\Composers;

use App\Support\Context;
use Roots\Acorn\View\Composer;

class Header extends Composer
{
    /**
     * @var array<int, string>
     */
    protected static $views = ['sections.header'];

    protected function with(): array
    {
        $context = app(Context::class);

        return [
            'siteName' => $context->siteName(),
            'logos' => $context->logos(),
            'menu' => $context->primaryNavigation(),
            'topBarMenu' => $context->topBarNavigation(),
            'phone' => $context->phone(),
            'deliveryTimer' => $context->deliveryTimer(),
        ];
    }
}
