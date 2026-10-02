<?php

declare(strict_types=1);

namespace App\Modules\LocalLinking;

/**
 * Editing for `popular_order`, the term meta that decides which cities lead the
 * "Popularne w [województwo]" block (production plugin, inc/admin-popular.php).
 *
 * The plugin also shipped a separate bulk screen listing every city of a
 * voivodeship. That screen queried the `bi_*` tables directly and is not
 * migrated: the column and a field on the category itself cover the same
 * editing without a second place to look, and they keep working before the
 * tables are imported.
 *
 * Cities left blank fall back to the deterministic CRC32 ordering in
 * LocalPageRepository::popularInWojewodztwo().
 */
final class PopularOrderAdmin
{
    public const META_KEY = 'popular_order';

    public const TAXONOMY = 'product_cat';

    public const NONCE_ACTION = 'sage_popular_order';

    public const NONCE_NAME = 'sage_popular_order_nonce';

    public static function boot(): void
    {
        add_filter('manage_edit-' . self::TAXONOMY . '_columns', [self::class, 'addColumn']);
        add_filter('manage_' . self::TAXONOMY . '_custom_column', [self::class, 'renderColumn'], 10, 3);
        add_filter('manage_edit-' . self::TAXONOMY . '_sortable_columns', [self::class, 'sortableColumn']);

        add_action(self::TAXONOMY . '_edit_form_fields', [self::class, 'renderField'], 20);
        add_action('edited_' . self::TAXONOMY, [self::class, 'save']);
        add_action('created_' . self::TAXONOMY, [self::class, 'save']);
    }

    /**
     * @param  mixed  $columns
     * @return mixed
     */
    public static function addColumn($columns)
    {
        if (! is_array($columns)) {
            return $columns;
        }

        $columns[self::META_KEY] = __('Popular', 'sage-back');

        return $columns;
    }

    /**
     * @param  mixed  $columns
     * @return mixed
     */
    public static function sortableColumn($columns)
    {
        if (! is_array($columns)) {
            return $columns;
        }

        $columns[self::META_KEY] = self::META_KEY;

        return $columns;
    }

    public static function renderColumn(mixed $output, mixed $column, mixed $termId): mixed
    {
        if ($column !== self::META_KEY) {
            return $output;
        }

        $value = get_term_meta((int) $termId, self::META_KEY, true);

        return trim((string) $value) === ''
            ? '<span aria-hidden="true">&mdash;</span>'
            : esc_html((string) $value);
    }

    public static function renderField(mixed $term): void
    {
        if (! $term instanceof \WP_Term) {
            return;
        }

        $value = get_term_meta($term->term_id, self::META_KEY, true);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        printf(
            '<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th>'
            . '<td><input type="number" min="1" step="1" id="%1$s" name="%1$s" value="%3$s" />'
            . '<p class="description">%4$s</p></td></tr>',
            esc_attr(self::META_KEY),
            esc_html__('Popular order', 'sage-back'),
            esc_attr((string) $value),
            esc_html__('Lower numbers come first in the "popular cities" block. Leave empty to order automatically.', 'sage-back'),
        );
    }

    public static function save(mixed $termId): void
    {
        $termId = (int) $termId;

        if ($termId <= 0) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_NAME])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]))
            : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('manage_product_terms')) {
            return;
        }

        $value = isset($_POST[self::META_KEY])
            ? trim(sanitize_text_field(wp_unslash($_POST[self::META_KEY])))
            : '';

        if ($value === '' || (int) $value <= 0) {
            delete_term_meta($termId, self::META_KEY);

            return;
        }

        update_term_meta($termId, self::META_KEY, (int) $value);
    }
}
