<?php

namespace App\Catalog;

use App\Admin\ProductAttributeIcons;
use Timber\Timber;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;
use WC_Product_Variable;

final class SingleProductView
{
    private ProductData $data;

    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function __construct(public readonly WC_Product $product)
    {
        $this->data = new ProductData($product);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->product->get_id(),
            'title' => $this->product->get_name(),
            'priceHtml' => $this->product->get_price_html(),
            'shortDescription' => (string) $this->product->get_short_description(),
            'description' => (string) $this->product->get_description(),
            'sku' => (string) $this->product->get_sku(),
            'badges' => $this->data->badges(),
            'gallery' => $this->gallery(),
            'variations' => $this->variations(),
            'variationAttributes' => $this->variationAttributes(),
            'availableVariations' => $this->availableVariations(),
            'isVariable' => $this->product instanceof WC_Product_Variable,
            'additions' => $this->crossSells(),
            'reviews' => $this->reviews(),
            'reviewCount' => (int) $this->product->get_review_count(),
            'averageRating' => (float) $this->product->get_average_rating(),
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    protected function gallery(): array
    {
        $imageIds = array_values(
            array_filter(
                array_merge(
                    [$this->product->get_image_id()],
                    $this->product->get_gallery_image_ids(),
                ),
            ),
        );

        if ($imageIds === []) {
            return [
                [
                    'src' => wc_placeholder_img_src('large'),
                    'alt' => $this->product->get_name(),
                    'width' => 0,
                    'height' => 0,
                    'srcset' => '',
                    'sizes' => '',
                ],
            ];
        }

        return array_map(function (int $attachmentId): array {
            $image = Timber::get_image($attachmentId);

            if ($image === null) {
                return [
                    'src' => wc_placeholder_img_src('large'),
                    'alt' => $this->product->get_name(),
                    'width' => 0,
                    'height' => 0,
                    'srcset' => '',
                    'sizes' => '',
                ];
            }

            return [
                'src' => $image->src('large'),
                'alt' => $image->alt() ?: $this->product->get_name(),
                'width' => $image->width() ?? 0,
                'height' => $image->height() ?? 0,
                'srcset' => $image->srcset('large') ?? '',
                'sizes' => $image->img_sizes('large') ?? '',
            ];
        }, $imageIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function availableVariations(): array
    {
        if (!($this->product instanceof WC_Product_Variable)) {
            return [];
        }

        if (isset($this->cache['availableVariations'])) {
            return $this->cache['availableVariations'];
        }

        $this->cache['availableVariations'] = $this->product->get_available_variations();

        return $this->cache['availableVariations'];
    }

    /**
    * @return array<int, array{key: string, label: string, icon: array{src: string, alt: string}|null, options: array<int, array{value: string, label: string, priceHtml: string, description: string}>}>
     */
    protected function variationAttributes(): array
    {
        if (!($this->product instanceof WC_Product_Variable)) {
            return [];
        }

        if (isset($this->cache['variationAttributes'])) {
            return $this->cache['variationAttributes'];
        }

        $availableVariations = $this->availableVariations();

        if ($availableVariations === []) {
            return [];
        }

        $availableOptions = $this->availableVariationOptions($availableVariations);
        $groups = [];

        foreach ($this->product->get_attributes() as $attributeName => $attribute) {
            if (!($attribute instanceof WC_Product_Attribute) || !$attribute->get_variation()) {
                continue;
            }

            $attributeKey = $this->normalizeVariationAttributeKey((string) $attributeName);
            $attributeOptions = $this->resolveVariationAttributeOptions(
                $attribute,
                array_keys($availableOptions[$attributeKey] ?? []),
            );

            if ($attributeOptions === []) {
                continue;
            }

            $groups[] = [
                'key' => $attributeKey,
                'label' => $this->variationAttributeLabel($attributeKey),
                'icon' => $this->variationAttributeIcon($attributeKey),
                'options' => array_map(
                    fn(array $option): array => $this->variationAttributeOptionWithPreview(
                        $attributeKey,
                        $option,
                        $availableVariations,
                    ),
                    $attributeOptions,
                ),
            ];
        }

        $knownAttributeKeys = array_column($groups, 'key');

        foreach ($availableOptions as $attributeKey => $options) {
            if (in_array($attributeKey, $knownAttributeKeys, true)) {
                continue;
            }

            $groups[] = [
                'key' => $attributeKey,
                'label' => $this->variationAttributeLabel($attributeKey),
                'icon' => $this->variationAttributeIcon($attributeKey),
                'options' => array_map(
                    fn(string $value): array => $this->variationAttributeOptionWithPreview(
                        $attributeKey,
                        [
                            'value' => $value,
                            'label' => $this->variationAttributeValueLabel($attributeKey, $value),
                        ],
                        $availableVariations,
                    ),
                    array_keys($options),
                ),
            ];
        }

        $this->cache['variationAttributes'] = $groups;

        return $this->cache['variationAttributes'];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    protected function variations(): array
    {
        if (isset($this->cache['variations'])) {
            return $this->cache['variations'];
        }

        if (!($this->product instanceof WC_Product_Variable)) {
            $this->cache['variations'] = [
                [
                    'id' => $this->product->get_id(),
                    'name' => __('Standard', 'sage-front'),
                    'price' => wp_strip_all_tags(
                        $this->product->get_price_html(),
                    ),
                    'description'
                        => $this->product->get_short_description() !== ''
                            ? wp_strip_all_tags(
                                $this->product->get_short_description(),
                            )
                            : __('Gotowy do zamowienia od razu.', 'sage-front'),
                ],
            ];

            return $this->cache['variations'];
        }

        $variations = [];

        foreach ($this->availableVariations() as $variationData) {
            $variationId = absint($variationData['variation_id'] ?? 0);

            if ($variationId <= 0) {
                continue;
            }

            $variation = wc_get_product($variationId);

            if (!($variation instanceof WC_Product_Variation)) {
                continue;
            }

            $attributeSummary = wc_get_formatted_variation(
                $variation,
                true,
                false,
                true,
            );

            $description
                = $variation->get_description()
                ?: $variation->get_short_description();

            $variations[] = [
                'id' => $variationId,
                'name' => $variation->get_name(),
                'price' => wp_strip_all_tags($variation->get_price_html()),
                'description'
                    => $description !== ''
                        ? wp_strip_all_tags($description)
                        : wp_strip_all_tags((string) $attributeSummary),
            ];
        }

        $this->cache['variations'] = $variations;

        return $this->cache['variations'];
    }

    /**
     * @param array<int, array<string, mixed>> $availableVariations
     * @return array<string, array<string, bool>>
     */
    protected function availableVariationOptions(array $availableVariations): array
    {
        $options = [];

        foreach ($availableVariations as $variation) {
            $attributes = $variation['attributes'] ?? [];

            if (!is_array($attributes)) {
                continue;
            }

            foreach ($attributes as $attributeName => $attributeValue) {
                if (!is_string($attributeName) || !is_string($attributeValue) || $attributeValue === '') {
                    continue;
                }

                $options[$attributeName][$attributeValue] = true;
            }
        }

        return $options;
    }

    protected function normalizeVariationAttributeKey(string $attributeName): string
    {
        if (str_starts_with($attributeName, 'attribute_')) {
            return $attributeName;
        }

        return 'attribute_' . $attributeName;
    }

    /**
     * @param array<int, string> $availableValues
     * @return array<int, array{value: string, label: string}>
     */
    protected function resolveVariationAttributeOptions(
        WC_Product_Attribute $attribute,
        array $availableValues,
    ): array {
        if ($availableValues === []) {
            return [];
        }

        $options = [];

        if ($attribute->is_taxonomy()) {
            foreach (wc_get_product_terms($this->product->get_id(), $attribute->get_name(), ['fields' => 'all']) as $term) {
                $slug = isset($term->slug) ? (string) $term->slug : '';

                if ($slug === '' || !in_array($slug, $availableValues, true)) {
                    continue;
                }

                $options[] = [
                    'value' => $slug,
                    'label' => trim(wp_strip_all_tags((string) $term->name)),
                ];
            }
        } else {
            foreach ($attribute->get_options() as $option) {
                $rawValue = trim(wp_strip_all_tags((string) $option));
                $slugValue = sanitize_title($rawValue);
                $value = in_array($rawValue, $availableValues, true)
                    ? $rawValue
                    : (in_array($slugValue, $availableValues, true) ? $slugValue : '');

                if ($value === '') {
                    continue;
                }

                $options[] = [
                    'value' => $value,
                    'label' => $rawValue,
                ];
            }
        }

        if ($options !== []) {
            return $options;
        }

        return array_map(
            fn(string $value): array => [
                'value' => $value,
                'label' => $this->variationAttributeValueLabel(
                    $this->normalizeVariationAttributeKey($attribute->get_name()),
                    $value,
                ),
            ],
            $availableValues,
        );
    }

    protected function variationAttributeLabel(string $attributeName): string
    {
        $taxonomy = preg_replace('/^attribute_/i', '', $attributeName) ?: '';

        if ($taxonomy === '') {
            return '';
        }

        if (function_exists('wc_attribute_label')) {
            $label = trim(wp_strip_all_tags((string) wc_attribute_label($taxonomy, $this->product)));

            if ($label !== '') {
                return $label;
            }
        }

        return trim(wp_strip_all_tags($taxonomy));
    }

    protected function variationAttributeValueLabel(string $attributeName, string $value): string
    {
        $taxonomy = preg_replace('/^attribute_/i', '', $attributeName) ?: '';
        $cleanValue = trim(wp_strip_all_tags($value));

        if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
            $term = get_term_by('slug', $cleanValue, $taxonomy);

            if ($term && !is_wp_error($term)) {
                return trim(wp_strip_all_tags((string) $term->name));
            }
        }

        return $cleanValue;
    }

    /**
     * @return array{src: string, alt: string}|null
     */
    protected function variationAttributeIcon(string $attributeName): ?array
    {
        $label = $this->variationAttributeLabel($attributeName);
        $taxonomy = preg_replace('/^attribute_/i', '', $attributeName) ?: '';

        if ($taxonomy === '') {
            return null;
        }

        return ProductAttributeIcons::getIconDataByTaxonomy($taxonomy, $label);
    }

    /**
     * @param array{value: string, label: string} $option
     * @param array<int, array<string, mixed>> $availableVariations
     * @return array{value: string, label: string, priceHtml: string, description: string}
     */
    protected function variationAttributeOptionWithPreview(
        string $attributeKey,
        array $option,
        array $availableVariations,
    ): array {
        $preview = $this->variationAttributePreview(
            $attributeKey,
            $option['value'],
            $availableVariations,
        );

        return [
            'value' => $option['value'],
            'label' => $option['label'],
            'priceHtml' => $preview['priceHtml'],
            'description' => $preview['description'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $availableVariations
     * @return array{priceHtml: string, description: string}
     */
    protected function variationAttributePreview(
        string $attributeKey,
        string $attributeValue,
        array $availableVariations,
    ): array {
        foreach ($availableVariations as $variationData) {
            $attributes = $variationData['attributes'] ?? [];

            if (!is_array($attributes) || ($attributes[$attributeKey] ?? null) !== $attributeValue) {
                continue;
            }

            return [
                'priceHtml' => isset($variationData['price_html'])
                    ? (string) $variationData['price_html']
                    : '',
                'description' => $this->variationPreviewDescription($variationData),
            ];
        }

        return [
            'priceHtml' => '',
            'description' => '',
        ];
    }

    /**
     * @param array<string, mixed> $variationData
     */
    protected function variationPreviewDescription(array $variationData): string
    {
        $variationId = absint($variationData['variation_id'] ?? 0);
        $cacheKey = 'variationPreviewDescription:' . $variationId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $description = trim((string) ($variationData['variation_description'] ?? ''));

        if ($description !== '') {
            $this->cache[$cacheKey] = $description;

            return $this->cache[$cacheKey];
        }

        if ($variationId <= 0) {
            return '';
        }

        $variation = wc_get_product($variationId);

        if (!($variation instanceof WC_Product_Variation)) {
            return '';
        }

        $attributeSummary = wc_get_formatted_variation(
            $variation,
            true,
            false,
            true,
        );

        $variationDescription
            = $variation->get_description()
            ?: $variation->get_short_description();

        if ($variationDescription !== '') {
            $this->cache[$cacheKey] = wpautop(wp_kses_post($variationDescription));

            return $this->cache[$cacheKey];
        }

        $this->cache[$cacheKey] = wpautop(esc_html(wp_strip_all_tags((string) $attributeSummary)));

        return $this->cache[$cacheKey];
    }

    /**
     * @return array<int, Product>
     */
    protected function upSells(): array
    {
        $products = [];

        foreach ($this->product->get_upsell_ids() as $id) {
            $upsell = wc_get_product($id);

            if ($upsell instanceof WC_Product) {
                $products[] = Product::fromWooCommerce($upsell);
            }
        }

        return $products;
    }
    /**
     * @return array<int, Product>
     */
    protected function crossSells(): array
    {
        $products = [];

        foreach ($this->product->get_cross_sell_ids() as $id) {
            $crossSell = wc_get_product($id);

            if ($crossSell instanceof WC_Product) {
                $products[] = Product::fromWooCommerce($crossSell);
            }
        }

        return $products;
    }

    /**
     * @return array<int, Review>
     */
    protected function reviews(): array
    {
        return array_map(
            fn(\WP_Comment $comment) => Review::fromWordPressComment($comment),
            $this->data->approvedReviewComments(),
        );
    }

    /**
     * @return array<int, Product>
     */
    public function relatedProducts(int $limit = 6): array
    {
        return $this->data->relatedProducts($limit);
    }
}
