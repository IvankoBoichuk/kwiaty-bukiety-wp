<?php

/**
 * Plugin Name: Mail Guard
 * Description: Outside production, every e-mail goes to one safe address instead of its real recipients.
 */

declare(strict_types=1);

if (wp_get_environment_type() === 'production') {
    return;
}

add_filter('wp_mail', function (array $atts): array {
    $redirectTo = defined('MAIL_REDIRECT_TO') && is_email(MAIL_REDIRECT_TO)
        ? MAIL_REDIRECT_TO
        : (string) get_option('admin_email');

    $original = array_merge(
        (array) $atts['to'],
        kb_mail_guard_take_header($atts, 'cc'),
        kb_mail_guard_take_header($atts, 'bcc'),
    );
    $original = array_filter(array_map('trim', $original));

    $atts['to'] = $redirectTo;
    $atts['subject'] = sprintf(
        '[%s → %s] %s',
        strtoupper(wp_get_environment_type()),
        implode(', ', $original),
        $atts['subject'],
    );

    // Real recipients stay visible in the headers for debugging.
    $atts['headers'][] = 'X-Original-To: ' . implode(', ', $original);

    return $atts;
}, PHP_INT_MAX);

/**
 * Removes Cc/Bcc headers from the message and returns their addresses,
 * so a copy never slips past the redirect.
 *
 * @param array<string, mixed> $atts
 * @return array<int, string>
 */
function kb_mail_guard_take_header(array &$atts, string $name): array
{
    $headers = $atts['headers'] ?? [];
    $headers = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers));
    $found = [];

    foreach ($headers as $index => $header) {
        if (stripos(ltrim((string) $header), $name . ':') === 0) {
            $found = array_merge($found, explode(',', substr(trim((string) $header), strlen($name) + 1)));
            unset($headers[$index]);
        }
    }

    $atts['headers'] = array_values($headers);

    return $found;
}
