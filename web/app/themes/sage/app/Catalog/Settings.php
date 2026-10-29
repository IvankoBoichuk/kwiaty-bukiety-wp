<?php

namespace App\Catalog;

use App\Shop\FuneralOptions;

final class Settings
{
    /**
     * @return array<string, string>
     */
    public static function locationsOptions(): array
    {
        return [
            'private-address' => __('Private address', 'sage-front'),
            'company' => __('Company', 'sage-front'),
            'church' => __('Church', 'sage-front'),
            'funeral-home' => __('Funeral home', 'sage-front'),
            'hospital' => __('Hospital', 'sage-front'),
            'hotel' => __('Hotel', 'sage-front'),
            'school' => __('School', 'sage-front'),
        ];
    }
    /**
     * @return array<string, string>
     */
    public static function deliveryOptions(): array
    {
        return [
            'at-the-door' => __('Leave at the door', 'sage-front'),
            'hand-delivery' => __('Hand delivery', 'sage-front'),
        ];
    }

    /**
     * @return array<int, \WC_Product>
     */
    public static function funeralAdditionsProducts(): array
    {
        return FuneralOptions::additionProducts();
    }
}
