<?php

declare(strict_types=1);

namespace App\Admin;

use App\Catalog\CategoryTiles;
use WP_Term;

/**
 * Editor for the per-category tile grid on the product category screen.
 *
 * Production edits these through the ACF repeater `kategoryi_repeater`; ACF is
 * not part of this stack, so the rows get a repeater of their own that writes
 * the structured `kb_category_tiles` term meta App\Catalog\CategoryTiles reads.
 * Shaped after CategoryFaqMetabox, with a category picker and a media picker.
 *
 * Only the category is required. The picture and the caption are overrides: left
 * empty they fall back to the chosen category's thumbnail and name, so the usual
 * row is one dropdown and nothing else.
 */
final class CategoryTilesMetabox
{
    public const TAXONOMY = 'product_cat';

    public const NONCE_ACTION = 'sage_category_tiles';

    public const NONCE_NAME = 'sage_category_tiles_nonce';

    public const FIELD = 'sage_category_tiles';

    public static function boot(): void
    {
        add_action(self::TAXONOMY . '_edit_form_fields', [self::class, 'render'], 20);
        add_action('edited_' . self::TAXONOMY, [self::class, 'save']);
        add_action('created_' . self::TAXONOMY, [self::class, 'save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * The picker runs on wp.media, which is not on the term screen by default.
     */
    public static function enqueue(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($screen === null || $screen->taxonomy !== self::TAXONOMY) {
            return;
        }

        wp_enqueue_media();
    }

    public static function render(mixed $term): void
    {
        if (! $term instanceof WP_Term) {
            return;
        }

        $tiles = CategoryTiles::forTerm($term->term_id);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<tr class="form-field sage-tiles-field"><th scope="row"><label>'
            . esc_html__('Category tiles', 'sage-back')
            . '</label></th><td>';

        echo '<div class="sage-tiles" data-sage-tiles><div data-sage-tiles-items>';

        foreach ($tiles as $index => $tile) {
            echo self::row($index, $tile['category'], $tile['image'], $tile['name']);
        }

        echo '</div>';

        printf(
            '<template data-sage-tiles-template>%s</template>',
            self::row('__index__', 0, 0, ''),
        );

        printf(
            '<p><button type="button" class="button" data-sage-tiles-add>%s</button></p>',
            esc_html__('Add tile', 'sage-back'),
        );

        printf(
            '<p class="description">%s</p>',
            esc_html__('Printed above the products on the first page of this category. Leave the picture and the caption empty to use the chosen category\'s own thumbnail and name.', 'sage-back'),
        );

        echo '</div></td></tr>';

        self::styles();
        self::scripts();
    }

    /**
     * @param  int|string  $index
     */
    protected static function row($index, int $categoryId, int $imageId, string $name): string
    {
        $preview = $imageId > 0 ? wp_get_attachment_image_url($imageId, 'thumbnail') : '';

        return sprintf(
            '<div class="sage-tiles__item" data-sage-tiles-item>'
            . '<div class="sage-tiles__media">'
            . '<img class="sage-tiles__preview" data-sage-tiles-preview src="%7$s" alt="" %8$s />'
            . '<input type="hidden" name="%1$s[%2$s][image]" value="%3$s" data-sage-tiles-image />'
            . '<button type="button" class="button" data-sage-tiles-select>%9$s</button>'
            . '<button type="button" class="button-link sage-tiles__clear" data-sage-tiles-clear>%10$s</button>'
            . '</div>'
            . '<div class="sage-tiles__fields">'
            . '<select class="widefat" name="%1$s[%2$s][category]">%4$s</select>'
            . '<input type="text" class="widefat" name="%1$s[%2$s][name]" value="%5$s" placeholder="%6$s" />'
            . '</div>'
            . '<button type="button" class="button-link sage-tiles__remove" data-sage-tiles-remove>%11$s</button>'
            . '</div>',
            esc_attr(self::FIELD),
            esc_attr((string) $index),
            esc_attr((string) $imageId),
            self::options($categoryId),
            esc_attr($name),
            esc_attr__('Caption — defaults to the category name', 'sage-back'),
            esc_url($preview),
            $preview === '' ? 'hidden' : '',
            esc_html__('Select picture', 'sage-back'),
            esc_html__('Clear', 'sage-back'),
            esc_html__('Remove tile', 'sage-back'),
        );
    }

    /**
     * The options of the category dropdown, nested so a child reads as one.
     */
    protected static function options(int $selected): string
    {
        $html = sprintf(
            '<option value="0">%s</option>',
            esc_html__('— Select a category —', 'sage-back'),
        );

        foreach (self::terms() as $termId => $label) {
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                $termId,
                selected($selected, $termId, false),
                esc_html($label),
            );
        }

        return $html;
    }

    /**
     * Term id => label, parents before their children, each level indented.
     *
     * Built once per request: the dropdown is repeated for every row and for the
     * template, and the taxonomy here runs to a few hundred terms.
     *
     * @return array<int, string>
     */
    protected static function terms(): array
    {
        static $labels = null;

        if (is_array($labels)) {
            return $labels;
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'orderby' => 'name',
        ]);

        if (! is_array($terms)) {
            return $labels = [];
        }

        $children = [];

        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $children[(int) $term->parent][] = $term;
            }
        }

        $labels = [];

        $walk = static function (int $parent, int $depth) use (&$walk, &$labels, $children): void {
            foreach ($children[$parent] ?? [] as $term) {
                $labels[(int) $term->term_id] = str_repeat("\u{a0}\u{a0}\u{a0}", $depth) . $term->name;
                $walk((int) $term->term_id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $labels;
    }

    public static function save(mixed $termId): void
    {
        $termId = (int) $termId;

        if ($termId <= 0) {
            return;
        }

        // The screen posts the whole term form; without the nonce this would
        // also fire for quick-edit and REST updates that carry no tiles at all
        // and would wipe the meta.
        $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('manage_product_terms')) {
            return;
        }

        $submitted = isset($_POST[self::FIELD]) && is_array($_POST[self::FIELD])
            ? wp_unslash($_POST[self::FIELD])
            : [];

        $tiles = [];

        foreach ($submitted as $tile) {
            if (! is_array($tile)) {
                continue;
            }

            $tiles[] = [
                'category' => (int) ($tile['category'] ?? 0),
                'image' => (int) ($tile['image'] ?? 0),
                'name' => (string) ($tile['name'] ?? ''),
            ];
        }

        CategoryTiles::save($termId, $tiles);
    }

    protected static function styles(): void
    {
        echo '<style>
            .sage-tiles__item { display: flex; align-items: flex-start; gap: 12px; border: 1px solid #dcdcde; border-radius: 4px; padding: 10px; margin-bottom: 10px; background: #fff; }
            .sage-tiles__media { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; width: 110px; flex: none; }
            .sage-tiles__preview { width: 100px; height: 100px; object-fit: cover; border: 1px solid #dcdcde; border-radius: 4px; display: block; }
            .sage-tiles__preview[hidden] { display: none; }
            .sage-tiles__fields { flex: 1; display: flex; flex-direction: column; gap: 6px; }
            .sage-tiles__clear, .sage-tiles__remove { color: #b32d2e; }
        </style>';
    }

    protected static function scripts(): void
    {
        echo '<script>
            (function () {
                var root = document.querySelector("[data-sage-tiles]");
                if (!root) { return; }

                var items = root.querySelector("[data-sage-tiles-items]");
                var template = root.querySelector("[data-sage-tiles-template]");
                var frame = null;

                function setImage(item, id, url) {
                    var input = item.querySelector("[data-sage-tiles-image]");
                    var preview = item.querySelector("[data-sage-tiles-preview]");
                    input.value = id ? String(id) : "";
                    if (url) { preview.src = url; preview.removeAttribute("hidden"); }
                    else { preview.removeAttribute("src"); preview.setAttribute("hidden", "hidden"); }
                }

                root.addEventListener("click", function (event) {
                    var target = event.target;

                    if (target.matches("[data-sage-tiles-add]")) {
                        items.insertAdjacentHTML("beforeend", template.innerHTML.replace(/__index__/g, String(Date.now())));
                        return;
                    }

                    var item = target.closest("[data-sage-tiles-item]");
                    if (!item) { return; }

                    if (target.matches("[data-sage-tiles-remove]")) { item.remove(); return; }
                    if (target.matches("[data-sage-tiles-clear]")) { setImage(item, 0, ""); return; }

                    if (target.matches("[data-sage-tiles-select]")) {
                        event.preventDefault();
                        if (!window.wp || !window.wp.media) { return; }

                        frame = window.wp.media({ title: target.textContent, library: { type: "image" }, multiple: false });
                        frame.on("select", function () {
                            var attachment = frame.state().get("selection").first().toJSON();
                            var sizes = attachment.sizes || {};
                            var thumb = sizes.thumbnail || sizes.medium || null;
                            setImage(item, attachment.id, thumb ? thumb.url : attachment.url);
                        });
                        frame.open();
                    }
                });
            })();
        </script>';
    }
}
