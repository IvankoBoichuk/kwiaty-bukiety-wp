<?php

declare(strict_types=1);

namespace App\Console;

use App\Shop\OrderSheetsSync;
use WC_Order;

/**
 * `wp kb sheets resend <order_id> [--status=<status>]`.
 *
 * Replaces production's `?debug_order=<id>`, which re-fired the send from
 * `admin_init` for anyone who hit the URL, logged in or not. This runs as the
 * shell user, which is a capability boundary WP-CLI already owns.
 *
 * It clears the sent flag for the status and queues the job, so the resend goes
 * through the same Action Scheduler path, retries and logging as an automatic
 * one rather than a second code path that can drift.
 */
final class OrderSheetsResend
{
    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $assoc
     */
    public static function handle(array $args, array $assoc = []): void
    {
        $orderId = (int) ($args[0] ?? 0);

        if ($orderId <= 0) {
            \WP_CLI::error('An order ID is required: wp kb sheets resend <order_id> [--status=<status>]');
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof WC_Order) {
            \WP_CLI::error(sprintf('Order %d was not found.', $orderId));
        }

        $status = trim((string) ($assoc['status'] ?? '')) ?: $order->get_status();

        if (! OrderSheetsSync::shouldSync($status)) {
            \WP_CLI::error(sprintf(
                'Status "%s" is not synced. Expected one of: %s.',
                $status,
                implode(', ', OrderSheetsSync::SYNCED_STATUSES),
            ));
        }

        if (OrderSheetsSync::url() === '') {
            \WP_CLI::error(
                'No Google Sheets URL is set (Appearance -> Order Notifications), so the sync is switched off.',
            );
        }

        if (! OrderSheetsSync::resend($orderId, $status)) {
            \WP_CLI::error(sprintf('Could not queue order %d as %s.', $orderId, $status));
        }

        \WP_CLI::success(sprintf(
            'Queued order %d as %s. It is sent when Action Scheduler runs the %s group.',
            $orderId,
            $status,
            OrderSheetsSync::ACTION_GROUP,
        ));
    }
}
