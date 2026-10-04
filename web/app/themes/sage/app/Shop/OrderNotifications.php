<?php

declare(strict_types=1);

namespace App\Shop;

use WC_Order;

/**
 * Outbound notifications fired when an order reaches `processing`:
 * the fulfilment e-mail (production snippet #71) and the Make.com webhook (#79).
 *
 * Both destinations were hardcoded in the snippets -- a Gmail address and a
 * live hook URL sitting in the database. They are site settings here
 * (Appearance -> Order Notifications), so the shop can change them without a
 * deploy, and each channel stays switched off while its field is empty, which
 * is what keeps a copy of the database from mailing a partner or posting into
 * the production automation.
 */
final class OrderNotifications
{
    public const OPTION_NAME = 'sage_order_notifications';

    public const EMAIL_SENT_META = '_kb_partner_email_sent';

    public const WEBHOOK_SENT_META = '_kb_make_webhook_sent';

    public const WEBHOOK_TIMEOUT = 15;

    public static function boot(): void
    {
        add_action('woocommerce_order_status_processing', [self::class, 'handle'], 20);
    }

    public static function handle(mixed $orderId): void
    {
        $order = wc_get_order((int) $orderId);

        if (! $order instanceof WC_Order) {
            return;
        }

        self::sendPartnerEmail($order);
        self::sendWebhook($order);
    }

    public static function sendPartnerEmail(WC_Order $order): bool
    {
        $recipient = self::recipient();

        if ($recipient === '' || $order->get_meta(self::EMAIL_SENT_META, true)) {
            return false;
        }

        $partnerOrder = new PartnerOrder($order);

        $sent = wp_mail(
            $recipient,
            sprintf(
                /* translators: %s: order number. */
                __('New order to fulfil #%s', 'sage-front'),
                $order->get_order_number(),
            ),
            view('emails.partner-order', ['order' => $partnerOrder])->render(),
            ['Content-Type: text/html; charset=UTF-8'],
        );

        if (! $sent) {
            error_log('Partner order e-mail was not sent. Order ID: ' . $order->get_id());

            return false;
        }

        // Re-entering `processing` must not send a second copy.
        $order->update_meta_data(self::EMAIL_SENT_META, current_time('mysql'));
        $order->save();

        return true;
    }

    public static function sendWebhook(WC_Order $order): bool
    {
        $url = self::webhookUrl();

        if ($url === '' || $order->get_meta(self::WEBHOOK_SENT_META, true)) {
            return false;
        }

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'timeout' => self::WEBHOOK_TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => (string) wp_json_encode((new PartnerOrder($order))->toArray()),
        ]);

        if (is_wp_error($response)) {
            error_log('Make.com webhook failed for order ' . $order->get_id() . ': ' . $response->get_error_message());

            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            error_log('Make.com webhook returned HTTP ' . $code . ' for order ' . $order->get_id());

            return false;
        }

        $order->update_meta_data(self::WEBHOOK_SENT_META, current_time('mysql'));
        $order->save();

        return true;
    }

    /**
     * @return array{partner_email: string, webhook_url: string, sheets_url: string}
     */
    public static function defaultOptions(): array
    {
        return ['partner_email' => '', 'webhook_url' => '', 'sheets_url' => ''];
    }

    /**
     * @return array{partner_email: string, webhook_url: string, sheets_url: string}
     */
    public static function settings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        return [
            'partner_email' => trim((string) ($stored['partner_email'] ?? '')),
            'webhook_url' => trim((string) ($stored['webhook_url'] ?? '')),
            'sheets_url' => trim((string) ($stored['sheets_url'] ?? '')),
        ];
    }

    public static function recipient(): string
    {
        $email = (string) apply_filters(
            'sage/shop/partner_order_email',
            self::settings()['partner_email'],
        );

        $email = trim($email);

        return $email !== '' && is_email($email) ? $email : '';
    }

    public static function webhookUrl(): string
    {
        $url = trim((string) apply_filters(
            'sage/shop/make_webhook_url',
            self::settings()['webhook_url'],
        ));

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}
