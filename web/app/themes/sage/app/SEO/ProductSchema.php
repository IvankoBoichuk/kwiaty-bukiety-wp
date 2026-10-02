<?php

declare(strict_types=1);

namespace App\SEO;

use WC_Product;

/**
 * Extends WooCommerce's Product structured data (production snippet #15).
 *
 * Google Merchant rejects offers without a return policy and shipping details,
 * so both are added to every offer, along with a description, the aggregate
 * rating and up to 20 reviews.
 */
final class ProductSchema
{
    public const RETURN_DAYS = 14;

    public const COUNTRY = 'PL';

    public const REVIEW_LIMIT = 20;

    public static function boot(): void
    {
        add_filter('woocommerce_structured_data_product', [self::class, 'extend'], 20, 2);
        add_action('woocommerce_after_single_product', [self::class, 'generate']);
    }

    /**
     * WooCommerce builds its Product data from woocommerce_single_product_summary,
     * which this theme's single-product template does not fire -- so product pages
     * were shipping a BreadcrumbList and nothing else. Generating it here, before
     * wp_footer flushes the structured data, restores the Product node that the
     * filter above then extends.
     */
    public static function generate(): void
    {
        global $product;

        if (! $product instanceof WC_Product || ! function_exists('WC')) {
            return;
        }

        $structuredData = WC()->structured_data ?? null;

        if (! $structuredData || ! method_exists($structuredData, 'generate_product_data')) {
            return;
        }

        $structuredData->generate_product_data($product);
    }

    /**
     * @param  array<string, mixed>  $markup
     * @return array<string, mixed>
     */
    public static function extend(mixed $markup, mixed $product): array
    {
        if (! is_array($markup) || ! $product instanceof WC_Product) {
            return is_array($markup) ? $markup : [];
        }

        if (empty($markup['description'])) {
            $description = $product->get_short_description() ?: $product->get_description();
            $markup['description'] = wp_strip_all_tags((string) $description);
        }

        if (isset($markup['sku']) && $markup['sku'] !== '') {
            $markup['sku'] = (string) $markup['sku'];
        }

        if (! empty($markup['offers']) && is_array($markup['offers'])) {
            $markup['offers'] = array_map([self::class, 'extendOffer'], $markup['offers']);
        }

        $rating = (float) $product->get_average_rating();
        $reviewCount = (int) $product->get_review_count();

        if ($rating > 0 && $reviewCount > 0) {
            $markup['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $reviewCount,
            ];
        }

        $reviews = self::reviews($product);

        if ($reviews !== []) {
            $markup['review'] = $reviews;
        }

        return $markup;
    }

    /**
     * @param  mixed  $offer
     * @return mixed
     */
    protected static function extendOffer($offer)
    {
        if (! is_array($offer)) {
            return $offer;
        }

        if (! empty($offer['availability'])) {
            $offer['availability'] = str_replace(
                'http://schema.org',
                'https://schema.org',
                (string) $offer['availability'],
            );
        }

        $offer['hasMerchantReturnPolicy'] = [
            '@type' => 'MerchantReturnPolicy',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => self::RETURN_DAYS,
            'applicableCountry' => self::COUNTRY,
            'returnMethod' => 'https://schema.org/ReturnByMail',
        ];

        $offer['shippingDetails'] = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => '0',
                'currency' => get_woocommerce_currency(),
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => self::COUNTRY,
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 1,
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 2,
                ],
            ],
        ];

        return $offer;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function reviews(WC_Product $product): array
    {
        $comments = get_comments([
            'post_id' => $product->get_id(),
            'status' => 'approve',
            'number' => self::REVIEW_LIMIT,
        ]);

        $reviews = [];

        foreach ((array) $comments as $comment) {
            if (! $comment instanceof \WP_Comment) {
                continue;
            }

            $review = [
                '@type' => 'Review',
                'author' => $comment->comment_author,
                'datePublished' => mysql2date('c', $comment->comment_date),
                'reviewBody' => wp_trim_words($comment->comment_content, 80),
            ];

            $rating = get_comment_meta($comment->comment_ID, 'rating', true);

            if ($rating !== '' && $rating !== false) {
                $review['reviewRating'] = [
                    '@type' => 'Rating',
                    'ratingValue' => (float) $rating,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ];
            }

            $reviews[] = $review;
        }

        return $reviews;
    }
}
