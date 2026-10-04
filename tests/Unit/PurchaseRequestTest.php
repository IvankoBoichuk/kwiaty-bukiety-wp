<?php

declare(strict_types=1);

use App\Shop\PurchaseRequest;

/**
 * The Store API sends camelCase JSON, which never reaches $_POST or $_REQUEST.
 * Reading only the snake_case form is what made every funeral product
 * unaddable: the lead-time rule saw no date and refused the request whatever
 * the customer picked.
 */
it('reads the camelCase fields the Store API sends', function () {
    $payload = PurchaseRequest::payload([
        'id' => 10369,
        'quantity' => 1,
        'deliveryDate' => '2026-10-06',
        'deliveryTime' => '08-12',
        'deliveryLocation' => 'warszawa',
        'deliveryType' => 'kurier',
        'deceasedFullName' => 'Jan Kowalski',
        'cardMessage' => 'Pamiętamy',
        'additionIds' => [11, 12],
    ]);

    expect($payload['delivery_date'])->toBe('2026-10-06');
    expect($payload['delivery_time'])->toBe('08-12');
    expect($payload['delivery_location'])->toBe('warszawa');
    expect($payload['delivery_type'])->toBe('kurier');
    expect($payload['deceased_full_name'])->toBe('Jan Kowalski');
    expect($payload['card_message'])->toBe('Pamiętamy');
    expect($payload['addition_ids'])->toBe([11, 12]);
});

it('still reads the snake_case fields the classic form posts', function () {
    $payload = PurchaseRequest::payload([
        'delivery_date' => '2026-10-06',
        'delivery_time' => '12-15',
    ]);

    expect($payload['delivery_date'])->toBe('2026-10-06');
    expect($payload['delivery_time'])->toBe('12-15');
});

it('prefers the snake_case spelling when a request carries both', function () {
    $payload = PurchaseRequest::payload([
        'delivery_date' => '2026-10-06',
        'deliveryDate' => '2026-10-07',
    ]);

    expect($payload['delivery_date'])->toBe('2026-10-06');
});

it('reports empty fields rather than null', function () {
    $payload = PurchaseRequest::payload([]);

    expect($payload['delivery_date'])->toBe('');
    expect($payload['delivery_time'])->toBe('');
    expect($payload['addition_ids'])->toBe([]);
});

it('drops addition ids that are not products', function () {
    $payload = PurchaseRequest::payload(['additionIds' => ['11', 0, '', 'abc', 12]]);

    expect($payload['addition_ids'])->toBe([11, 12]);
});

it('exposes the date and time on their own', function () {
    $request = ['deliveryDate' => '2026-10-06', 'deliveryTime' => '08:30'];

    expect(PurchaseRequest::deliveryDate($request))->toBe('2026-10-06');
    expect(PurchaseRequest::deliveryTime($request))->toBe('08:30');
});
