<?php

declare(strict_types=1);

use App\Shop\OrderNotifications;
use App\Shop\OrderSheetsSync;

/**
 * The sync writes into a spreadsheet the shop runs its day from, so the parts
 * worth pinning down are the ones whose failure is silent: a payload key that
 * drifts, a status that slips through, a row written twice, and a send that
 * leaves the site when the module is supposed to be off.
 */
function kb_order(array $overrides = []): WC_Order
{
    $order = new WC_Order(
        $overrides['id'] ?? 11125,
        $overrides['status'] ?? 'processing',
        $overrides['meta'] ?? [],
        $overrides['items'] ?? [new WC_Order_Item_Product('Bukiet Morska Bryza', 1)],
        $overrides['total'] ?? '189.00',
        $overrides['city'] ?? 'Warszawa',
        $overrides['postcode'] ?? '00-001',
    );

    $GLOBALS['kb_test']['orders'][$order->get_id()] = $order;

    return $order;
}

function kb_with_url(string $url = 'https://example.test/exec'): void
{
    $GLOBALS['kb_test']['options'][OrderNotifications::OPTION_NAME] = [
        'partner_email' => '',
        'webhook_url' => '',
        'sheets_url' => $url,
    ];
}

beforeEach(function () {
    kb_test_reset();
});

describe('payload', function () {
    it('carries exactly the keys the Apps Script reads', function () {
        $order = kb_order(['meta' => ['delivery_date' => '2026-10-02', 'delivery_time' => '12:00-15:00']]);

        expect(array_keys(OrderSheetsSync::payload($order, 'processing')))
            ->toBe(['id', 'status', 'date', 'time', 'city', 'postcode', 'price', 'products']);
    });

    it('builds the row production builds', function () {
        $order = kb_order(['meta' => ['delivery_date' => '2026-10-02', 'delivery_time' => '12:00-15:00']]);

        expect(OrderSheetsSync::payload($order, 'processing'))->toBe([
            'id' => 11125,
            'status' => 'processing',
            'date' => '2026-10-02',
            'time' => '12:00-15:00',
            'city' => 'Warszawa',
            'postcode' => '00-001',
            'price' => '189.00',
            'products' => ['Bukiet Morska Bryza x1'],
        ]);
    });

    it('falls back to an em dash when the date or hour is missing', function () {
        $payload = OrderSheetsSync::payload(kb_order(), 'processing');

        expect($payload['date'])->toBe('—')
            ->and($payload['time'])->toBe('—');
    });

    it('formats every line as name x quantity', function () {
        $order = kb_order(['items' => [
            new WC_Order_Item_Product('Bukiet Morska Bryza', 1),
            new WC_Order_Item_Product('Róże czerwone', 3),
        ]]);

        expect(OrderSheetsSync::payload($order, 'completed')['products'])
            ->toBe(['Bukiet Morska Bryza x1', 'Róże czerwone x3']);
    });

    it('reads the date a new order writes on the order', function () {
        $order = kb_order(['meta' => ['delivery_date' => '2026-10-05']]);

        expect(OrderSheetsSync::payload($order, 'processing')['date'])->toBe('2026-10-05');
    });

    it('reads the date a migrated order carries on the line item', function () {
        // Orders carried over from production spell it with an "e", and keep it
        // on the item rather than the order.
        $order = kb_order(['items' => [
            new WC_Order_Item_Product('Bukiet', 1, ['Date dostawy' => '2026-09-30', 'Godzina dostawy' => '09:00-12:00']),
        ]]);

        $payload = OrderSheetsSync::payload($order, 'processing');

        expect($payload['date'])->toBe('2026-09-30')
            ->and($payload['time'])->toBe('09:00-12:00');
    });
});

describe('status filter', function () {
    it('queues the four statuses the sheet tracks', function () {
        kb_with_url();

        foreach (OrderSheetsSync::SYNCED_STATUSES as $status) {
            kb_test_reset();
            kb_with_url();
            kb_order(['status' => $status]);

            expect(OrderSheetsSync::queue(11125, $status))->toBeTrue();
            expect($GLOBALS['kb_test']['async'])->toHaveCount(1);
        }
    });

    it('ignores every other status', function () {
        kb_with_url();
        kb_order(['status' => 'on-hold']);

        expect(OrderSheetsSync::queue(11125, 'on-hold'))->toBeFalse();
        expect(OrderSheetsSync::queue(11125, 'pending'))->toBeFalse();
        expect($GLOBALS['kb_test']['async'])->toBeEmpty();
    });

    it('files the job under the kb-sheets group', function () {
        kb_with_url();
        kb_order();

        OrderSheetsSync::queue(11125, 'processing');

        expect($GLOBALS['kb_test']['async'][0])->toBe([
            'hook' => OrderSheetsSync::ACTION_HOOK,
            'args' => [11125, 'processing'],
            'group' => OrderSheetsSync::ACTION_GROUP,
        ]);
    });
});

describe('idempotency', function () {
    it('does not queue the same status twice', function () {
        kb_with_url();
        kb_order(['meta' => [OrderSheetsSync::sentMetaKey('processing') => '2026-10-02 12:00:00']]);

        expect(OrderSheetsSync::queue(11125, 'processing'))->toBeFalse();
        expect($GLOBALS['kb_test']['async'])->toBeEmpty();
    });

    it('still queues a different status', function () {
        kb_with_url();
        kb_order([
            'status' => 'completed',
            'meta' => [OrderSheetsSync::sentMetaKey('processing') => '2026-10-02 12:00:00'],
        ]);

        expect(OrderSheetsSync::queue(11125, 'completed'))->toBeTrue();
    });

    it('queues again when a resend clears the flag', function () {
        kb_with_url();
        kb_order(['meta' => [OrderSheetsSync::sentMetaKey('processing') => '2026-10-02 12:00:00']]);

        expect(OrderSheetsSync::resend(11125, 'processing'))->toBeTrue();
    });
});

describe('the off switch', function () {
    it('queues nothing while the URL is empty', function () {
        kb_order();

        expect(OrderSheetsSync::url())->toBe('');
        expect(OrderSheetsSync::queue(11125, 'processing'))->toBeFalse();
        expect($GLOBALS['kb_test']['async'])->toBeEmpty();
    });

    it('sends nothing while the URL is empty', function () {
        kb_order();

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
    });

    it('treats a malformed URL as off rather than as an endpoint', function () {
        kb_with_url('not-a-url');

        expect(OrderSheetsSync::url())->toBe('');
    });
});

describe('the response', function () {
    it('marks the status sent on a 2xx', function () {
        kb_with_url();
        $order = kb_order();
        kb_test_http([['response' => ['code' => 200]]]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($order->get_meta(OrderSheetsSync::sentMetaKey('processing')))->not->toBe('');
        expect($GLOBALS['kb_test']['scheduled'])->toBeEmpty();
    });

    it('retries a 5xx instead of dropping the row', function () {
        kb_with_url();
        kb_order();
        kb_test_http([['response' => ['code' => 500]]]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['scheduled'])->toHaveCount(1);
        expect($GLOBALS['kb_test']['scheduled'][0]['args'])->toBe([11125, 'processing', 2]);
    });

    it('retries a transport error', function () {
        kb_with_url();
        kb_order();
        kb_test_http([new WP_Error('cURL error 28')]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['scheduled'])->toHaveCount(1);
    });

    it('widens the gap between attempts', function () {
        kb_with_url();
        kb_order();
        kb_test_http([['response' => ['code' => 500]], ['response' => ['code' => 500]]]);

        OrderSheetsSync::handleTask(11125, 'processing', 1);
        $first = $GLOBALS['kb_test']['scheduled'][0]['timestamp'];

        OrderSheetsSync::handleTask(11125, 'processing', 2);
        $second = $GLOBALS['kb_test']['scheduled'][1]['timestamp'];

        expect($second)->toBeGreaterThan($first);
    });

    it('notes the failure on the order once the attempts are spent', function () {
        kb_with_url();
        $order = kb_order();
        kb_test_http([['response' => ['code' => 500]]]);

        OrderSheetsSync::handleTask(11125, 'processing', OrderSheetsSync::MAX_ATTEMPTS);

        expect($GLOBALS['kb_test']['scheduled'])->toBeEmpty();
        expect($order->notes)->toHaveCount(1);
        expect($order->notes[0])->toContain('processing');
    });

    it('does nothing when the order is gone', function () {
        kb_with_url();

        OrderSheetsSync::handleTask(999, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
    });

    it('skips an order that has left the status since the job was filed', function () {
        kb_with_url();
        kb_order(['status' => 'cancelled']);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
    });
});

describe('the manual resend', function () {
    it('is hidden from a user who cannot edit orders', function () {
        $GLOBALS['kb_test']['can'] = false;

        expect(OrderSheetsSync::registerOrderAction([]))->toBe([]);
    });

    it('is offered to a user who can', function () {
        expect(OrderSheetsSync::registerOrderAction([]))
            ->toHaveKey(OrderSheetsSync::ORDER_ACTION);
    });
});
