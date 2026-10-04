<?php

declare(strict_types=1);

namespace App\Shop;

use WC_Order;
use WC_Order_Item_Product;

/**
 * Mirrors an order into the Google Sheet the shop runs its fulfilment from.
 *
 * Production does this from the `advanced-checkout-page` plugin, which posts to
 * an Apps Script web app straight out of `woocommerce_order_status_changed`.
 * The payload here is deliberately identical -- the Apps Script on the other
 * side is not ours to change -- but everything around it is not:
 *
 * - The request leaves the status hook. Production waits on a 5 second timeout
 *   inside the transition itself, so since May 2026 nearly every send fails
 *   with cURL error 28 while the checkout and the payment callback sit behind
 *   it. Here the hook only queues an Action Scheduler job.
 * - The URL is a setting rather than a constant in the source, and an empty one
 *   switches the module off. That is what stops a copy of the database on dev
 *   from writing into the live sheet.
 * - A failed send is retried three times with a widening gap instead of being
 *   dropped, and the last failure is recorded on the order rather than in an
 *   unbounded `_gs_debug_log` meta.
 * - Re-sending by hand is an order action and a WP-CLI command, both behind a
 *   capability check, rather than production's `?debug_order=<id>` which any
 *   logged-in visitor could fire.
 */
final class OrderSheetsSync
{
    /**
     * The Action Scheduler hook and group the jobs are filed under.
     */
    public const ACTION_HOOK = 'kb_sheets_sync';

    public const ACTION_GROUP = 'kb-sheets';

    /**
     * `wc_get_logger()` channel. Production appended to a post meta that grew
     * without a bound.
     */
    public const LOG_SOURCE = 'kb-sheets';

    /**
     * One flag per status, so `processing -> completed` writes two rows while
     * re-entering `processing` writes none.
     */
    public const SENT_META_PREFIX = '_kb_sheets_sent_';

    /**
     * Only these four reach the sheet, matching production.
     *
     * @var array<int, string>
     */
    public const SYNCED_STATUSES = ['processing', 'completed', 'cancelled', 'refunded'];

    /**
     * Matches OrderNotifications::WEBHOOK_TIMEOUT. Apps Script answers with a
     * redirect to script.googleusercontent.com, so the follow-up counts against
     * the same budget.
     */
    public const TIMEOUT = OrderNotifications::WEBHOOK_TIMEOUT;

    public const MAX_ATTEMPTS = 3;

    /**
     * Seconds to wait before attempts 2 and 3.
     *
     * @var array<int, int>
     */
    public const RETRY_DELAYS = [300, 1800, 7200];

    public const ORDER_ACTION = 'kb_sheets_resend';

    public static function boot(): void
    {
        add_action('woocommerce_order_status_changed', [self::class, 'onStatusChanged'], 20, 4);
        add_action(self::ACTION_HOOK, [self::class, 'handleTask'], 10, 3);

        add_filter('woocommerce_order_actions', [self::class, 'registerOrderAction']);
        add_action('woocommerce_order_action_' . self::ORDER_ACTION, [self::class, 'handleOrderAction']);
    }

    /**
     * `woocommerce_order_status_changed` passes statuses without the `wc-`
     * prefix, which is the form the sheet stores.
     */
    public static function onStatusChanged(mixed $orderId, mixed $from, mixed $to): void
    {
        self::queue((int) $orderId, (string) $to);
    }

    /**
     * Files the job. Nothing here talks to the network: that is the whole point
     * of the module.
     *
     * `$force` is what the order action and the CLI command pass to re-send a
     * status that has already been recorded.
     */
    public static function queue(int $orderId, string $status, bool $force = false): bool
    {
        if (! self::shouldSync($status)) {
            return false;
        }

        // An unset URL is the off switch, not a failure, so it stays out of the
        // log: dev runs this way permanently.
        if (self::url() === '') {
            return false;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return false;
        }

        if ($force) {
            $order->delete_meta_data(self::sentMetaKey($status));
            $order->save();
        } elseif (self::alreadySent($order, $status)) {
            return false;
        }

        as_enqueue_async_action(
            self::ACTION_HOOK,
            [$orderId, $status],
            self::ACTION_GROUP,
        );

        return true;
    }

    /**
     * Runs inside Action Scheduler, where a slow or failing request costs
     * nobody a page load.
     */
    public static function handleTask(mixed $orderId, mixed $status, mixed $attempt = 1): void
    {
        $orderId = (int) $orderId;
        $status = (string) $status;
        $attempt = max(1, (int) $attempt);

        $url = self::url();

        if ($url === '') {
            return;
        }

        $order = wc_get_order($orderId);

        // Deleted between queueing and running: there is nothing to report.
        if (! $order instanceof WC_Order) {
            return;
        }

        // The order may have moved on while the job waited -- a retry of
        // `processing` must not land after the order was cancelled.
        if ($order->get_status() !== $status) {
            self::log(sprintf(
                'Order %d left %s before the sync ran; skipping.',
                $orderId,
                $status,
            ));

            return;
        }

        if (self::alreadySent($order, $status)) {
            return;
        }

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'timeout' => self::TIMEOUT,
            // Apps Script answers 302 to script.googleusercontent.com; success
            // is the status of the request at the end of that chain.
            'redirection' => 5,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => (string) wp_json_encode(self::payload($order, $status)),
        ]);

        if (is_wp_error($response)) {
            self::retryOrGiveUp($order, $status, $attempt, $response->get_error_message());

            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            self::retryOrGiveUp($order, $status, $attempt, 'HTTP ' . $code);

            return;
        }

        $order->update_meta_data(self::sentMetaKey($status), current_time('mysql'));
        $order->save();

        self::log(sprintf('Order %d synced to the sheet as %s.', $order->get_id(), $status));
    }

    /**
     * The row the Apps Script expects. Keys, order and formatting match
     * production exactly; changing them means changing the spreadsheet.
     *
     * @return array{id: int, status: string, date: string, time: string, city: string, postcode: string, price: string, products: array<int, string>}
     */
    public static function payload(WC_Order $order, string $status): array
    {
        $partnerOrder = new PartnerOrder($order);

        return [
            'id' => $order->get_id(),
            'status' => $status,
            'date' => self::orDash($partnerOrder->deliveryDate()),
            'time' => self::orDash($partnerOrder->deliveryTime()),
            'city' => (string) $order->get_shipping_city(),
            'postcode' => (string) $order->get_shipping_postcode(),
            'price' => (string) $order->get_total(),
            'products' => self::products($order),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function products(WC_Order $order): array
    {
        $products = [];

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $products[] = $item->get_name() . ' x' . (int) $item->get_quantity();
        }

        return $products;
    }

    public static function shouldSync(string $status): bool
    {
        return in_array($status, self::SYNCED_STATUSES, true);
    }

    public static function sentMetaKey(string $status): string
    {
        return self::SENT_META_PREFIX . $status;
    }

    public static function alreadySent(WC_Order $order, string $status): bool
    {
        return (string) $order->get_meta(self::sentMetaKey($status), true) !== '';
    }

    /**
     * The Apps Script endpoint, or an empty string when the module is off.
     */
    public static function url(): string
    {
        $url = trim((string) apply_filters(
            'sage/shop/sheets_url',
            OrderNotifications::settings()['sheets_url'],
        ));

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * Adds the manual re-send to the order editor, for users who may edit
     * orders. Production exposed the same thing as `?debug_order=<id>` on
     * `admin_init`, with no capability check at all.
     *
     * @param  array<string, string>  $actions
     * @return array<string, string>
     */
    public static function registerOrderAction(array $actions): array
    {
        if (! current_user_can('edit_shop_orders')) {
            return $actions;
        }

        $actions[self::ORDER_ACTION] = __('Send to Google Sheets', 'sage-back');

        return $actions;
    }

    public static function handleOrderAction(mixed $order): void
    {
        if (! $order instanceof WC_Order || ! current_user_can('edit_shop_orders')) {
            return;
        }

        self::resend($order->get_id(), $order->get_status());
    }

    /**
     * Clears the flag for a status and queues it again. Shared by the order
     * action and the CLI command.
     */
    public static function resend(int $orderId, ?string $status = null): bool
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return self::queue($orderId, $status ?? $order->get_status(), true);
    }

    /**
     * Schedules the next attempt, or records the failure on the order once the
     * attempts are spent. Production did neither: a failed send was lost.
     */
    protected static function retryOrGiveUp(WC_Order $order, string $status, int $attempt, string $reason): void
    {
        $orderId = $order->get_id();

        if ($attempt >= self::MAX_ATTEMPTS) {
            self::log(sprintf(
                'Order %d failed to sync as %s after %d attempts: %s',
                $orderId,
                $status,
                $attempt,
                $reason,
            ), 'error');

            $order->add_order_note(sprintf(
                /* translators: 1: order status, 2: number of attempts, 3: failure reason. */
                __('Google Sheets sync failed for status %1$s after %2$d attempts: %3$s', 'sage-back'),
                $status,
                $attempt,
                $reason,
            ));
            $order->save();

            return;
        }

        $delay = self::RETRY_DELAYS[$attempt - 1] ?? self::RETRY_DELAYS[count(self::RETRY_DELAYS) - 1];

        self::log(sprintf(
            'Order %d failed to sync as %s on attempt %d (%s); retrying in %d s.',
            $orderId,
            $status,
            $attempt,
            $reason,
            $delay,
        ), 'warning');

        as_schedule_single_action(
            time() + $delay,
            self::ACTION_HOOK,
            [$orderId, $status, $attempt + 1],
            self::ACTION_GROUP,
        );
    }

    protected static function log(string $message, string $level = 'info'): void
    {
        if (! function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->log($level, $message, ['source' => self::LOG_SOURCE]);
    }

    /**
     * The sheet shows an em dash for a missing date or hour, so the payload
     * carries one rather than an empty cell.
     */
    protected static function orDash(string $value): string
    {
        return $value !== '' ? $value : '—';
    }
}
