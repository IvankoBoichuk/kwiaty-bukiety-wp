<?php

declare(strict_types=1);

/**
 * The few WordPress and WooCommerce symbols the unit tests touch.
 *
 * phpunit.xml.dist boots `vendor/autoload.php` and nothing else, so the suite
 * runs without WordPress -- which is why the existing tests only cover pure
 * static helpers. The order sync is worth testing past that line: its status
 * filter, its idempotency and its retry decision are the parts most likely to
 * break, and all three sit behind a handful of WP calls.
 *
 * These are deliberately dumb: enough shape for the code under test, with the
 * calls recorded in $GLOBALS['kb_test'] so a test can assert on them. Every
 * definition is guarded, so loading a real WordPress alongside them is safe.
 */
if (! isset($GLOBALS['kb_test'])) {
    $GLOBALS['kb_test'] = [];
}

/**
 * Resets the recorded state. Call it at the top of every test.
 *
 * @param  array<string, mixed>  $options
 */
function kb_test_reset(array $options = []): void
{
    $GLOBALS['kb_test'] = [
        'options' => $options,
        'orders' => [],
        'async' => [],
        'scheduled' => [],
        'http' => [],
        'requests' => [],
        'can' => true,
    ];
}

/**
 * Queues the responses wp_remote_post() hands back, in order.
 *
 * @param  array<int, mixed>  $responses
 */
function kb_test_http(array $responses): void
{
    $GLOBALS['kb_test']['http'] = $responses;
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(private string $message = 'error') {}

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (! class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product
    {
        /**
         * @param  array<string, string>  $meta
         */
        public function __construct(
            private string $name = '',
            private int $quantity = 1,
            private array $meta = [],
        ) {}

        public function get_name(): string
        {
            return $this->name;
        }

        public function get_quantity(): int
        {
            return $this->quantity;
        }

        public function get_meta(string $key, bool $single = true): string
        {
            return $this->meta[$key] ?? '';
        }
    }
}

if (! class_exists('WC_Order')) {
    class WC_Order
    {
        /** @var array<int, string> */
        public array $notes = [];

        public bool $saved = false;

        /**
         * @param  array<string, string>  $meta
         * @param  array<int, WC_Order_Item_Product>  $items
         */
        public function __construct(
            private int $id = 1,
            private string $status = 'processing',
            private array $meta = [],
            private array $items = [],
            private string $total = '0.00',
            private string $city = '',
            private string $postcode = '',
        ) {}

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_status(): string
        {
            return $this->status;
        }

        public function set_status(string $status): void
        {
            $this->status = $status;
        }

        public function get_total(): string
        {
            return $this->total;
        }

        public function get_shipping_city(): string
        {
            return $this->city;
        }

        public function get_shipping_postcode(): string
        {
            return $this->postcode;
        }

        /**
         * @return array<int, WC_Order_Item_Product>
         */
        public function get_items(string $type = 'line_item'): array
        {
            return $this->items;
        }

        public function get_meta(string $key, bool $single = true): string
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, mixed $value): void
        {
            $this->meta[$key] = (string) $value;
        }

        public function delete_meta_data(string $key): void
        {
            unset($this->meta[$key]);
        }

        public function add_order_note(string $note): void
        {
            $this->notes[] = $note;
        }

        public function save(): void
        {
            $this->saved = true;
        }
    }
}

if (! function_exists('wc_get_order')) {
    function wc_get_order(mixed $id): mixed
    {
        return $GLOBALS['kb_test']['orders'][(int) $id] ?? false;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['kb_test']['options'][$name] ?? $default;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return (bool) ($GLOBALS['kb_test']['can'] ?? true);
    }
}

if (! function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string
    {
        return '2026-10-02 12:00:00';
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0): string
    {
        return (string) json_encode($value, $flags);
    }
}

if (! function_exists('as_enqueue_async_action')) {
    function as_enqueue_async_action(string $hook, array $args = [], string $group = ''): int
    {
        $GLOBALS['kb_test']['async'][] = ['hook' => $hook, 'args' => $args, 'group' => $group];

        return count($GLOBALS['kb_test']['async']);
    }
}

if (! function_exists('as_schedule_single_action')) {
    function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): int
    {
        $GLOBALS['kb_test']['scheduled'][] = [
            'timestamp' => $timestamp,
            'hook' => $hook,
            'args' => $args,
            'group' => $group,
        ];

        return count($GLOBALS['kb_test']['scheduled']);
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): mixed
    {
        $GLOBALS['kb_test']['requests'][] = ['url' => $url, 'args' => $args];

        return array_shift($GLOBALS['kb_test']['http']) ?? ['response' => ['code' => 200]];
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(mixed $response): int
    {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
    }
}
