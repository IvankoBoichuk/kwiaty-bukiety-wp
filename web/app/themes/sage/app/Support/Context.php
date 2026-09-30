<?php

namespace App\Support;

use App\Admin\ContactSettingsPage;
use Timber\Timber;

class Context
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $contactSettings = null;

    protected ?string $siteName = null;

    protected ?object $logos = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $primaryNavigation = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $contacts = null;

    /**
     * @var array<string, array<int, array<string, mixed>>>|null
     */
    protected ?array $footerMenus = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $socials = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $deliveryTimer = null;

    public function siteName(): string
    {
        return $this->siteName ??= get_bloginfo('name', 'display');
    }

    public function logos(): ?object
    {
        if ($this->logos !== null) {
            return $this->logos;
        }

        $logoDarkId = get_theme_mod('logo_dark');
        $logoLightId = get_theme_mod('logo_light');

        if (!$logoLightId && !$logoDarkId) {
            return $this->logos = null;
        }

        return $this->logos = (object) [
            'light' => self::logoImage((string) $logoLightId),
            'dark' => self::logoImage((string) $logoDarkId),
        ];
    }

    /**
     * The customizer stores a URL, so the id has to be looked up.
     *
     * attachment_url_to_postid() is an uncached raw query against wp_posts.guid
     * and this runs on every page through the header and footer composers, so
     * memoise the result. Skips the query entirely when the setting is empty,
     * which the old code did not -- it called through with '' whenever only one
     * of the two logos was set.
     */
    protected static function logoImage(string $url): ?\Timber\Image
    {
        static $resolved = [];

        if (trim($url) === '') {
            return null;
        }

        if (! \array_key_exists($url, $resolved)) {
            $cacheKey = 'sage_logo_id_' . md5($url);
            $attachmentId = get_transient($cacheKey);

            if ($attachmentId === false) {
                $attachmentId = attachment_url_to_postid($url);
                set_transient($cacheKey, $attachmentId, DAY_IN_SECONDS);
            }

            $resolved[$url] = $attachmentId > 0
                ? Timber::get_image((int) $attachmentId)
                : null;
        }

        return $resolved[$url];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function primaryNavigation(): array
    {
        if ($this->primaryNavigation !== null) {
            return $this->primaryNavigation;
        }

        $locations = get_nav_menu_locations();
        $menuId = $locations['primary_navigation'] ?? null;

        if (!$menuId) {
            return $this->primaryNavigation = [];
        }

        $menuItems = wp_get_nav_menu_items($menuId);

        if (!is_array($menuItems)) {
            return $this->primaryNavigation = [];
        }

        return $this->primaryNavigation = $this->buildMenuTree($menuItems);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function contacts(): array
    {
        if ($this->contacts !== null) {
            return $this->contacts;
        }

        $phones = array_map(function (array $item): array {
            return [
                'type' => 'phone',
                'value' => $item['number'],
                'details' => array_values(array_filter([$item['label'] ?? ''])),
            ];
        }, $this->phones());

        $emails = array_map(function (array $item): array {
            return [
                'type' => 'email',
                'value' => $item['email'],
                'details' => array_values(array_filter([$item['label'] ?? ''])),
            ];
        }, $this->emails());

        return $this->contacts = array_values(
            array_filter([
                ...$phones,
                ...$emails,
                [
                    'type' => 'text',
                    'value' => __('Opening hours', 'sage-front'),
                    'details' => $this->workingHoursDetails(),
                ],
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function deliveryTimer(): array
    {
        return $this->deliveryTimer ??= app(DeliveryTimer::class)->viewData();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function footerMenus(): array
    {
        if ($this->footerMenus !== null) {
            return $this->footerMenus;
        }

        return $this->footerMenus = [
            'main' => $this->menuItems('footer_navigation'),
            'secondary' => $this->menuItems('footer_secondary_navigation'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function socials(): array
    {
        if ($this->socials !== null) {
            return $this->socials;
        }

        $labels = ContactSettingsPage::socialNetworks();

        return $this->socials = array_map(function (array $item) use (
            $labels,
        ): array {
            return [
                'label'
                    => $labels[$item['network']] ?? ucfirst($item['network']),
                'url' => $item['url'],
                'target' => '_blank',
                'classes' => [$item['network']],
                'icon' => $item['network'],
            ];
        }, $this->socialItems());
    }

    /**
     * @return array{
     *     label: string,
     *     value: string,
     *     href: string
     * }|null
     */
    public function phone(): ?array
    {
        $phone = $this->phones()[0] ?? null;

        if (!$phone || empty($phone['number'])) {
            return null;
        }

        return [
            'label' => $phone['label'] ?? '',
            'value' => $phone['number'],
            'href' => 'tel:' . preg_replace('/\s+/', '', $phone['number']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function phones(): array
    {
        return $this->contactSettings()['phones'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function emails(): array
    {
        return $this->contactSettings()['emails'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function socialItems(): array
    {
        return $this->contactSettings()['socials'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    protected function workingHoursDetails(): array
    {
        return app(DeliveryTimer::class)->workingHoursDetails();
    }

    /**
     * @return array<string, mixed>
     */
    protected function contactSettings(): array
    {
        return $this->contactSettings ??= ContactSettingsPage::getOptions();
    }

    /**
     * @param array<int, \WP_Post> $items
     * @return array<int, array<string, mixed>>
     */
    protected function buildMenuTree(array $items, int $parentId = 0): array
    {
        $branch = [];

        foreach ($items as $item) {
            if ((int) $item->menu_item_parent !== $parentId) {
                continue;
            }

            $children = $this->buildMenuTree($items, (int) $item->ID);

            $branch[] = [
                'title' => $item->title,
                'url' => $item->url,
                'children' => $children,
            ];
        }

        return $branch;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function menuItems(string $location): array
    {
        $locations = get_nav_menu_locations();
        $menuId = $locations[$location] ?? null;

        if (!$menuId) {
            return [];
        }

        $items = wp_get_nav_menu_items($menuId) ?: [];

        return array_values(
            array_map(function ($item): array {
                return [
                    'label' => $item->title,
                    'url' => $item->url,
                    'target' => $item->target,
                    'classes' => array_values(
                        array_filter((array) $item->classes),
                    ),
                ];
            }, array_filter(
                $items,
                fn($item) => (int) $item->menu_item_parent === 0,
            )),
        );
    }
}
