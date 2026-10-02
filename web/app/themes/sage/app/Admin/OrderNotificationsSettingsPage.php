<?php

declare(strict_types=1);

namespace App\Admin;

use App\Shop\OrderNotifications;

/**
 * Appearance → Order Notifications: where a paid order is announced.
 *
 * Both destinations were hardcoded in the production snippets (#71, #79). They
 * live in a site option so the shop can change the partner address or re-point
 * the Make.com scenario without a deploy; leaving a field empty switches that
 * channel off.
 */
class OrderNotificationsSettingsPage
{
    protected const PAGE_SLUG = 'sage-order-notifications-settings';

    protected const SETTINGS_GROUP = 'sage_order_notifications_settings';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'registerPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function registerPage(): void
    {
        add_theme_page(
            __('Order Notifications', 'sage-back'),
            __('Order Notifications', 'sage-back'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage'],
        );
    }

    public static function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            OrderNotifications::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitizeOptions'],
                'default' => OrderNotifications::defaultOptions(),
                'show_in_rest' => false,
            ],
        );
    }

    /**
     * Anything that is not a usable address or URL is stored as an empty
     * string, which turns that channel off rather than failing silently on
     * every paid order.
     *
     * @return array<string, string>
     */
    public static function sanitizeOptions(mixed $input): array
    {
        $input = is_array($input) ? $input : [];

        $email = sanitize_email((string) ($input['partner_email'] ?? ''));
        $url = esc_url_raw(trim((string) ($input['webhook_url'] ?? '')), ['http', 'https']);
        $sheetsUrl = esc_url_raw(trim((string) ($input['sheets_url'] ?? '')), ['http', 'https']);

        if ($email !== '' && ! is_email($email)) {
            add_settings_error(
                OrderNotifications::OPTION_NAME,
                'partner_email',
                __('The partner e-mail address is not valid; the e-mail channel stays off.', 'sage-back'),
            );

            $email = '';
        }

        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            add_settings_error(
                OrderNotifications::OPTION_NAME,
                'webhook_url',
                __('The webhook URL is not valid; the webhook stays off.', 'sage-back'),
            );

            $url = '';
        }

        if ($sheetsUrl !== '' && ! filter_var($sheetsUrl, FILTER_VALIDATE_URL)) {
            add_settings_error(
                OrderNotifications::OPTION_NAME,
                'sheets_url',
                __('The Google Sheets URL is not valid; the sync stays off.', 'sage-back'),
            );

            $sheetsUrl = '';
        }

        return [
            'partner_email' => $email,
            'webhook_url' => $url,
            'sheets_url' => $sheetsUrl,
        ];
    }

    public static function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = OrderNotifications::settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Order Notifications', 'sage-back'); ?></h1>

            <p class="description">
                <?php echo esc_html__('Sent once when an order moves to "processing". Leave a field empty to switch that channel off.', 'sage-back'); ?>
            </p>

            <form action="options.php" method="post">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="sage-partner-email">
                                <?php echo esc_html__('Partner e-mail', 'sage-back'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="sage-partner-email"
                                class="regular-text"
                                name="<?php echo esc_attr(OrderNotifications::OPTION_NAME); ?>[partner_email]"
                                value="<?php echo esc_attr($options['partner_email']); ?>"
                            />
                            <p class="description">
                                <?php echo esc_html__('Receives the fulfilment e-mail with the order details and the partner price.', 'sage-back'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sage-webhook-url">
                                <?php echo esc_html__('Make.com webhook URL', 'sage-back'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="sage-webhook-url"
                                class="large-text code"
                                name="<?php echo esc_attr(OrderNotifications::OPTION_NAME); ?>[webhook_url]"
                                value="<?php echo esc_attr($options['webhook_url']); ?>"
                                placeholder="https://hook.eu1.make.com/…"
                            />
                            <p class="description">
                                <?php echo esc_html__('The order is posted here as JSON.', 'sage-back'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sage-sheets-url">
                                <?php echo esc_html__('Google Sheets web app URL', 'sage-back'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="sage-sheets-url"
                                class="large-text code"
                                name="<?php echo esc_attr(OrderNotifications::OPTION_NAME); ?>[sheets_url]"
                                value="<?php echo esc_attr($options['sheets_url']); ?>"
                                placeholder="https://script.google.com/macros/s/…/exec"
                            />
                            <p class="description">
                                <?php echo esc_html__('Orders entering processing, completed, cancelled or refunded are appended to the sheet. Leave empty on a copy of the site: that is what keeps it out of the live spreadsheet.', 'sage-back'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
