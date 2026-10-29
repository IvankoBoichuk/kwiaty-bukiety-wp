<?php

declare(strict_types=1);

namespace App\Shop;

use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Mirrors an order into the Google Sheet the shop runs its fulfilment from,
 * through the Sheets API as a service account.
 *
 * Production does this from the `advanced-checkout-page` plugin, which posts to
 * an Apps Script web app straight out of `woocommerce_order_status_changed`,
 * and the script's doPost() decides what happens to the sheet. That decision
 * now lives here, kept the same:
 *
 * - The row is found by the order ID in column A.
 * - `cancelled` deletes it; any other status writes A-H over it.
 * - An order with no row yet gets a new one -- at the top, under the header,
 *   where the script appended at the bottom. The shop asked for newest first.
 * - Columns past H belong to the people working the sheet and are never
 *   written.
 *
 * Around it:
 *
 * - The request leaves the status hook. Production waits on a 5 second timeout
 *   inside the transition itself, so since May 2026 nearly every send fails
 *   with cURL error 28 while the checkout and the payment callback sit behind
 *   it. Here the hook only queues an Action Scheduler job.
 * - The key sits in the environment (GOOGLE_SHEETS_CREDENTIALS) and the sheet
 *   in a setting; either one missing switches the module off. A copy of the
 *   database on dev carries the setting but not the key, so it cannot write
 *   into the live sheet.
 * - A failed send is tried again twice with a widening gap instead of being
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
     * One flag per status, so `processing -> completed` writes twice while
     * re-entering `processing` writes nothing.
     */
    public const SENT_META_PREFIX = '_kb_sheets_sent_';

    /**
     * Only these four reach the sheet, matching production.
     *
     * @var array<int, string>
     */
    public const SYNCED_STATUSES = ['processing', 'completed', 'cancelled', 'refunded'];

    /**
     * The tab the Apps Script wrote to.
     */
    public const DEFAULT_TAB = 'Zamówienia';

    /**
     * Row 1 is the header; orders start under it.
     */
    public const FIRST_ROW = 2;

    public const MAX_ATTEMPTS = 3;

    /**
     * Seconds to wait before attempts 2 and 3.
     *
     * @var array<int, int>
     */
    public const RETRY_DELAYS = [300, 1800];

    /**
     * How long a job waits when another one is mid-write. It does not count as
     * an attempt.
     */
    public const LOCK_DELAY = 30;

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

        // Switched off is not a failure, so it stays out of the log: dev runs
        // this way permanently.
        if (! self::enabled()) {
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

        $client = self::client();

        if ($client === null) {
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

        // Rows are addressed by position, and every new order pushes the rest
        // down one, so two jobs writing at once could each land on the other's
        // row. One at a time; the other waits its turn.
        if (! self::lock()) {
            as_schedule_single_action(
                time() + self::LOCK_DELAY,
                self::ACTION_HOOK,
                [$orderId, $status, $attempt],
                self::ACTION_GROUP,
            );

            return;
        }

        try {
            $outcome = self::write($client, $order, $status);
        } catch (RuntimeException $e) {
            self::retryOrGiveUp($order, $status, $attempt, $e->getMessage());

            return;
        } finally {
            self::unlock();
        }

        $order->update_meta_data(self::sentMetaKey($status), current_time('mysql'));
        $order->save();

        self::log(sprintf('Order %d synced to the sheet as %s: %s.', $order->get_id(), $status, $outcome));
    }

    /**
     * What doPost() did, with new rows going in at the top.
     *
     * @return string What happened, for the log.
     */
    public static function write(GoogleSheetsClient $client, WC_Order $order, string $status): string
    {
        $tab = self::tab();
        $ids = $client->idColumn($tab);
        $index = array_search((string) $order->get_id(), $ids, true);
        $row = $index === false ? null : $index + self::FIRST_ROW;

        if ($status === 'cancelled') {
            if ($row === null) {
                return 'no row to delete';
            }

            $client->deleteRow($tab, $row);

            return 'deleted row ' . $row;
        }

        $values = self::row($order, $status);

        if ($row !== null) {
            $client->writeRow($tab, $row, $values);

            return 'updated row ' . $row;
        }

        // An insert whose write failed leaves an empty top row behind. The
        // retry fills it rather than opening a second one above it -- but only
        // when the row is empty all the way across, since a phone order may
        // have been typed in with no ID.
        if (($ids[0] ?? '') !== '' || ! $client->rowIsEmpty($tab, self::FIRST_ROW)) {
            $client->insertRow($tab, self::FIRST_ROW);
        }

        $client->writeRow($tab, self::FIRST_ROW, $values);

        return 'inserted row ' . self::FIRST_ROW;
    }

    /**
     * Columns A-H, as doPost() built them: the ID, date, hour, products, city,
     * postcode, price and status.
     *
     * The values are written as if typed in, so a city that a customer typed
     * as `=IMPORTXML(...)` would become a live formula in the shop's sheet.
     * Text that opens like a formula is quoted, which the sheet shows as the
     * plain text it was.
     *
     * @return array<int, string>
     */
    public static function row(WC_Order $order, string $status): array
    {
        $partnerOrder = new PartnerOrder($order);

        $cells = [
            (string) $order->get_id(),
            self::orDash($partnerOrder->deliveryDate()),
            self::orDash($partnerOrder->deliveryTime()),
            implode(', ', self::products($order)),
            (string) $order->get_shipping_city(),
            (string) $order->get_shipping_postcode(),
            (string) $order->get_total(),
            $status,
        ];

        return array_map(
            static fn(string $cell): string => preg_match('/^[=+\-@]/', $cell) ? "'" . $cell : $cell,
            $cells,
        );
    }

    /**
     * "Name xQty", with a wreath's deceased name and ribbon message in
     * brackets after it: the sheet has no column of its own for them, and
     * columns past H are not ours to add.
     *
     * @return array<int, string>
     */
    public static function products(WC_Order $order): array
    {
        $products = [];

        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_name() . ' x' . (int) $item->get_quantity();
            $note = FuneralCartItem::orderItemNote($item);

            $products[] = $note !== '' ? $product . ' (' . $note . ')' : $product;
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

    public static function enabled(): bool
    {
        return self::spreadsheetId() !== '' && GoogleSheetsClient::credentials() !== null;
    }

    /**
     * The API client, or null when the module is off.
     */
    public static function client(): ?GoogleSheetsClient
    {
        $credentials = GoogleSheetsClient::credentials();
        $spreadsheetId = self::spreadsheetId();

        if ($credentials === null || $spreadsheetId === '') {
            return null;
        }

        return new GoogleSheetsClient($credentials, $spreadsheetId);
    }

    public static function spreadsheetId(): string
    {
        return self::parseSpreadsheetId((string) apply_filters(
            'sage/shop/sheets_spreadsheet_id',
            OrderNotifications::settings()['sheets_spreadsheet_id'],
        ));
    }

    public static function tab(): string
    {
        $tab = trim((string) apply_filters(
            'sage/shop/sheets_tab',
            OrderNotifications::settings()['sheets_tab'],
        ));

        return $tab !== '' ? $tab : self::DEFAULT_TAB;
    }

    /**
     * Takes either the bare ID or the whole address out of the browser bar,
     * since the address is what people copy. Anything else is empty, i.e. off.
     */
    public static function parseSpreadsheetId(string $value): string
    {
        $value = trim($value);

        if (preg_match('~/spreadsheets/d/([A-Za-z0-9_-]+)~', $value, $match)) {
            return $match[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{20,}$/', $value) ? $value : '';
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

    /**
     * A MySQL named lock rather than an option: taking it is atomic, and it is
     * released with the connection, so a job that dies mid-write cannot leave
     * the sync locked.
     */
    protected static function lock(): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', self::lockName())) === 1;
    }

    protected static function unlock(): void
    {
        global $wpdb;

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lockName()));
    }

    /**
     * Named locks are server-wide, so the name carries the spreadsheet: two
     * databases on one server writing to two sheets do not wait on each other.
     */
    protected static function lockName(): string
    {
        return 'kb_sheets_' . md5(self::spreadsheetId());
    }

    protected static function log(string $message, string $level = 'info'): void
    {
        if (! function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->log($level, $message, ['source' => self::LOG_SOURCE]);
    }

    /**
     * The sheet shows an em dash for a missing date or hour, as doPost() did.
     */
    protected static function orDash(string $value): string
    {
        return $value !== '' ? $value : '—';
    }
}
