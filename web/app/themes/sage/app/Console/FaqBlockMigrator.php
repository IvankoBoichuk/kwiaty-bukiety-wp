<?php

declare(strict_types=1);

namespace App\Console;

use App\Catalog\Faq;
use App\Support\PageSections;

/**
 * Moves a category's FAQ onto a page as an fa/section FAQ block.
 *
 * Written for the "Kwiaciarnie w Polsce" hub: production printed its six
 * questions on the category archive, but that URL now redirects to a page, and
 * a page is where an editor looks for its FAQ. The block renders the visible
 * accordion and the FAQPage markup from one source, like every other FAQ
 * section on the site.
 *
 * The questions come from the term's `kb_faq`, or, when the FAQ import missed
 * the term, straight from its legacy ACF field. Idempotent: a page that already
 * has an FAQ section is left alone.
 */
final class FaqBlockMigrator
{
    public const DEFAULT_TITLE = 'FAQ – Najczęściej zadawane pytania';

    /**
     * The empty FAQ shell production's category description carried, copied
     * into the page with the rest of that markup. It renders nothing but a gap.
     */
    private const EMPTY_LEGACY_SHELL = '#<section class="kb-section kb-faq">\s*<div class="kb-faq-list">\s*</div>\s*</section>#';

    /**
     * @return array{changed: bool, content: string, log: list<string>}
     */
    public function migrate(string $content, \WP_Term $term): array
    {
        if ($this->hasFaqSection($content)) {
            return ['changed' => false, 'content' => $content, 'log' => ['the page already has an FAQ section']];
        }

        $legacy = (string) get_term_meta($term->term_id, Faq::LEGACY_META_KEY, true);
        $faq = Faq::forTerm($term->term_id);
        $source = 'kb_faq';

        if ($faq === []) {
            $faq = Faq::parseLegacyHtml($legacy);
            $source = Faq::LEGACY_META_KEY;
        }

        if ($faq === []) {
            return ['changed' => false, 'content' => $content, 'log' => ["no questions on term '{$term->slug}'"]];
        }

        $log = [sprintf('%d questions from %s', \count($faq), $source)];

        $stripped = (string) preg_replace(self::EMPTY_LEGACY_SHELL, '', $content, -1, $removed);

        if ($removed > 0) {
            $log[] = 'removed the empty legacy kb-faq shell';
        }

        $block = serialize_block($this->section($this->title($legacy), $faq));

        return [
            'changed' => true,
            'content' => rtrim($stripped) . "\n\n" . $block . "\n",
            'log' => $log,
        ];
    }

    private function hasFaqSection(string $content): bool
    {
        foreach (parse_blocks($content) as $block) {
            if (($block['blockName'] ?? null) === PageSections::SECTION_BLOCK && ($block['attrs']['variant'] ?? '') === 'faq') {
                return true;
            }
        }

        return false;
    }

    /**
     * The heading the legacy block opened with, so the page keeps the wording
     * production showed.
     */
    private function title(string $legacy): string
    {
        if (preg_match('#<h2[^>]*>(.*?)</h2>#is', $legacy, $match)) {
            $title = trim(html_entity_decode(wp_strip_all_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($title !== '') {
                return $title;
            }
        }

        return self::DEFAULT_TITLE;
    }

    /**
     * The tree LegacyBlockMigrator builds for a legacy FAQ list: a header with
     * the title, then the list of question/answer items.
     *
     * @param  array<int, array{q: string, a: string}>  $faq
     * @return array<string, mixed>
     */
    private function section(string $title, array $faq): array
    {
        $heading = '<h2 class="wp-block-heading">' . esc_html($title) . '</h2>';

        return $this->block('fa/section', [
            'variant' => 'faq',
            'layout' => '1',
            'layouts' => [['label' => 'First', 'value' => '1']],
        ], [
            $this->block('fa/header', [], [
                $this->block('fa/title', [], [[
                    'blockName' => 'core/heading',
                    'attrs' => ['level' => 2],
                    'innerBlocks' => [],
                    'innerHTML' => "\n{$heading}\n",
                    'innerContent' => ["\n{$heading}\n"],
                ]]),
            ]),
            $this->block('fa/list', [
                'list' => [
                    'layout' => null,
                    'fields' => ['ttl', 'text'],
                    'textIfEmpty' => null,
                    'ttl' => null,
                    'items' => array_map(
                        static fn(array $item): array => [
                            'ttl' => ['text' => $item['q'], 'level' => 'h3'],
                            'subttl' => null,
                            'text' => $item['a'],
                            'image' => null,
                            'icon' => null,
                            'link' => null,
                            'post' => null,
                            'meta' => null,
                        ],
                        $faq,
                    ),
                ],
            ]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $innerBlocks
     * @return array<string, mixed>
     */
    private function block(string $name, array $attrs = [], array $innerBlocks = []): array
    {
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $innerBlocks,
            'innerHTML' => '',
            'innerContent' => array_fill(0, \count($innerBlocks), null),
        ];
    }
}
