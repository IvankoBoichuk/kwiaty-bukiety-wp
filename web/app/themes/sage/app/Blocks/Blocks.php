<?php

namespace App\Blocks;

use Illuminate\Support\Fluent;
use Timber\Image;
use WP_Block;

class Blocks
{
    /**
     * @var array<string, array<string, string>>
     */
    protected static array $blocks = [
        'offer' => ['title' => 'Offer'],
        'products' => ['title' => 'Products'],
        'numbers' => ['title' => 'Numbers'],
        'reviews' => ['title' => 'Reviews'],
        'list' => ['title' => 'List'],
        'cities' => ['title' => 'Cities'],
    ];

    public static function boot(): void
    {
        add_filter('block_categories_all', [self::class, 'addCategory']);
        add_action('init', [self::class, 'register']);
    }

    public static function addCategory(array $categories): array
    {
        array_unshift($categories, [
            'slug' => 'kwiaty-bukiety',
            'title' => __('Kwiaty Bukiety', 'sage-back'),
            'icon' => null,
        ]);

        return $categories;
    }

    public static function register(): void
    {
        foreach (self::$blocks as $slug => $config) {
            register_block_type("sage/{$slug}", [
                'api_version' => 3,
                'title' => __($config['title'], 'sage-back'),
                'render_callback' => fn(
                    array $attributes = [],
                    string $content = '',
                    ?WP_Block $block = null,
                ): string => self::render($slug, $attributes, $block),
                'attributes' => static::defaults()[$slug],
                'supports' => [
                    'anchor' => true,
                    'className' => true,
                    'html' => false,
                ],
            ]);
        }
    }

    public static function prepare(
        string $slug,
        array $attributes = [],
        ?WP_Block $block = null,
    ): array {
        $defaults = static::defaults()[$slug]['default'] ?? [];
        $data = array_replace_recursive($defaults, $attributes);
        $data['layout'] ??= 'default';

        foreach ($data as $key => &$value) {
            $value = apply_filters(
                "sage/blocks/{$key}",
                $value,
                $attributes,
                $block,
            );
            $value = apply_filters(
                "sage/blocks/{$slug}/{$key}",
                $value,
                $attributes,
                $block,
            );
        }

        return $data;
    }

    public static function render(
        string $slug,
        array $attributes = [],
        ?WP_Block $block = null,
    ): string {
        $data = self::prepare($slug, $attributes, $block);

        return view(
            self::resolveBlockView($slug, $data['layout']),
            array_merge(
                [
                    'attributes' => self::toViewVariable($attributes),
                    'block' => $block,
                ],
                self::viewVariables($data),
            ),
        )->render();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        static $defaults;

        if (isset($defaults)) {
            return $defaults;
        }

        $path = get_theme_file_path('resources/blocks/defaults.json');

        if (!file_exists($path)) {
            return $defaults = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $defaults = \is_array($decoded) ? $decoded : [];
    }

    protected static function resolveBlockView(
        string $slug,
        string $layout,
    ): string {
        $layoutPath = "resources/views/blocks/{$slug}-{$layout}.blade.php";

        if (
            $layout !== 'default'
            && file_exists(get_theme_file_path($layoutPath))
        ) {
            return "blocks.{$slug}-{$layout}";
        }

        return "blocks.{$slug}";
    }

    /**
     * @return array<string, mixed>
     */
    protected static function viewVariables(array $data): array
    {
        $variables = [];

        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $variables[$key] = self::toViewVariable($value);
        }

        return $variables;
    }

    protected static function toViewVariable(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            return new Fluent(
                array_map([self::class, 'toViewVariable'], $value),
            );
        }

        return array_map([self::class, 'toViewVariable'], $value);
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
                static fn ($line) => $line !== '',
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
            static fn (string $line, int $index): string => sprintf(
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
}
