<?php

declare(strict_types=1);

namespace App\Console;

/**
 * Rewrites the legacy `sage/*` blocks in a post into `fa/section` variants.
 *
 * The legacy blocks kept every field in one flat attribute bag; fa/section
 * composes the same content out of real inner blocks, so the conversion is a
 * tree build rather than a rename. Runs once per post and is idempotent: a post
 * with no `sage/*` blocks left is reported as unchanged.
 */
final class LegacyBlockMigrator
{
    /**
     * Section layouts advertised to the editor, mirroring the plugin's
     * block.json. A layout missing here renders, but the editor cannot switch
     * to it.
     *
     * @var array<string, list<array{label: string, value: string}>>
     */
    private const LAYOUTS = [
        'products' => [
            ['label' => 'Slider', 'value' => '1'],
            ['label' => 'Grid', 'value' => '2'],
        ],
        'reviews' => [
            ['label' => 'First', 'value' => '1'],
        ],
        'benefits' => [
            ['label' => 'First', 'value' => '1'],
            ['label' => 'Cards', 'value' => '2'],
        ],
        'faq' => [
            ['label' => 'First', 'value' => '1'],
        ],
        'cities' => [
            ['label' => 'First', 'value' => '1'],
        ],
    ];

    /** @var list<string> */
    private array $log = [];

    /**
     * @return array{changed: bool, content: string, log: list<string>}
     */
    public function migrate(string $content): array
    {
        $this->log = [];
        $blocks = parse_blocks($content);
        $result = [];

        foreach ($blocks as $block) {
            $converted = $this->convert($block);

            foreach ($converted as $item) {
                $result[] = $item;
            }
        }

        $serialized = serialize_blocks($result);

        return [
            'changed' => $serialized !== $content,
            'content' => $serialized,
            'log' => $this->log,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function convert(array $block): array
    {
        $attrs = $block['attrs'] ?? [];

        return match ($block['blockName'] ?? null) {
            // The homepage carries sage/offer directly above an fa/section
            // offer that already replaced it; keeping both would render the
            // hero twice.
            'sage/offer' => $this->drop('sage/offer (duplicate of the fa/section offer)'),
            'sage/products' => [$this->products($attrs)],
            'sage/reviews' => [$this->reviews($attrs)],
            'sage/list' => [$this->list($attrs)],
            'sage/cities' => [$this->cities($attrs)],
            default => [$block],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drop(string $what): array
    {
        $this->log[] = "removed {$what}";

        return [];
    }

    private function products(array $attrs): array
    {
        $texts = $this->texts($attrs);
        $layout = ($attrs['layout'] ?? '') === 'popular' ? '2' : '1';
        $productIds = $this->ids($attrs['products'] ?? []);

        $this->log[] = sprintf(
            'sage/products -> fa/section products layout %s (%d products)',
            $layout,
            \count($productIds),
        );

        return $this->section('products', $layout, array_filter([
            $this->header($texts),
            $this->text($texts['text'] ?? ''),
            $this->block('fa/query', [
                'query' => [
                    'mode' => 'manual',
                    'postType' => 'product',
                    'postIds' => $productIds,
                    'perPage' => max(1, \count($productIds)),
                    'orderBy' => 'date',
                    'order' => 'desc',
                ],
            ]),
        ]));
    }

    private function reviews(array $attrs): array
    {
        $texts = $this->texts($attrs);
        $commentIds = $this->ids($attrs['reviews'] ?? []);

        $this->log[] = sprintf(
            'sage/reviews -> fa/section reviews layout 1 (%d comments)',
            \count($commentIds),
        );

        return $this->section('reviews', '1', array_filter([
            $this->header($texts),
            $this->text($texts['text'] ?? ''),
            $this->block('fa/reviews', [
                'reviews' => [
                    'source' => 'comment',
                    'mode' => 'manual',
                    'commentIds' => $commentIds,
                    'perPage' => max(1, \count($commentIds)),
                    'order' => 'desc',
                ],
            ]),
        ]));
    }

    private function list(array $attrs): array
    {
        $texts = $this->texts($attrs);
        $legacyLayout = (string) ($attrs['layout'] ?? '');

        // The legacy `list` block multiplexed three unrelated sections through
        // its layout attribute; each maps onto a different fa/section variant.
        [$variant, $layout, $cardLayout] = match ($legacyLayout) {
            'faq' => ['faq', '1', null],
            'guarantees' => ['benefits', '2', null],
            default => ['benefits', '1', 'numbered-step'],
        };

        $items = array_values(array_filter(array_map(
            fn($item) => $this->listItem((array) $item),
            (array) ($attrs['list'] ?? []),
        )));

        $this->log[] = sprintf(
            'sage/list (%s) -> fa/section %s layout %s (%d items)',
            $legacyLayout !== '' ? $legacyLayout : 'how-it-works',
            $variant,
            $layout,
            \count($items),
        );

        return $this->section($variant, $layout, array_filter([
            $this->header($texts),
            $this->text($texts['text'] ?? ''),
            $this->block('fa/list', [
                'list' => [
                    'layout' => $cardLayout,
                    'fields' => $cardLayout === 'numbered-step'
                        ? ['ttl', 'text']
                        : ['ttl', 'text', 'icon'],
                    'textIfEmpty' => null,
                    'ttl' => null,
                    'items' => $items,
                ],
            ]),
        ]));
    }

    private function cities(array $attrs): array
    {
        $texts = $this->texts($attrs);
        $termIds = $this->ids($attrs['cities'] ?? []);

        $this->log[] = sprintf(
            'sage/cities -> fa/section cities layout 1 (%d pinned terms)',
            \count($termIds),
        );

        return $this->section('cities', '1', array_filter([
            $this->header($texts),
            $this->text($texts['text'] ?? ''),
            $this->block('fa/terms', [
                'terms' => [
                    'mode' => $termIds !== [] ? 'manual' : 'automatic',
                    'taxonomy' => 'product_cat',
                    'termIds' => $termIds,
                    'perPage' => 9,
                    'orderBy' => 'name',
                    'order' => 'asc',
                    'hideEmpty' => false,
                    'nameLike' => 'Kwiaciarnia',
                ],
            ]),
        ]));
    }

    /**
     * The legacy list item stored `icon` as a URL; fa/list wants an attachment
     * id, so resolve it and drop the icon when the file is not in the library.
     */
    private function listItem(array $item): ?array
    {
        $title = trim((string) ($item['title'] ?? ''));
        $text = trim((string) ($item['text'] ?? ''));

        if ($title === '' && $text === '') {
            return null;
        }

        $icon = null;
        $iconUrl = trim((string) ($item['icon'] ?? ''));

        if ($iconUrl !== '') {
            $attachmentId = attachment_url_to_postid($iconUrl);

            if ($attachmentId > 0) {
                $icon = ['id' => $attachmentId];
            } else {
                $this->log[] = "  ! icon not found in the media library: {$iconUrl}";
            }
        }

        return [
            'ttl' => $title !== '' ? ['text' => $title, 'level' => 'h3'] : null,
            'subttl' => null,
            'text' => $text !== '' ? $text : null,
            'image' => null,
            'icon' => $icon,
            'link' => null,
            'post' => null,
            'meta' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $inner
     */
    private function section(string $variant, string $layout, array $inner): array
    {
        return $this->block(
            'fa/section',
            [
                'variant' => $variant,
                'layout' => $layout,
                'layouts' => self::LAYOUTS[$variant] ?? [['label' => 'First', 'value' => '1']],
            ],
            array_values($inner),
        );
    }

    private function header(array $texts): ?array
    {
        $inner = [];
        $subtitle = trim((string) ($texts['subtitle'] ?? ''));
        $title = trim((string) ($texts['title'] ?? ''));

        if ($subtitle !== '') {
            $inner[] = $this->block('fa/subtitle', [], [
                $this->paragraph($subtitle),
            ]);
        }

        if ($title !== '') {
            $inner[] = $this->block('fa/title', [], [
                $this->heading($title),
            ]);
        }

        return $inner === [] ? null : $this->block('fa/header', [], $inner);
    }

    private function text(string $text): ?array
    {
        $text = trim($text);

        return $text === '' ? null : $this->block('fa/text', [], [
            $this->paragraph($text),
        ]);
    }

    private function heading(string $text): array
    {
        $html = $this->inlineHtml($text);

        return [
            'blockName' => 'core/heading',
            'attrs' => ['level' => 2],
            'innerBlocks' => [],
            'innerHTML' => "\n<h2 class=\"wp-block-heading\">{$html}</h2>\n",
            'innerContent' => ["\n<h2 class=\"wp-block-heading\">{$html}</h2>\n"],
        ];
    }

    private function paragraph(string $text): array
    {
        $html = $this->inlineHtml($text);

        return [
            'blockName' => 'core/paragraph',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => "\n<p>{$html}</p>\n",
            'innerContent' => ["\n<p>{$html}</p>\n"],
        ];
    }

    /**
     * Legacy titles used literal newlines for line breaks; block markup needs
     * <br> instead, and everything else must be escaped.
     */
    private function inlineHtml(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $lines = array_map(
            static fn(string $line): string => esc_html(trim($line)),
            $lines,
        );

        return implode('<br>', array_filter($lines, static fn($line) => $line !== ''));
    }

    /**
     * @param list<array<string, mixed>> $innerBlocks
     */
    private function block(string $name, array $attrs = [], array $innerBlocks = []): array
    {
        $innerContent = array_fill(0, \count($innerBlocks), null);

        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $innerBlocks,
            'innerHTML' => '',
            'innerContent' => $innerContent,
        ];
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            'absint',
            \is_array($value) ? $value : [],
        ))));
    }

    private function texts(array $attrs): array
    {
        return \is_array($attrs['texts'] ?? null) ? $attrs['texts'] : [];
    }
}
