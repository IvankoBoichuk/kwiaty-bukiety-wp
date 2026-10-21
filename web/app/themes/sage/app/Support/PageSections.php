<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Splits a text page into runs of editor copy and the fa/section blocks placed
 * between them.
 *
 * A text page prints its copy inside the article measure, while an fa/section
 * brings its own container and must not be wrapped. A page that is copy with a
 * section added -- the "Kwiaciarnie w Polsce" hub with its FAQ block -- needs
 * both, so content-page renders each run in its own shape instead of choosing
 * one for the whole page.
 */
final class PageSections
{
    public const SECTION_BLOCK = 'fa/section';

    /**
     * True when the page opens on a section: such a page is assembled from
     * sections and prints bare, its title carried by the first of them.
     */
    public static function startsWithSection(string $content): bool
    {
        foreach (parse_blocks($content) as $block) {
            if (self::isBlank($block)) {
                continue;
            }

            return ($block['blockName'] ?? null) === self::SECTION_BLOCK;
        }

        return false;
    }

    /**
     * Consecutive blocks grouped by kind, in source order, each group as the
     * block markup it was parsed from. Whitespace between blocks stays with the
     * run it follows and never forms a run of its own.
     *
     * @return list<array{section: bool, content: string}>
     */
    public static function split(string $content): array
    {
        $runs = [];

        foreach (parse_blocks($content) as $block) {
            $isSection = ($block['blockName'] ?? null) === self::SECTION_BLOCK;
            $last = array_key_last($runs);

            if ($last !== null && (self::isBlank($block) || $runs[$last]['section'] === $isSection)) {
                $runs[$last]['blocks'][] = $block;

                continue;
            }

            if (self::isBlank($block)) {
                continue;
            }

            $runs[] = ['section' => $isSection, 'blocks' => [$block]];
        }

        return array_map(
            static fn(array $run): array => [
                'section' => $run['section'],
                'content' => serialize_blocks($run['blocks']),
            ],
            $runs,
        );
    }

    /**
     * A run rendered the way the_content() renders a whole post.
     */
    public static function render(string $content): string
    {
        return str_replace(']]>', ']]&gt;', (string) apply_filters('the_content', $content));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function isBlank(array $block): bool
    {
        return ($block['blockName'] ?? null) === null && trim((string) ($block['innerHTML'] ?? '')) === '';
    }
}
