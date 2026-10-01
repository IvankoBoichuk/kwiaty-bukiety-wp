<?php

declare(strict_types=1);

namespace App\Admin;

use App\Catalog\Faq;

/**
 * Editor for the per-category FAQ on the product category screen.
 *
 * The production site edited these through an ACF WYSIWYG field; ACF is not
 * part of this stack, so the questions get a small repeater of their own that
 * writes the structured `kb_faq` term meta App\Catalog\Faq reads.
 */
final class CategoryFaqMetabox
{
    public const TAXONOMY = 'product_cat';

    public const NONCE_ACTION = 'sage_category_faq';

    public const NONCE_NAME = 'sage_category_faq_nonce';

    public const FIELD = 'sage_category_faq';

    public static function boot(): void
    {
        add_action(self::TAXONOMY . '_edit_form_fields', [self::class, 'render'], 20);
        add_action('edited_' . self::TAXONOMY, [self::class, 'save']);
        add_action('created_' . self::TAXONOMY, [self::class, 'save']);
    }

    public static function render(mixed $term): void
    {
        if (! $term instanceof \WP_Term) {
            return;
        }

        $items = Faq::forTerm($term->term_id);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<tr class="form-field sage-faq-field"><th scope="row"><label>'
            . esc_html__('FAQ', 'sage-back')
            . '</label></th><td>';

        echo '<div class="sage-faq" data-sage-faq><div data-sage-faq-items>';

        foreach ($items as $index => $item) {
            echo self::row($index, $item['q'], $item['a']);
        }

        echo '</div>';

        printf(
            '<template data-sage-faq-template>%s</template>',
            self::row('__index__', '', ''),
        );

        printf(
            '<p><button type="button" class="button" data-sage-faq-add>%s</button></p>',
            esc_html__('Add question', 'sage-back'),
        );

        printf(
            '<p class="description">%s</p>',
            esc_html__('Shown on the first page of the category and published as FAQPage structured data.', 'sage-back'),
        );

        echo '</div></td></tr>';

        self::styles();
        self::scripts();
    }

    /**
     * @param  int|string  $index
     */
    protected static function row($index, string $question, string $answer): string
    {
        return sprintf(
            '<div class="sage-faq__item" data-sage-faq-item>'
            . '<input type="text" class="widefat" name="%1$s[%2$s][q]" value="%3$s" placeholder="%4$s" />'
            . '<textarea class="widefat" rows="4" name="%1$s[%2$s][a]" placeholder="%5$s">%6$s</textarea>'
            . '<button type="button" class="button-link sage-faq__remove" data-sage-faq-remove>%7$s</button>'
            . '</div>',
            esc_attr(self::FIELD),
            esc_attr((string) $index),
            esc_attr($question),
            esc_attr__('Question', 'sage-back'),
            esc_attr__('Answer', 'sage-back'),
            esc_textarea($answer),
            esc_html__('Remove', 'sage-back'),
        );
    }

    public static function save(mixed $termId): void
    {
        $termId = (int) $termId;

        if ($termId <= 0) {
            return;
        }

        // The screen posts the whole term form; without the nonce this would
        // also fire for quick-edit and REST updates that carry no FAQ at all
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

        $items = [];

        foreach ($submitted as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'q' => (string) ($item['q'] ?? ''),
                'a' => (string) ($item['a'] ?? ''),
            ];
        }

        Faq::save($termId, $items);
    }

    protected static function styles(): void
    {
        echo '<style>
            .sage-faq__item { border: 1px solid #dcdcde; border-radius: 4px; padding: 10px; margin-bottom: 10px; background: #fff; }
            .sage-faq__item input { margin-bottom: 6px; }
            .sage-faq__remove { color: #b32d2e; }
        </style>';
    }

    protected static function scripts(): void
    {
        echo '<script>
            (function () {
                var root = document.querySelector("[data-sage-faq]");
                if (!root) { return; }

                var items = root.querySelector("[data-sage-faq-items]");
                var template = root.querySelector("[data-sage-faq-template]");

                root.addEventListener("click", function (event) {
                    if (event.target.matches("[data-sage-faq-add]")) {
                        var html = template.innerHTML.replace(/__index__/g, String(Date.now()));
                        items.insertAdjacentHTML("beforeend", html);
                        return;
                    }

                    if (event.target.matches("[data-sage-faq-remove]")) {
                        var item = event.target.closest("[data-sage-faq-item]");
                        if (item) { item.remove(); }
                    }
                });
            })();
        </script>';
    }
}
