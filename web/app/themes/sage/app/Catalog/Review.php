<?php

namespace App\Catalog;

use WP_Comment;

final class Review
{
    private readonly float $decimal;
    private readonly bool $hasPartialStar;
    private readonly int $partialStarFill;
    private readonly int $totalEmptyStars;

    public function __construct(
        public readonly int $reviewId,
        public readonly string $name,
        public readonly string $location,
        public readonly float $rating,
        public readonly int $fullStars,
        public readonly string $text,
    ) {
        $this->decimal = $this->rating - $this->fullStars;
        $this->hasPartialStar = $this->decimal >= 0.1;
        $this->partialStarFill = (int) round($this->decimal * 100);
        $this->totalEmptyStars = $this->hasPartialStar
            ? 5 - $this->fullStars - 1
            : 5 - $this->fullStars;
    }

    public static function fromWordPressComment(WP_Comment $comment): self
    {
        $location = (string) get_comment_meta(
            $comment->comment_ID,
            'location',
            true,
        );
        $rating
            = (float) (get_comment_meta($comment->comment_ID, 'rating', true)
                ?? 0);

        return new self(
            reviewId: (int) $comment->comment_ID,
            name: (string) ($comment->comment_author ?? ''),
            location: $location,
            rating: $rating,
            fullStars: (int) floor($rating),
            text: (string) ($comment->comment_content ?? ''),
        );
    }

    // Method accessors below mirror Frontenda\Blocks\SlotComment's API so
    // partials/review-card.blade.php can render either type interchangeably.
    public function id(): int
    {
        return $this->reviewId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function fullStars(): int
    {
        return $this->fullStars;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function hasPartialStar(): bool
    {
        return $this->hasPartialStar;
    }

    public function partialStarFill(): int
    {
        return $this->partialStarFill;
    }

    public function totalEmptyStars(): int
    {
        return $this->totalEmptyStars;
    }
}
