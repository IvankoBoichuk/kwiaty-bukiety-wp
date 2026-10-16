<?php

declare(strict_types=1);

namespace App\SEO;

/**
 * The shop as a schema.org Florist, with the team as its employees, on the
 * "O nas" page.
 *
 * Production published this graph there; it did not come over with the
 * database, and Yoast's own graph describes the site, not the business, so
 * the address, opening hours and contact details were gone from the page.
 * The values are the ones production published. Every URL is built from this
 * install, so the graph points at whichever host serves it.
 */
final class FloristSchema
{
    public const PAGE_SLUG = 'o-nas';

    /**
     * Author login => what production published about them.
     *
     * @var array<string, array{name: string, jobTitle: string, image: string}>
     */
    private const TEAM = [
        'katrinamsl' => [
            'name' => 'Katrina',
            'jobTitle' => 'Ekspertka od pielęgnacji kwiatów',
            'image' => '2024/11/author-katrina.webp',
        ],
        'igor' => [
            'name' => 'Igor',
            'jobTitle' => 'Specjalista ds. logistyki dostaw',
            'image' => '2024/11/author-igor.webp',
        ],
        'inna' => [
            'name' => 'Inna',
            'jobTitle' => 'Florystka',
            'image' => '2024/11/author-inna.webp',
        ],
    ];

    public static function boot(): void
    {
        add_action('wp_head', [self::class, 'render'], 20);
    }

    public static function render(): void
    {
        if (! is_page(self::PAGE_SLUG)) {
            return;
        }

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode(
                ['@context' => 'https://schema.org', '@graph' => self::graph()],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function graph(): array
    {
        $home = home_url('/');
        $floristId = $home . '#florist';
        $uploads = rtrim((string) wp_get_upload_dir()['baseurl'], '/') . '/';

        $people = [];

        foreach (self::TEAM as $login => $person) {
            $user = get_user_by('login', $login);

            // A missing author would leave the Florist pointing at a URL that 404s.
            if (! $user instanceof \WP_User) {
                continue;
            }

            $url = get_author_posts_url($user->ID);

            $people[] = [
                '@type' => 'Person',
                '@id' => $url . '#person',
                'name' => $person['name'],
                'jobTitle' => $person['jobTitle'],
                'url' => $url,
                'image' => $uploads . $person['image'],
                'worksFor' => ['@id' => $floristId],
                'knowsLanguage' => ['pl'],
            ];
        }

        $florist = [
            '@type' => 'Florist',
            '@id' => $floristId,
            'name' => 'Kwiaty-Bukiety.com.pl',
            'url' => $home,
            'description' => 'W Kwiaty-Bukiety.com.pl łączymy pasję z miłością do kwiatów. Zamawiaj świeże bukiety z szybką dostawą na terenie całej Polski.',
            'priceRange' => '$$',
            'logo' => ['@type' => 'ImageObject', 'url' => $uploads . '2024/11/logo.svg'],
            'image' => ['@type' => 'ImageObject', 'url' => $uploads . '2024/11/o-nas-baner.webp'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'ul. Podwiejska 39',
                'addressLocality' => 'Milanówek',
                'postalCode' => '05-822',
                'addressCountry' => 'PL',
            ],
            'email' => 'kwiatybukietycompl@gmail.com',
            'telephone' => '+48 509 879 036',
            'openingHours' => ['Mo-Fr 08:00-19:00', 'Sa 09:00-15:00', 'Su 09:00-15:00'],
            'sameAs' => [
                'https://www.facebook.com/profile.php?id=100086430231839',
                'https://www.instagram.com/kwiaty_bukiety_com.pl/',
            ],
        ];

        if ($people !== []) {
            $florist['employee'] = array_map(
                static fn(array $person): array => ['@id' => $person['@id']],
                $people,
            );
        }

        return [$florist, ...$people];
    }
}
