<?php

declare(strict_types=1);

use App\Shop\GoogleSheetsClient;
use App\Shop\OrderNotifications;
use App\Shop\OrderSheetsSync;

/**
 * The sync writes into a spreadsheet the shop runs its day from, so the parts
 * worth pinning down are the ones whose failure is silent: a cell that drifts,
 * a status that slips through, a row written twice or onto the wrong order,
 * and a send that leaves the site when the module is supposed to be off.
 */
const KB_SPREADSHEET = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';

const KB_SA_EMAIL = 'orders@kb-test.iam.gserviceaccount.com';

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

/**
 * One RSA key for the whole file: generating it is the slow part.
 *
 * @return array{private: string, public: string}
 */
function kb_key(): array
{
    static $key = null;

    if ($key === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $key = ['private' => $private, 'public' => openssl_pkey_get_details($resource)['key']];
    }

    return $key;
}

/**
 * Switches the module on: a spreadsheet in the settings, a key in the
 * environment, and -- unless a test wants to watch them being fetched -- a
 * cached token and tab id, so the requests a test sees are the sheet's own.
 */
function kb_with_sheet(bool $cached = true): void
{
    $GLOBALS['kb_test']['options'][OrderNotifications::OPTION_NAME] = [
        'partner_email' => '',
        'webhook_url' => '',
        'sheets_spreadsheet_id' => KB_SPREADSHEET,
        'sheets_tab' => 'Zamówienia',
    ];

    $GLOBALS['kb_test']['filters']['sage/shop/sheets_credentials'] = json_encode([
        'client_email' => KB_SA_EMAIL,
        'private_key' => kb_key()['private'],
    ]);

    if ($cached) {
        $GLOBALS['kb_test']['transients']['kb_sheets_token_' . md5(KB_SA_EMAIL)] = 'test-token';
        $GLOBALS['kb_test']['transients']['kb_sheets_gid_' . md5(KB_SPREADSHEET . "\0Zamówienia")] = 777;
    }
}

/**
 * The column-A read the sync starts every write with.
 *
 * @param  array<int, string|int>  $ids
 */
function kb_ids(array $ids): array
{
    return kb_test_json($ids === [] ? [] : ['values' => [$ids]]);
}

/**
 * @return array{method: string, url: string, body: mixed}
 */
function kb_request(int $index): array
{
    $request = $GLOBALS['kb_test']['requests'][$index];

    return [
        'method' => $request['args']['method'] ?? 'POST',
        'url' => urldecode($request['url']),
        'body' => isset($request['args']['body']) && is_string($request['args']['body'])
            ? json_decode($request['args']['body'], true)
            : ($request['args']['body'] ?? null),
    ];
}

beforeEach(function () {
    kb_test_reset();
});

describe('the row', function () {
    it('builds columns A-H the way doPost() did', function () {
        $order = kb_order(['meta' => ['delivery_date' => '2026-10-02', 'delivery_time' => '12:00-15:00']]);

        expect(OrderSheetsSync::row($order, 'processing'))->toBe([
            '11125',
            '2026-10-02',
            '12:00-15:00',
            'Bukiet Morska Bryza x1',
            'Warszawa',
            '00-001',
            '189.00',
            'processing',
        ]);
    });

    it('falls back to an em dash when the date or hour is missing', function () {
        $row = OrderSheetsSync::row(kb_order(), 'processing');

        expect($row[1])->toBe('—')
            ->and($row[2])->toBe('—');
    });

    it('joins the products with a comma, as name x quantity', function () {
        $order = kb_order(['items' => [
            new WC_Order_Item_Product('Bukiet Morska Bryza', 1),
            new WC_Order_Item_Product('Róże czerwone', 3),
        ]]);

        expect(OrderSheetsSync::row($order, 'completed')[3])
            ->toBe('Bukiet Morska Bryza x1, Róże czerwone x3');
    });

    it('reads the date a migrated order carries on the line item', function () {
        // Orders carried over from production spell it with an "e", and keep it
        // on the item rather than the order.
        $order = kb_order(['items' => [
            new WC_Order_Item_Product('Bukiet', 1, ['Date dostawy' => '2026-09-30', 'Godzina dostawy' => '09:00-12:00']),
        ]]);

        $row = OrderSheetsSync::row($order, 'processing');

        expect($row[1])->toBe('2026-09-30')
            ->and($row[2])->toBe('09:00-12:00');
    });

    it('quotes customer text that would otherwise become a formula', function () {
        $row = OrderSheetsSync::row(kb_order(['city' => '=IMPORTXML("https://x.test")', 'postcode' => '+48']), 'processing');

        expect($row[4])->toBe('\'=IMPORTXML("https://x.test")')
            ->and($row[5])->toBe("'+48");
    });
});

describe('status filter', function () {
    it('queues the four statuses the sheet tracks', function () {
        foreach (OrderSheetsSync::SYNCED_STATUSES as $status) {
            kb_test_reset();
            kb_with_sheet();
            kb_order(['status' => $status]);

            expect(OrderSheetsSync::queue(11125, $status))->toBeTrue();
            expect($GLOBALS['kb_test']['async'])->toHaveCount(1);
        }
    });

    it('ignores every other status', function () {
        kb_with_sheet();
        kb_order(['status' => 'on-hold']);

        expect(OrderSheetsSync::queue(11125, 'on-hold'))->toBeFalse();
        expect(OrderSheetsSync::queue(11125, 'pending'))->toBeFalse();
        expect($GLOBALS['kb_test']['async'])->toBeEmpty();
    });

    it('files the job under the kb-sheets group', function () {
        kb_with_sheet();
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
        kb_with_sheet();
        kb_order(['meta' => [OrderSheetsSync::sentMetaKey('processing') => '2026-10-02 12:00:00']]);

        expect(OrderSheetsSync::queue(11125, 'processing'))->toBeFalse();
        expect($GLOBALS['kb_test']['async'])->toBeEmpty();
    });

    it('still queues a different status', function () {
        kb_with_sheet();
        kb_order([
            'status' => 'completed',
            'meta' => [OrderSheetsSync::sentMetaKey('processing') => '2026-10-02 12:00:00'],
        ]);

        expect(OrderSheetsSync::queue(11125, 'completed'))->toBeTrue();
    });

    it('queues again when a resend clears the flag', function () {
        kb_with_sheet();
        kb_order(['meta' => [OrderSheetsSync::sentMetaKey('processing') => '2026-10-02 12:00:00']]);

        expect(OrderSheetsSync::resend(11125, 'processing'))->toBeTrue();
    });
});

describe('the off switch', function () {
    it('queues nothing without a spreadsheet', function () {
        kb_with_sheet();
        $GLOBALS['kb_test']['options'][OrderNotifications::OPTION_NAME]['sheets_spreadsheet_id'] = '';
        kb_order();

        expect(OrderSheetsSync::enabled())->toBeFalse();
        expect(OrderSheetsSync::queue(11125, 'processing'))->toBeFalse();
    });

    it('queues nothing without a key, which is how a copy of the database runs', function () {
        kb_with_sheet();
        unset($GLOBALS['kb_test']['filters']['sage/shop/sheets_credentials']);
        kb_order();

        expect(OrderSheetsSync::enabled())->toBeFalse();
        expect(OrderSheetsSync::queue(11125, 'processing'))->toBeFalse();
    });

    it('sends nothing when the key is gone by the time the job runs', function () {
        kb_with_sheet();
        $GLOBALS['kb_test']['filters']['sage/shop/sheets_credentials'] = '';
        kb_order();

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
    });

    it('treats a key without its private half as no key', function () {
        $GLOBALS['kb_test']['filters']['sage/shop/sheets_credentials'] = json_encode(['client_email' => KB_SA_EMAIL]);

        expect(GoogleSheetsClient::credentials())->toBeNull();
    });

    it('takes the spreadsheet as a link or a bare ID, and nothing else', function () {
        expect(OrderSheetsSync::parseSpreadsheetId('https://docs.google.com/spreadsheets/d/' . KB_SPREADSHEET . '/edit#gid=0'))
            ->toBe(KB_SPREADSHEET)
            ->and(OrderSheetsSync::parseSpreadsheetId(KB_SPREADSHEET))->toBe(KB_SPREADSHEET)
            ->and(OrderSheetsSync::parseSpreadsheetId('https://script.google.com/macros/s/abc/exec'))->toBe('');
    });
});

describe('writing the sheet', function () {
    it('puts a new order at the top, under the header', function () {
        kb_with_sheet();
        $order = kb_order();
        kb_test_http([kb_ids(['11124', '11120'])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toHaveCount(3);

        expect(kb_request(0)['method'])->toBe('GET')
            ->and(kb_request(0)['url'])->toContain("/values/'Zamówienia'!A2:A");

        expect(kb_request(1)['url'])->toEndWith(KB_SPREADSHEET . ':batchUpdate')
            ->and(kb_request(1)['body']['requests'][0]['insertDimension']['range'])->toBe([
                'sheetId' => 777,
                'dimension' => 'ROWS',
                'startIndex' => 1,
                'endIndex' => 2,
            ]);

        expect(kb_request(2)['method'])->toBe('PUT')
            ->and(kb_request(2)['url'])->toContain("'Zamówienia'!A2:H2")
            ->and(kb_request(2)['url'])->toContain('valueInputOption=USER_ENTERED')
            ->and(kb_request(2)['body']['values'][0])->toBe(OrderSheetsSync::row($order, 'processing'));

        expect($order->get_meta(OrderSheetsSync::sentMetaKey('processing')))->not->toBe('');
    });

    it('writes over the row an order already has, leaving the rows around it', function () {
        kb_with_sheet();
        kb_order(['status' => 'completed']);
        kb_test_http([kb_ids([11130, 11125, 11120])]);

        OrderSheetsSync::handleTask(11125, 'completed');

        expect($GLOBALS['kb_test']['requests'])->toHaveCount(2);
        expect(kb_request(1)['method'])->toBe('PUT')
            ->and(kb_request(1)['url'])->toContain("'Zamówienia'!A3:H3")
            ->and(kb_request(1)['body']['values'][0][7])->toBe('completed');
    });

    it('never writes past column H, where the people working the sheet type', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_ids(['11125'])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect(kb_request(1)['body']['values'][0])->toHaveCount(8)
            ->and(kb_request(1)['body']['range'])->toBe("'Zamówienia'!A2:H2");
    });

    it('deletes the row of a cancelled order', function () {
        kb_with_sheet();
        $order = kb_order(['status' => 'cancelled']);
        kb_test_http([kb_ids(['11130', '11125'])]);

        OrderSheetsSync::handleTask(11125, 'cancelled');

        expect($GLOBALS['kb_test']['requests'])->toHaveCount(2);
        expect(kb_request(1)['body']['requests'][0]['deleteDimension']['range'])->toBe([
            'sheetId' => 777,
            'dimension' => 'ROWS',
            'startIndex' => 2,
            'endIndex' => 3,
        ]);
        expect($order->get_meta(OrderSheetsSync::sentMetaKey('cancelled')))->not->toBe('');
    });

    it('writes nothing for a cancelled order that never had a row', function () {
        kb_with_sheet();
        $order = kb_order(['status' => 'cancelled']);
        kb_test_http([kb_ids(['11130'])]);

        OrderSheetsSync::handleTask(11125, 'cancelled');

        expect($GLOBALS['kb_test']['requests'])->toHaveCount(1);
        expect($order->get_meta(OrderSheetsSync::sentMetaKey('cancelled')))->not->toBe('');
    });

    it('fills an empty top row instead of opening a second one above it', function () {
        // What an insert leaves behind when its write fails.
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_ids(['', '11120']), kb_test_json([])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toHaveCount(3);
        expect(kb_request(1)['url'])->toContain("'Zamówienia'!2:2");
        expect(kb_request(2)['method'])->toBe('PUT')
            ->and(kb_request(2)['url'])->toContain('A2:H2');
    });

    it('inserts above a top row that has no ID but holds a hand-typed order', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_ids(['', '11120']), kb_test_json(['values' => [['', '2026-10-03', '', 'Telefon']]])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect(kb_request(2)['body']['requests'][0])->toHaveKey('insertDimension');
        expect(kb_request(3)['url'])->toContain('A2:H2');
    });

    it('writes the first order into an empty sheet without inserting', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_ids([]), kb_test_json([])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toHaveCount(3)
            ->and(kb_request(2)['method'])->toBe('PUT');
    });

    it('looks the tab id up by name when it is not cached', function () {
        kb_with_sheet();
        unset($GLOBALS['kb_test']['transients']['kb_sheets_gid_' . md5(KB_SPREADSHEET . "\0Zamówienia")]);
        kb_order();
        kb_test_http([
            kb_ids(['11120']),
            kb_test_json(['sheets' => [
                ['properties' => ['sheetId' => 0, 'title' => 'Logs']],
                ['properties' => ['sheetId' => 41, 'title' => 'Zamówienia']],
            ]]),
        ]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect(kb_request(1)['url'])->toContain('fields=sheets.properties')
            ->and(kb_request(2)['body']['requests'][0]['insertDimension']['range']['sheetId'])->toBe(41);
    });

    it('retries rather than guessing when the tab is missing', function () {
        kb_with_sheet();
        unset($GLOBALS['kb_test']['transients']['kb_sheets_gid_' . md5(KB_SPREADSHEET . "\0Zamówienia")]);
        kb_order();
        kb_test_http([kb_ids(['11120']), kb_test_json(['sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Logs']]]])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['scheduled'])->toHaveCount(1);
    });
});

describe('the access token', function () {
    it('signs a JWT for the service account and caches what Google returns', function () {
        kb_with_sheet(cached: false);
        kb_order();
        kb_test_http([kb_test_json(['access_token' => 'fresh-token', 'expires_in' => 3599]), kb_ids(['11125'])]);

        OrderSheetsSync::handleTask(11125, 'processing');

        $tokenRequest = $GLOBALS['kb_test']['requests'][0];
        expect($tokenRequest['url'])->toBe(GoogleSheetsClient::TOKEN_URI)
            ->and($tokenRequest['args']['body']['grant_type'])->toBe('urn:ietf:params:oauth:grant-type:jwt-bearer');

        [$header, $claims, $signature] = explode('.', $tokenRequest['args']['body']['assertion']);
        $decode = fn(string $part): string => base64_decode(strtr($part, '-_', '+/'));

        expect(openssl_verify("$header.$claims", $decode($signature), kb_key()['public'], OPENSSL_ALGO_SHA256))->toBe(1);
        expect(json_decode($decode($claims), true))
            ->toMatchArray(['iss' => KB_SA_EMAIL, 'scope' => GoogleSheetsClient::SCOPE, 'aud' => GoogleSheetsClient::TOKEN_URI]);

        expect($GLOBALS['kb_test']['requests'][1]['args']['headers']['Authorization'])->toBe('Bearer fresh-token');
        expect($GLOBALS['kb_test']['transients']['kb_sheets_token_' . md5(KB_SA_EMAIL)])->toBe('fresh-token');
    });

    it('forgets a token the API refuses, so the retry signs a new one', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_test_json(['error' => ['message' => 'Invalid Credentials']], 401)]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['transients'])->not->toHaveKey('kb_sheets_token_' . md5(KB_SA_EMAIL));
        expect($GLOBALS['kb_test']['scheduled'])->toHaveCount(1);
    });
});

describe('failures', function () {
    it('retries a 5xx instead of dropping the row', function () {
        kb_with_sheet();
        $order = kb_order();
        kb_test_http([kb_test_json([], 500)]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['scheduled'])->toHaveCount(1);
        expect($GLOBALS['kb_test']['scheduled'][0]['args'])->toBe([11125, 'processing', 2]);
        expect($order->get_meta(OrderSheetsSync::sentMetaKey('processing')))->toBe('');
    });

    it('retries a transport error', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([new WP_Error('cURL error 28')]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['scheduled'])->toHaveCount(1);
    });

    it('widens the gap between attempts', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_test_json([], 500), kb_test_json([], 500)]);

        OrderSheetsSync::handleTask(11125, 'processing', 1);
        $first = $GLOBALS['kb_test']['scheduled'][0]['timestamp'];

        OrderSheetsSync::handleTask(11125, 'processing', 2);
        $second = $GLOBALS['kb_test']['scheduled'][1]['timestamp'];

        expect($second)->toBeGreaterThan($first);
    });

    it('notes the failure on the order once the attempts are spent', function () {
        kb_with_sheet();
        $order = kb_order();
        kb_test_http([kb_test_json(['error' => ['message' => 'The caller does not have permission']], 403)]);

        OrderSheetsSync::handleTask(11125, 'processing', OrderSheetsSync::MAX_ATTEMPTS);

        expect($GLOBALS['kb_test']['scheduled'])->toBeEmpty();
        expect($order->notes)->toHaveCount(1);
        expect($order->notes[0])->toContain('processing')
            ->and($order->notes[0])->toContain('The caller does not have permission');
    });

    it('does nothing when the order is gone', function () {
        kb_with_sheet();

        OrderSheetsSync::handleTask(999, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
    });

    it('skips an order that has left the status since the job was filed', function () {
        kb_with_sheet();
        kb_order(['status' => 'cancelled']);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
    });
});

describe('one write at a time', function () {
    it('waits its turn, without spending an attempt, while another job writes', function () {
        kb_with_sheet();
        kb_order();
        $GLOBALS['kb_test']['lock_free'] = false;

        OrderSheetsSync::handleTask(11125, 'processing', 2);

        expect($GLOBALS['kb_test']['requests'])->toBeEmpty();
        expect($GLOBALS['kb_test']['scheduled'][0]['args'])->toBe([11125, 'processing', 2]);
    });

    it('lets go of the lock after a failure too', function () {
        kb_with_sheet();
        kb_order();
        kb_test_http([kb_test_json([], 500)]);

        OrderSheetsSync::handleTask(11125, 'processing');

        expect(end($GLOBALS['kb_test']['locks']))->toContain('RELEASE_LOCK');
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

describe('A1 notation', function () {
    it('quotes the tab and doubles an apostrophe in its name', function () {
        expect(GoogleSheetsClient::a1("Zamówienia", 'A2:A'))->toBe("'Zamówienia'!A2:A")
            ->and(GoogleSheetsClient::a1("Kwiaty's", 'A1'))->toBe("'Kwiaty''s'!A1");
    });

    it('names columns the way the sheet does', function () {
        expect(GoogleSheetsClient::column(1))->toBe('A')
            ->and(GoogleSheetsClient::column(8))->toBe('H')
            ->and(GoogleSheetsClient::column(27))->toBe('AA');
    });
});
