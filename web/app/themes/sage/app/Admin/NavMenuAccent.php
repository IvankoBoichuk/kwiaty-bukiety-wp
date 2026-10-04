<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * The "accent item" checkbox on the Appearance > Menus screen.
 *
 * The design singles out one promoted category in the header -- a filled pill
 * instead of a plain link. That is editorial, not structural, so it cannot be
 * derived from the tree; it used to ride on a `promo` CSS class typed into the
 * menu item, which is invisible until someone knows to look for it. The flag
 * now has a checkbox of its own and lives in `_menu_item_accent` meta.
 */
final class NavMenuAccent
{
    public const META = '_menu_item_accent';

    /** The checkbox itself; only posted when it is ticked. */
    public const FIELD = 'menu-item-accent';

    /** Posted for every item on screen, so an unticked box can be told from an
        item that was never part of the submitted form. */
    public const MARKER = 'menu-item-accent-submitted';

    public static function boot(): void
    {
        add_action('wp_nav_menu_item_custom_fields', [self::class, 'render'], 10, 2);
        add_action('wp_update_nav_menu_item', [self::class, 'save'], 10, 2);
    }

    /**
     * @param  int|string  $itemId
     */
    public static function render($itemId, mixed $item = null): void
    {
        $itemId = (int) $itemId;

        if ($itemId <= 0) {
            return;
        }

        // Only top-level items get the pill; the children live inside the mega
        // menu, where there is nothing to accent.
        if ($item instanceof \WP_Post && (int) $item->menu_item_parent !== 0) {
            return;
        }

        printf(
            '<input type="hidden" name="%1$s[%2$d]" value="1" />'
            . '<p class="field-accent description description-wide">'
            . '<label for="%3$s-%2$d">'
            . '<input type="checkbox" id="%3$s-%2$d" name="%3$s[%2$d]" value="1" %4$s />&nbsp;%5$s'
            . '</label>'
            . '<span class="description">%6$s</span>'
            . '</p>',
            esc_attr(self::MARKER),
            $itemId,
            esc_attr(self::FIELD),
            checked(self::isAccent($itemId), true, false),
            esc_html__('Accent item', 'sage-back'),
            esc_html__('Shown in the header as a filled pill instead of a plain link.', 'sage-back'),
        );
    }

    /**
     * @param  int|string  $menuId
     * @param  int|string  $itemId
     */
    public static function save($menuId, $itemId): void
    {
        $itemId = (int) $itemId;

        if ($itemId <= 0) {
            return;
        }

        /*
         * wp_update_nav_menu_item() also runs for updates the field never took
         * part in -- the customizer, the REST menu endpoints, an import -- and
         * a missing checkbox there means "not posted", not "unticked". The
         * marker separates the two so those updates leave the flag alone.
         */
        $submitted = isset($_POST[self::MARKER]) && is_array($_POST[self::MARKER])
            ? $_POST[self::MARKER]
            : [];

        if (! isset($submitted[$itemId])) {
            return;
        }

        // nav-menus.php checks the update-nav_menu nonce before it gets here;
        // the capability is the part worth repeating.
        if (! current_user_can('edit_theme_options')) {
            return;
        }

        $checked = isset($_POST[self::FIELD])
            && is_array($_POST[self::FIELD])
            && ! empty($_POST[self::FIELD][$itemId]);

        if ($checked) {
            update_post_meta($itemId, self::META, '1');

            return;
        }

        delete_post_meta($itemId, self::META);
    }

    public static function isAccent(int $itemId): bool
    {
        return get_post_meta($itemId, self::META, true) === '1';
    }
}
