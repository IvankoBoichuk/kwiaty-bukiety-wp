<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Vite;
use Throwable;
use Timber\Image;
use WP_Post;

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

    /**
     * The listing excerpt of the post in the loop.
     *
     * get_the_excerpt() ends an auto-generated excerpt with the "... Continued"
     * link that app/filters.php installs on excerpt_more. That belongs under a
     * full post listing; a blog card already carries its own Read more control
     * and the design ends the copy on an ellipsis, so the filter is swapped out
     * for the length of the call.
     */
    public static function excerpt(): string
    {
        $ellipsis = static fn(): string => "\u{2026}";

        add_filter('excerpt_more', $ellipsis, 99);
        $excerpt = (string) get_the_excerpt();
        remove_filter('excerpt_more', $ellipsis, 99);

        return trim(wp_strip_all_tags($excerpt));
    }

    /**
     * The <img> a card shows for a post, or an empty string when it has none.
     *
     * A card used to ask for the featured image alone, which printed the grey
     * placeholder next to every imported article saved without one -- and the
     * body of those articles opens on a photo. So the thumbnail comes first and
     * the first image in the body stands in for it, which is the one rule every
     * card on the site now shares.
     *
     * The attachment id is what travels, not a URL: wp_get_attachment_image()
     * needs it to emit the srcset and sizes the cards rely on, so an <img>
     * hotlinked from outside the media library is skipped and the caller draws
     * its placeholder instead.
     *
     * @param  array<string, string>  $attr
     */
    public static function cardImage(
        string $size = 'large',
        array $attr = [],
        int|WP_Post|null $post = null,
    ): string {
        $post = get_post($post);
        $imageId = self::cardImageId($post);

        if ($imageId === null) {
            return '';
        }

        /*
         * A card is a link whose only other content is the title, so an
         * attachment saved without alt text would hand a screen reader an
         * unlabelled image inside it. The post title is what a reader of the
         * card sees next to the photo anyway.
         */
        $alt = trim(
            (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
        );

        if ($alt === '' && !isset($attr['alt'])) {
            $attr['alt'] = wp_strip_all_tags(get_the_title($post));
        }

        return wp_get_attachment_image($imageId, $size, false, $attr);
    }

    /**
     * The attachment behind cardImage(): the post thumbnail, else the first
     * image of the body.
     */
    public static function cardImageId(int|WP_Post|null $post = null): ?int
    {
        $post = get_post($post);

        if (!$post instanceof WP_Post) {
            return null;
        }

        $thumbnailId = (int) get_post_thumbnail_id($post);

        if ($thumbnailId > 0) {
            return $thumbnailId;
        }

        // A listing renders the same post once, but a single article asks for
        // its own image from the hero, the schema and the related block, and
        // scanning the body is the expensive half of the lookup.
        static $firstImage = [];

        if (!array_key_exists($post->ID, $firstImage)) {
            $firstImage[$post->ID] = self::firstContentImageId(
                (string) $post->post_content,
            );
        }

        return $firstImage[$post->ID];
    }

    /**
     * Every attachment id the images of a post body carry, in the order they
     * appear in it.
     *
     * The ids come from the markup the editor saves rather than from a rendered
     * copy of it: the_content() on another post's body would run every block
     * and shortcode on the page to find one number. An image block writes the
     * id twice -- in its own block comment and as the wp-image-N class of the
     * <img> -- while a cover or media-text block writes it only in the comment,
     * hence both halves of the pattern.
     *
     * @return array<int, int>
     */
    public static function contentImageIds(string $content): array
    {
        if (
            !str_contains($content, 'wp-image-')
            && !str_contains($content, '<!-- wp:')
        ) {
            return [];
        }

        preg_match_all(
            '/wp-image-(?<class>\d+)'
            . '|<!--\s*wp:(?:image|cover|media-text|gallery)\s+(?<attributes>\{.*?\})\s*\/?-->/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $ids = [];

        foreach ($matches as $match) {
            $id = 0;

            if (($match['class'] ?? '') !== '') {
                $id = (int) $match['class'];
            } elseif (
                ($match['attributes'] ?? '') !== ''
                && preg_match('/"id":\s*(\d+)/', $match['attributes'], $found)
                    === 1
            ) {
                $id = (int) $found[1];
            }

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * The first id of contentImageIds() that is still an image in the library.
     */
    private static function firstContentImageId(string $content): ?int
    {
        foreach (self::contentImageIds($content) as $id) {
            if (wp_attachment_is_image($id)) {
                return $id;
            }
        }

        return null;
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
