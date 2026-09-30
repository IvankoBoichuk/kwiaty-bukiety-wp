<?php

namespace App\Admin;

use Timber\Timber;

class ProductAttributeIcons
{
    protected const OPTION_NAME = 'sage_product_attribute_icons';

    protected const FIELD_NAME = 'sage_product_attribute_icon_id';

    public static function boot(): void
    {
        add_action(
            'woocommerce_after_add_attribute_fields',
            [self::class, 'renderAddField'],
        );
        add_action(
            'woocommerce_after_edit_attribute_fields',
            [self::class, 'renderEditField'],
        );
        add_action(
            'woocommerce_attribute_added',
            [self::class, 'storeAddedAttributeIcon'],
            10,
            2,
        );
        add_action(
            'woocommerce_attribute_updated',
            [self::class, 'storeUpdatedAttributeIcon'],
            10,
            3,
        );
        add_action(
            'admin_enqueue_scripts',
            [self::class, 'enqueueAdminAssets'],
        );
        add_action('admin_head', [self::class, 'renderStyles']);
        add_action('admin_footer', [self::class, 'renderScripts']);
    }

    public static function enqueueAdminAssets(): void
    {
        if (!self::isProductAttributesScreen()) {
            return;
        }

        wp_enqueue_media();
    }

    public static function renderAddField(): void
    {
        self::renderFieldMarkup();
    }

    public static function renderEditField(): void
    {
        $taxonomy = self::currentEditedTaxonomy();
        $attachmentId = self::getIconAttachmentIdByTaxonomy($taxonomy);

        self::renderFieldMarkup($attachmentId, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function storeAddedAttributeIcon(int $id, array $data): void
    {
        unset($id);

        $taxonomy = self::taxonomyFromPostedData($data);

        if ($taxonomy === '') {
            return;
        }

        self::persistAttachmentId($taxonomy, self::postedAttachmentId());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function storeUpdatedAttributeIcon(
        int $id,
        array $data,
        string $oldSlug,
    ): void {
        unset($id);

        $taxonomy = self::taxonomyFromPostedData($data);
        $oldTaxonomy = self::normalizeTaxonomyName($oldSlug);

        if ($taxonomy === '') {
            return;
        }

        if ($oldTaxonomy !== '' && $oldTaxonomy !== $taxonomy) {
            $icons = self::allIcons();

            if (isset($icons[$oldTaxonomy]) && !isset($icons[$taxonomy])) {
                $icons[$taxonomy] = absint($icons[$oldTaxonomy]);
            }

            unset($icons[$oldTaxonomy]);
            update_option(self::OPTION_NAME, $icons, false);
        }

        self::persistAttachmentId($taxonomy, self::postedAttachmentId());
    }

    public static function getIconAttachmentIdByTaxonomy(string $taxonomy): int
    {
        $taxonomy = self::normalizeTaxonomyName($taxonomy);

        if ($taxonomy === '') {
            return 0;
        }

        return absint(self::allIcons()[$taxonomy] ?? 0);
    }

    /**
     * @return array{src: string, alt: string}|null
     */
    public static function getIconDataByTaxonomy(
        string $taxonomy,
        string $fallbackAlt = '',
    ): ?array {
        $attachmentId = self::getIconAttachmentIdByTaxonomy($taxonomy);

        if ($attachmentId <= 0) {
            return null;
        }

        $image = Timber::get_image($attachmentId);

        if ($image === null) {
            return null;
        }

        return [
            'src' => $image->src('thumbnail'),
            'alt' => $image->alt() ?: $fallbackAlt,
        ];
    }

    public static function renderStyles(): void
    {
        if (!self::isProductAttributesScreen()) {
            return;
        }

        ?>
        <style>
            .sage-attribute-icon-field__preview {
                margin: 0 0 12px;
            }

            .sage-attribute-icon-field__preview img {
                display: block;
                width: 48px;
                height: 48px;
                object-fit: contain;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                padding: 6px;
            }

            .sage-attribute-icon-field__preview.is-empty {
                display: none;
            }

            .sage-attribute-icon-field__actions {
                display: flex;
                gap: 8px;
                align-items: center;
                flex-wrap: wrap;
            }

            .sage-attribute-icon-field__remove.is-hidden {
                display: none;
            }
        </style>
        <?php
    }

    public static function renderScripts(): void
    {
        if (!self::isProductAttributesScreen()) {
            return;
        }

        ?>
        <script>
          document.querySelectorAll('[data-sage-attribute-icon-field]').forEach((field) => {
            const input = field.querySelector('[data-sage-attribute-icon-input]')
            const preview = field.querySelector('[data-sage-attribute-icon-preview]')
            const image = preview ? preview.querySelector('img') : null
            const uploadButton = field.querySelector('[data-sage-attribute-icon-upload]')
            const removeButton = field.querySelector('[data-sage-attribute-icon-remove]')

            if (!input || !preview || !image || !uploadButton || !removeButton) {
              return
            }

            const syncState = () => {
              const hasValue = input.value.trim() !== ''
              preview.classList.toggle('is-empty', !hasValue)
              removeButton.classList.toggle('is-hidden', !hasValue)
            }

            uploadButton.addEventListener('click', (event) => {
              event.preventDefault()

              const frame = wp.media({
                title: 'Select attribute icon',
                button: { text: 'Use icon' },
                library: { type: 'image' },
                multiple: false,
              })

              frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON()

                input.value = String(attachment.id ?? '')
                image.src = attachment.sizes?.thumbnail?.url || attachment.url || ''
                image.alt = attachment.alt || attachment.filename || ''
                syncState()
              })

              frame.open()
            })

            removeButton.addEventListener('click', (event) => {
              event.preventDefault()
              input.value = ''
              image.src = ''
              image.alt = ''
              syncState()
            })

            syncState()
          })
        </script>
        <?php
    }

    protected static function renderFieldMarkup(
        int $attachmentId = 0,
        bool $isEdit = false,
    ): void {
        $imageUrl = $attachmentId > 0
            ? (string) wp_get_attachment_image_url($attachmentId, 'thumbnail')
            : '';
        $imageAlt = $attachmentId > 0
            ? (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true)
            : '';

        if ($isEdit) {
            ?>
            <tr class="form-field">
                <th scope="row">
                    <label for="<?php echo esc_attr(self::FIELD_NAME); ?>">
                        <?php echo esc_html__('Icon', 'sage-back'); ?>
                    </label>
                </th>
                <td>
                    <?php self::renderFieldControls($attachmentId, $imageUrl, $imageAlt); ?>
                </td>
            </tr>
            <?php
            return;
        }

        ?>
        <div class="form-field">
            <label for="<?php echo esc_attr(self::FIELD_NAME); ?>">
                <?php echo esc_html__('Icon', 'sage-back'); ?>
            </label>
            <?php self::renderFieldControls($attachmentId, $imageUrl, $imageAlt); ?>
        </div>
        <?php
    }

    protected static function renderFieldControls(
        int $attachmentId,
        string $imageUrl,
        string $imageAlt,
    ): void {
        ?>
        <div data-sage-attribute-icon-field>
            <input
                id="<?php echo esc_attr(self::FIELD_NAME); ?>"
                name="<?php echo esc_attr(self::FIELD_NAME); ?>"
                type="hidden"
                value="<?php echo esc_attr($attachmentId); ?>"
                data-sage-attribute-icon-input
            />

            <div
                class="sage-attribute-icon-field__preview<?php echo $imageUrl === '' ? ' is-empty' : ''; ?>"
                data-sage-attribute-icon-preview
            >
                <img src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr($imageAlt); ?>" />
            </div>

            <div class="sage-attribute-icon-field__actions">
                <button type="button" class="button" data-sage-attribute-icon-upload>
                    <?php echo esc_html__('Upload icon', 'sage-back'); ?>
                </button>
                <button
                    type="button"
                    class="button-link-delete sage-attribute-icon-field__remove<?php echo $imageUrl === '' ? ' is-hidden' : ''; ?>"
                    data-sage-attribute-icon-remove
                >
                    <?php echo esc_html__('Remove icon', 'sage-back'); ?>
                </button>
            </div>

            <p class="description">
                <?php echo esc_html__('Upload an icon for this product attribute group.', 'sage-back'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * @return array<string, int>
     */
    protected static function allIcons(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        if (!is_array($stored)) {
            return [];
        }

        $icons = [];

        foreach ($stored as $taxonomy => $attachmentId) {
            $normalizedTaxonomy = self::normalizeTaxonomyName((string) $taxonomy);

            if ($normalizedTaxonomy === '') {
                continue;
            }

            $icons[$normalizedTaxonomy] = absint($attachmentId);
        }

        return $icons;
    }

    protected static function persistAttachmentId(
        string $taxonomy,
        int $attachmentId,
    ): void {
        $taxonomy = self::normalizeTaxonomyName($taxonomy);

        if ($taxonomy === '') {
            return;
        }

        $icons = self::allIcons();

        if ($attachmentId > 0) {
            $icons[$taxonomy] = $attachmentId;
        } else {
            unset($icons[$taxonomy]);
        }

        update_option(self::OPTION_NAME, $icons, false);
    }

    protected static function postedAttachmentId(): int
    {
        // Reached only through woocommerce_attribute_added/updated, which
        // WooCommerce already gates on manage_product_terms and its own
        // nonce. Check the capability anyway rather than let the hook
        // contract be the only thing standing here.
        if (! current_user_can('manage_product_terms')) {
            return 0;
        }

        return absint($_POST[self::FIELD_NAME] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function taxonomyFromPostedData(array $data): string
    {
        $name = sanitize_title((string) ($data['attribute_name'] ?? ''));

        return self::normalizeTaxonomyName($name);
    }

    protected static function currentEditedTaxonomy(): string
    {
        $editId = absint($_GET['edit'] ?? 0);

        if ($editId > 0 && function_exists('wc_get_attribute')) {
            $attribute = wc_get_attribute($editId);

            if (is_object($attribute) && isset($attribute->slug)) {
                return self::normalizeTaxonomyName((string) $attribute->slug);
            }
        }

        return self::normalizeTaxonomyName((string) ($_GET['edit'] ?? ''));
    }

    protected static function normalizeTaxonomyName(string $taxonomy): string
    {
        $taxonomy = sanitize_title($taxonomy);

        if ($taxonomy === '') {
            return '';
        }

        if (str_starts_with($taxonomy, 'pa_')) {
            return $taxonomy;
        }

        if (function_exists('wc_attribute_taxonomy_name')) {
            return (string) wc_attribute_taxonomy_name($taxonomy);
        }

        return 'pa_' . $taxonomy;
    }

    protected static function isProductAttributesScreen(): bool
    {
        return is_admin()
            && ($_GET['post_type'] ?? '') === 'product'
            && ($_GET['page'] ?? '') === 'product_attributes';
    }
}
