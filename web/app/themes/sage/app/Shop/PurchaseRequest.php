<?php

declare(strict_types=1);

namespace App\Shop;

use WP_REST_Request;

/**
 * The delivery fields one add-to-cart request carries, whichever way it came in.
 *
 * Three callers want the same values and none of them can read another's
 * source. The classic form posts snake_case fields in $_POST; the Store API
 * sends camelCase JSON, which PHP never unpacks into $_POST or $_REQUEST; and
 * `woocommerce_add_to_cart_validation` is handed no request object at all, so a
 * filter on it has to read the body itself.
 *
 * That last gap is why this class exists: LeadTimeRules and PostalDelivery read
 * $_REQUEST['delivery_date'], which is always empty under the Store API, so
 * every funeral product was unaddable no matter what date the customer picked.
 */
final class PurchaseRequest
{
    /**
     * Request field => the camelCase alias the JS sends for it.
     *
     * @var array<string, string>
     */
    public const FIELD_ALIASES = [
        'delivery_date' => 'deliveryDate',
        'delivery_time' => 'deliveryTime',
        'delivery_location' => 'deliveryLocation',
        'delivery_type' => 'deliveryType',
        'deceased_full_name' => 'deceasedFullName',
        'card_message' => 'cardMessage',
        'addition_ids' => 'additionIds',
    ];

    /**
     * The decoded JSON body, parsed at most once per request.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $jsonBody = null;

    /**
     * @return array{
     *     delivery_date: string,
     *     delivery_time: string,
     *     delivery_location: string,
     *     delivery_type: string,
     *     deceased_full_name: string,
     *     card_message: string,
     *     addition_ids: array<int, int>
     * }
     */
    public static function payload(WP_REST_Request|array|null $request = null): array
    {
        $source = self::source($request);

        return [
            'delivery_date' => self::text($source, 'delivery_date'),
            'delivery_time' => self::text($source, 'delivery_time'),
            'delivery_location' => self::text($source, 'delivery_location'),
            'delivery_type' => self::text($source, 'delivery_type'),
            'deceased_full_name' => self::text($source, 'deceased_full_name'),
            'card_message' => sanitize_textarea_field(
                (string) self::raw($source, 'card_message'),
            ),
            'addition_ids' => array_values(
                array_filter(
                    array_map('absint', (array) self::raw($source, 'addition_ids')),
                ),
            ),
        ];
    }

    /**
     * `Y-m-d`, or '' when the request carries no date.
     */
    public static function deliveryDate(WP_REST_Request|array|null $request = null): string
    {
        return self::payload($request)['delivery_date'];
    }

    /**
     * A `HH-HH` slot or a `HH:MM` time, or '' when the request carries neither.
     */
    public static function deliveryTime(WP_REST_Request|array|null $request = null): string
    {
        return self::payload($request)['delivery_time'];
    }

    /**
     * Forgets the parsed body. Only the test suite needs this.
     */
    public static function flush(): void
    {
        self::$jsonBody = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function source(WP_REST_Request|array|null $request): array
    {
        if ($request instanceof WP_REST_Request) {
            return $request->get_params();
        }

        if (is_array($request)) {
            return $request;
        }

        if ($_POST !== []) {
            return (array) wp_unslash($_POST);
        }

        $jsonBody = self::jsonBody();

        if ($jsonBody !== []) {
            return $jsonBody;
        }

        // The classic form can also arrive as a GET (?add-to-cart=123), which
        // is what $_REQUEST used to cover for the two validation rules.
        return (array) wp_unslash($_GET);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function jsonBody(): array
    {
        if (self::$jsonBody !== null) {
            return self::$jsonBody;
        }

        $rawBody = file_get_contents('php://input');
        $decoded = is_string($rawBody) ? json_decode($rawBody, true) : null;

        return self::$jsonBody = is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    protected static function text(array $source, string $field): string
    {
        return sanitize_text_field((string) self::raw($source, $field));
    }

    /**
     * The value under either spelling of a field: `delivery_date` first, then
     * the camelCase alias the Store API payload uses.
     *
     * @param  array<string, mixed>  $source
     */
    protected static function raw(array $source, string $field): mixed
    {
        $alias = self::FIELD_ALIASES[$field] ?? $field;

        return $source[$field] ?? ($source[$alias] ?? ($field === 'addition_ids' ? [] : ''));
    }
}
