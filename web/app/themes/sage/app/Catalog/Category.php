<?php

namespace App\Catalog;

use Timber\Image;
use Timber\Timber;
use WP_Term;

final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $link,
        public readonly ?Image $image,
    ) {}

    public static function fromWordPressTerm(WP_Term $term): self
    {
        $thumbnailId = get_term_meta($term->term_id, 'thumbnail_id', true);

        return new self(
            id: $term->term_id,
            name: $term->name,
            link: (string) (get_term_link($term) ?: ''),
            image: Timber::get_image($thumbnailId),
        );
    }
}
