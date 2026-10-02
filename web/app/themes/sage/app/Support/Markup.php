<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Vite;
use Throwable;
use Timber\Image;

/**
 * Presentation helpers shared by the fa/section templates and the WooCommerce
 * partials. These outlived App\Blocks\Blocks, which registered the legacy
 * sage/* blocks and was removed once the last of them was migrated.
 */
final class Markup
{
    /**
     * Resolve a built asset, or null when it is not in the manifest.
     *
     * webfonts.css only exists because vite-plugin-webfont-dl downloads the
     * Google Fonts CSS at build time. A build without egress produces no such
     * entry, and an unguarded Vite::asset() would then throw on every page of
     * the site rather than just lose the webfont preload.
     */
    public static function viteAsset(string $asset): ?string
    {
        try {
            return Vite::asset($asset);
        } catch (Throwable) {
            return null;
        }
    }

    public static function multilineTitle(?string $value): string
    {
        $value = trim((string) $value);
        $openingTag = '';
        $closingTag = '';
        $content = $value;

        if (
            preg_match(
                '/\A\s*(<(?<tag>h[1-6])\b[^>]*>)(.*)(<\/\k<tag>\s*>)\s*\z/is',
                $value,
                $matches,
            ) === 1
        ) {
            $openingTag = $matches[1];
            $content = $matches[3];
            $closingTag = $matches[4];
        }

        $lines = preg_split('/\r\n|\r|\n|<br\s*\/?>/i', $content) ?: [];
        $lines = array_values(
            array_filter(
                array_map('trim', $lines),
                static fn($line) => $line !== '',
            ),
        );

        if ($lines === []) {
            return '';
        }

        if (\count($lines) === 3) {
            $lineTemplates = [
                '<span class="font-heading font-semibold text-[38px] leading-11.5 -mb-1.25 md:text-[56px] md:mb-3 lg:m-0 lg:text-[99px] lg:leading-21.5">%s</span>',
                '<span class="h3-mobile -mb-2.5 md:text-[22px] lg:text-[99px] lg:font-heading lg:leading-21.5 lg:m-0 block lg:inline lg:ml-2">%s</span>',
                '<span class="font-heading font-medium italic text-[42px] leading-[1.2] md:text-[64px] lg:text-[115px] lg:leading-[1.2] -mt-4 block">%s</span>',
            ];
        } else {
            $lineTemplates = array_fill(
                0,
                \count($lines),
                '<span class="text-offer-ttl">%s</span>',
            );
        }

        $spans = implode('', array_map(
            static fn(string $line, int $index): string => sprintf(
                $lineTemplates[$index],
                wp_kses_post($line),
            ),
            $lines,
            array_keys($lines),
        ));

        return $openingTag . $spans . $closingTag;
    }

    public static function buttonClasses(
        string $variant = 'purple',
        string $size = 'md',
        bool $showIcon = true,
    ): string {
        $classes = [
            'inline-flex',
            'items-center',
            'justify-center',
            'rounded-full',
            'transition-all',
            'duration-200',
        ];

        $classes = array_merge(
            $classes,
            match ($variant) {
                'green' => [
                    'bg-green-dark',
                    'text-gray-6',
                    'border-2',
                    'border-green-default',
                ],
                'border' => [
                    'bg-background',
                    'text-green-default',
                    'border-2',
                    'border-green-default',
                    'font-semibold',
                ],
                default => [
                    'bg-purple-dark',
                    'text-gray-6',
                    'border-2',
                    'border-purple-dark',
                ],
            },
        );

        $classes = array_merge(
            $classes,
            match ($size) {
                'xs' => [
                    'px-5.5',
                    'py-1.5',
                    'font-semibold',
                    'text-[14px]',
                    'gap-1',
                ],
                'sm' => ['px-2.5', 'py-1', 'text-sm', 'gap-1'],
                'lg' => [
                    'w-full',
                    'px-8',
                    'py-4',
                    'font-semibold',
                    'text-[13px]',
                    'uppercase',
                    'gap-2',
                ],
                default => ['px-3', 'py-1.5', 'text-base', 'gap-1.5'],
            },
        );

        if (!$showIcon) {
            $classes[] = 'gap-0';
        }

        return implode(' ', $classes);
    }

    public static function buttonIconSize(string $size = 'md'): string
    {
        return match ($size) {
            'xs' => '20',
            'sm' => '24',
            'lg' => '40',
            default => '32',
        };
    }

    public static function sanitizeSvg(Image $svg): string
    {
        $path = $svg->file_loc();

        if (
            strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'svg'
            || !is_file($path)
            || !is_readable($path)
        ) {
            return '';
        }

        $contents = file_get_contents($path);

        if (
            !is_string($contents)
            || preg_match('/<svg\b[^>]*>.*<\/svg>/is', $contents, $matches) !== 1
        ) {
            return '';
        }

        $allowed = [
            'svg' => [
                'width' => true,
                'height' => true,
                'viewbox' => true,
                'fill' => true,
                'stroke' => true,
                'xmlns' => true,
                'class' => true,
                'role' => true,
                'aria-hidden' => true,
                'aria-label' => true,
                'focusable' => true,
            ],
            'path' => [
                'd' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
                'stroke-miterlimit' => true,
                'clip-path' => true,
                'fill-rule' => true,
                'clip-rule' => true,
                'opacity' => true,
                'transform' => true,
            ],
            'g' => [
                'clip-path' => true,
                'fill' => true,
                'stroke' => true,
                'opacity' => true,
                'transform' => true,
            ],
            'defs' => [],
            'clippath' => ['id' => true],
            'rect' => [
                'x' => true,
                'y' => true,
                'width' => true,
                'height' => true,
                'fill' => true,
                'stroke' => true,
                'rx' => true,
                'ry' => true,
                'transform' => true,
            ],
            'circle' => [
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
                'stroke' => true,
            ],
            'ellipse' => [
                'cx' => true,
                'cy' => true,
                'rx' => true,
                'ry' => true,
                'fill' => true,
                'stroke' => true,
            ],
            'line' => [
                'x1' => true,
                'y1' => true,
                'x2' => true,
                'y2' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
            ],
            'polygon' => ['points' => true, 'fill' => true, 'stroke' => true],
            'polyline' => ['points' => true, 'fill' => true, 'stroke' => true],
            'lineargradient' => [
                'id' => true,
                'x1' => true,
                'y1' => true,
                'x2' => true,
                'y2' => true,
                'gradientunits' => true,
                'gradienttransform' => true,
            ],
            'radialgradient' => [
                'id' => true,
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fx' => true,
                'fy' => true,
                'gradientunits' => true,
                'gradienttransform' => true,
            ],
            'stop' => [
                'offset' => true,
                'stop-color' => true,
                'stop-opacity' => true,
            ],
            'title' => [],
            'desc' => [],
        ];

        $sanitized = trim(wp_kses($matches[0], $allowed));

        return str_starts_with(strtolower($sanitized), '<svg')
            ? $sanitized
            : '';
    }

    public static function faqSchema(array $items): string
    {
        $items = array_values(
            array_filter(
                $items,
                static fn($item) => !empty($item['title'])
                    && !empty($item['text']),
            ),
        );

        if ($items === []) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                static fn($item) => [
                    '@type' => 'Question',
                    'name' => wp_strip_all_tags((string) $item['title']),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags((string) $item['text']),
                    ],
                ],
                $items,
            ),
        ];

        return sprintf(
            '<script type="application/ld+json">%s</script>',
            wp_json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    /**
     * Promote a section's media to the priority the LCP element needs.
     *
     * MediaRenderer takes its priority from the block's own attribute, which
     * defaults to low, so the hero of a page ships fetchpriority="low"
     * loading="lazy" unless an editor remembers to flip it -- and a lazily
     * loaded LCP image is exactly what Lighthouse flags.
     *
     * The rendered tag is rewritten rather than re-rendered: the slot API takes
     * no priority argument, and going around it would lose the <picture>, its
     * per-breakpoint sources and the focal-point style. Media an editor already
     * marked high carries neither string and passes through untouched.
     */
    public static function asLcpMedia(string $html): string
    {
        return str_replace(
            ['fetchpriority="low"', 'loading="lazy"'],
            ['fetchpriority="high"', 'loading="eager"'],
            $html,
        );
    }
}
