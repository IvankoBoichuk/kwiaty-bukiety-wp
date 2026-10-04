<?php

declare(strict_types=1);

namespace App\Shop;

use WC_Order;
use WC_Order_Item_Product;

/**
 * The view of an order a fulfilment partner needs.
 *
 * Shared by the partner e-mail (production snippet #71) and the Make.com
 * webhook (#79), which both fired on `processing` and each assembled their own
 * half of the same data.
 */
final class PartnerOrder
{
    /**
     * The partner is paid this share of the line total.
     */
    public const PARTNER_SHARE = 0.67;

    /**
     * Partner prices are rounded to the nearest multiple of this.
     */
    public const PRICE_STEP = 5;

    /**
     * Delivery date and time were written under a different key by every
     * generation of the checkout; the current one uses the first of each.
     *
     * @var array<int, string>
     */
    public const DATE_KEYS = [
        'delivery_date', '_delivery_date', 'delivery-date',
        'datadostav', 'data_dostawy', 'Data dostawy', 'Date dostawy',
    ];

    /**
     * @var array<int, string>
     */
    public const TIME_KEYS = [
        'delivery_time', '_delivery_time', 'delivery-time',
        'godzina_dostawy', 'Godzina dostawy',
    ];

    public function __construct(public readonly WC_Order $order) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->order->get_id(),
            'order_number' => $this->order->get_order_number(),
            'recipient' => $this->recipientName(),
            'phone' => $this->recipientPhone(),
            'delivery' => $this->address() + [
                'address_full' => $this->fullAddress(),
                'phone' => $this->recipientPhone(),
                'date' => $this->deliveryDate(),
                'time' => $this->deliveryTime(),
            ],
            'customer_note' => $this->order->get_customer_note(),
            'items' => $this->items(),
            'partner_total' => $this->partnerTotal(),
            'currency' => $this->order->get_currency(),
        ];
    }

    public function recipientName(): string
    {
        $name = trim($this->order->get_shipping_first_name() . ' ' . $this->order->get_shipping_last_name());

        if ($name !== '') {
            return $name;
        }

        return trim($this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name());
    }

    public function recipientPhone(): string
    {
        $phone = (string) $this->order->get_shipping_phone();

        return $phone !== '' ? $phone : (string) $this->order->get_billing_phone();
    }

    /**
     * Falls back to the billing address when no separate shipping one exists.
     *
     * @return array{street: string, apartment: string, postcode: string, city: string, country: string}
     */
    public function address(): array
    {
        $street = (string) $this->order->get_shipping_address_1();

        if ($street !== '') {
            return [
                'street' => $street,
                'apartment' => (string) $this->order->get_shipping_address_2(),
                'postcode' => (string) $this->order->get_shipping_postcode(),
                'city' => (string) $this->order->get_shipping_city(),
                'country' => (string) $this->order->get_shipping_country(),
            ];
        }

        return [
            'street' => (string) $this->order->get_billing_address_1(),
            'apartment' => (string) $this->order->get_billing_address_2(),
            'postcode' => (string) $this->order->get_billing_postcode(),
            'city' => (string) $this->order->get_billing_city(),
            'country' => (string) $this->order->get_billing_country(),
        ];
    }

    public function fullAddress(): string
    {
        $address = $this->address();
        $street = $address['street'];

        if ($address['apartment'] !== '') {
            $street .= ', ' . $address['apartment'];
        }

        return trim($street . ', ' . $address['postcode'] . ' ' . $address['city'], " \t\n\r\0\x0B,");
    }

    public function deliveryDate(): string
    {
        return $this->firstMeta(self::DATE_KEYS);
    }

    public function deliveryTime(): string
    {
        return $this->firstMeta(self::TIME_KEYS);
    }

    /**
     * @return array<int, array{name: string, quantity: int, flowers: string, card: string, notes: string, partner_price: float}>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->order->get_items('line_item') as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $extras = $this->itemExtras($item);

            $items[] = [
                'name' => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'flowers' => $extras['flowers'],
                'card' => $extras['card'],
                'notes' => $extras['notes'],
                'partner_price' => self::partnerPrice((float) $item->get_total()),
            ];
        }

        return $items;
    }

    public function partnerTotal(): float
    {
        return array_sum(array_column($this->items(), 'partner_price'));
    }

    public static function partnerPrice(float $lineTotal): float
    {
        return round($lineTotal * self::PARTNER_SHARE / self::PRICE_STEP) * self::PRICE_STEP;
    }

    /**
     * Pulls the flower count, card text and notes out of the line item meta,
     * which is keyed by translated labels rather than stable names.
     *
     * @return array{flowers: string, card: string, notes: string}
     */
    protected function itemExtras(WC_Order_Item_Product $item): array
    {
        $extras = ['flowers' => '', 'card' => '', 'notes' => ''];

        foreach ($item->get_meta_data() as $meta) {
            $key = trim((string) $meta->key);
            $value = $meta->value;

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim(wp_strip_all_tags((string) $value));

            if ($value === '') {
                continue;
            }

            if (stripos($key, 'rozmiar') !== false || stripos($key, 'bukiet') !== false) {
                $count = self::flowerCount($value);

                if ($count !== '') {
                    $extras['flowers'] = $count;
                }
            }

            if (stripos($key, 'bilec') !== false || stripos($key, 'tresc') !== false || stripos($key, 'treść') !== false) {
                $extras['card'] = $value;
            }

            if (stripos($key, 'uwag') !== false || stripos($key, 'comment') !== false || stripos($key, 'komentar') !== false) {
                $extras['notes'] = $value;
            }
        }

        if ($extras['flowers'] === '') {
            $product = $item->get_product();

            if ($product) {
                $extras['flowers'] = self::flowerCount((string) $product->get_attribute('pa_rozmiary-bukietow'));
            }
        }

        return $extras;
    }

    /**
     * "bukiet-sredni-11-13-kwiatow" => "11–13 kwiatów".
     */
    public static function flowerCount(string $value): string
    {
        $value = wp_strip_all_tags($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/(\d+)[^\d]+(\d+)[^\d]*(kwiat|flower)/iu', $value, $matches) === 1) {
            return $matches[1] . '–' . $matches[2] . ' kwiatów';
        }

        if (preg_match('/(\d+)[^\d]*(kwiat|flower)/iu', $value, $matches) === 1) {
            return $matches[1] . ' kwiatów';
        }

        return '';
    }

    /**
     * Order meta first, then the line items.
     *
     * The current checkout writes the delivery date and hour onto the order.
     * Orders carried over from production carry them on the line item instead,
     * under the label the old checkout printed -- including `Date dostawy`,
     * with the typo -- so an order-only lookup finds nothing for those.
     *
     * @param  array<int, string>  $keys
     */
    protected function firstMeta(array $keys): string
    {
        foreach ($keys as $key) {
            $value = self::scalarMeta($this->order->get_meta($key, true));

            if ($value !== '') {
                return sanitize_text_field($value);
            }
        }

        foreach ($this->order->get_items('line_item') as $item) {
            foreach ($keys as $key) {
                $value = self::scalarMeta($item->get_meta($key, true));

                if ($value !== '') {
                    return sanitize_text_field($value);
                }
            }
        }

        return '';
    }

    /**
     * Meta can come back as an array or an object; neither is a date.
     */
    protected static function scalarMeta(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
