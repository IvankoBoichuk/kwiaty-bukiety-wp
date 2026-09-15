<?php

namespace App\Catalog;

use Timber\Image;
use Timber\Timber;
use WC_Product;
use WC_Product_Variable;

final class Product
{
    protected $cache = [];
    private ProductData $data;
    public readonly int $id;
    public readonly string $name;
    public readonly string $link;
    public readonly string $target;
    /** @var array<string, string> */
    public readonly array $badges;
    public readonly string $price;
    public readonly ?Image $image;
    public function __construct(public readonly WC_Product $product)
    {
        $this->data = new ProductData($product);
        $this->id = $product->get_id();
        $this->name = $product->get_name();
        $this->link = (string) (get_permalink($product->get_id()) ?: '');
        $this->target = '_self';
        $this->badges = $this->data->badges();

        $thumbnailId = $product->get_image_id();

        $this->price = $product->get_price_html();
        $this->image = Timber::get_image($thumbnailId);
    }
    public static function fromWooCommerce(WC_Product $product): self
    {
        return new self($product);
    }
    public static function fromID(int $id): self
    {
        $product = wc_get_product($id);
        if (!$product) {
            throw new \InvalidArgumentException(
                "Product with ID $id not found.",
            );
        }
        return new self($product);
    }

    public function isVariable(): bool
    {
        return $this->product instanceof WC_Product_Variable;
    }

    /**
     * @return array<int, array<string, int|string>>
     */

    public function getVariations(): array
    {
        if (!($this->product instanceof WC_Product_Variable)) {
            return [];
        }

        if (isset($this->cache['getVariations'])) {
            return $this->cache['getVariations'];
        }

        $variations = $this->product->get_available_variations();
        $this->cache['getVariations'] = $variations;
        return $this->cache['getVariations'];
    }

    public function crossSellProducts(): array
    {
        if (isset($this->cache['crossSellProducts'])) {
            return $this->cache['crossSellProducts'];
        }
        $crossSells = $this->product->get_cross_sell_ids();

        if (empty($crossSells)) {
            return [];
        }

        $this->cache['crossSellProducts'] = array_map(
            fn($id) => self::fromID($id),
            $crossSells,
        );

        return $this->cache['crossSellProducts'];
    }
    public function description(): string
    {
        if (isset($this->cache['description'])) {
            return $this->cache['description'];
        }
        $this->cache['description'] = $this->product->get_description();
        return $this->cache['description'];
    }
    public function reviews(): array
    {
        if (isset($this->cache['reviews'])) {
            return $this->cache['reviews'];
        }
        $this->cache['reviews'] = array_map(
            fn(\WP_Comment $comment) => Review::fromWordPressComment($comment),
            $this->data->approvedReviewComments(),
        );

        return $this->cache['reviews'];
    }
    public function relatedProducts(): array
    {
        if (isset($this->cache['relatedProducts'])) {
            return $this->cache['relatedProducts'];
        }
        $this->cache['relatedProducts'] = $this->data->relatedProducts();

        return $this->cache['relatedProducts'];
    }
    public function is_funeral(): bool
    {
        return $this->data->isFuneral();
    }
}
