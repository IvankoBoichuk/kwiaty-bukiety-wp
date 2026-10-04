<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

/**
 * Tools → Hostinger Redirects: what is mirrored, what is not, and why.
 *
 * The module runs on its own, so this page exists for the two questions it
 * cannot answer by itself -- "is it actually on?" and "why is this redirect
 * not on the server?" -- plus the three buttons: a dry run, a sync, and the
 * confirmation a held-back batch of deletions needs.
 */
final class AdminPage
{
    public const PAGE_SLUG = 'kb-hostinger-redirects';

    private const ACTION = 'kb_hostinger_redirects_run';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'registerPage']);
        add_action('admin_post_' . self::ACTION, [self::class, 'handlePost']);
    }

    public static function registerPage(): void
    {
        add_management_page(
            'Hostinger Redirects',
            'Hostinger Redirects',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render'],
        );
    }

    /**
     * The buttons run the sync in the request rather than queueing it: the
     * person pressing one is waiting for the answer, and the write cap keeps
     * the request bounded.
     */
    public static function handlePost(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.', '', ['response' => 403]);
        }

        check_admin_referer(self::ACTION);

        $mode = isset($_POST['mode']) ? sanitize_key((string) $_POST['mode']) : 'dry-run';

        $report = match ($mode) {
            'sync' => Sync::run(false, false),
            'force' => Sync::run(false, true),
            'purge' => Sync::purge(),
            default => Sync::run(true, false),
        };

        // A dry run leaves no stored report, so it is carried to the page in a
        // transient rather than the option.
        if ($mode === 'dry-run') {
            set_transient(self::PAGE_SLUG . '-preview', $report, 300);
        }

        wp_safe_redirect(add_query_arg(
            ['page' => self::PAGE_SLUG, 'kb-ran' => $mode],
            admin_url('tools.php'),
        ));

        exit;
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $preview = get_transient(self::PAGE_SLUG . '-preview');
        $report = is_array($preview) && $preview !== [] ? $preview : Sync::lastReport();
        delete_transient(self::PAGE_SLUG . '-preview');

        $enabled = Config::isEnabled();
        $ledger = Sync::ledger();

        echo '<div class="wrap">';
        echo '<h1>Hostinger Redirects</h1>';

        echo '<p>Redirection stays the place redirects are written. The plain, enabled 301s among them are '
            . 'mirrored to Hostinger, which answers them before PHP starts; everything else keeps working in PHP.</p>';

        echo '<h2>Configuration</h2><table class="widefat striped" style="max-width:48rem"><tbody>';
        self::row('Status', $enabled ? 'on' : 'off — ' . Sync::disabledReason());
        self::row('Domain', Config::domain() ?: '—');
        self::row('Hosting username', Config::username() ?: 'not set (looked up from the API)');
        self::row('API token', Config::token() === '' ? 'missing' : 'set');
        self::row('Mirrored status codes', implode(', ', Config::allowedCodes()));
        self::row('Mirrored right now', (string) count($ledger));
        echo '</tbody></table>';

        self::renderReport($report);

        echo '<h2>Run</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        echo '<p>';
        echo '<button class="button" name="mode" value="dry-run">Preview changes</button> ';
        echo '<button class="button button-primary" name="mode" value="sync">Sync now</button> ';
        echo '<button class="button" name="mode" value="force" onclick="return confirm(\'Apply the held-back deletions too?\')">Sync including held deletions</button> ';
        echo '<button class="button button-link-delete" name="mode" value="purge" onclick="return confirm(\'Remove every redirect this module created from Hostinger?\')">Remove all mirrored redirects</button>';
        echo '</p>';
        echo '</form>';

        if ($ledger !== []) {
            echo '<h2>Mirrored redirects</h2>';
            echo '<table class="widefat striped"><thead><tr><th>From</th><th>To</th></tr></thead><tbody>';

            foreach (array_slice($ledger, 0, 200, true) as $from => $to) {
                echo '<tr><td><code>' . esc_html((string) $from) . '</code></td><td><code>' . esc_html($to) . '</code></td></tr>';
            }

            echo '</tbody></table>';

            if (count($ledger) > 200) {
                echo '<p>' . esc_html(sprintf('%d more not shown.', count($ledger) - 200)) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function renderReport(array $report): void
    {
        if ($report === []) {
            echo '<h2>Last run</h2><p>The mirror has not run yet.</p>';

            return;
        }

        echo '<h2>Last run</h2><table class="widefat striped" style="max-width:48rem"><tbody>';
        self::row('When', (string) ($report['time'] ?? '—'));
        self::row('Result', (string) ($report['status'] ?? '—') . (! empty($report['dry_run']) ? ' (preview)' : ''));

        if (! empty($report['message'])) {
            self::row('Note', (string) $report['message']);
        }

        self::row('Rows in Redirection', (string) ($report['rows'] ?? 0));
        self::row('Eligible for the server', (string) ($report['eligible'] ?? 0));
        self::row('Held by Hostinger', (string) ($report['remote'] ?? 0));
        self::row('Created', (string) count((array) ($report['created'] ?? [])));
        self::row('Deleted', (string) count((array) ($report['deleted'] ?? [])));
        echo '</tbody></table>';

        self::renderFailures((array) ($report['failed'] ?? []));
        self::renderHeldDeletes((array) ($report['held_deletes'] ?? []));
        self::renderConflicts((array) ($report['conflicts'] ?? []));
        self::renderSkipped((array) ($report['skipped'] ?? []));
    }

    /**
     * @param  array<int, mixed>  $failures
     */
    private static function renderFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        echo '<h3>Failed writes</h3><ul>';

        foreach ($failures as $failure) {
            $failure = (array) $failure;
            echo '<li><code>' . esc_html((string) ($failure['from'] ?? '')) . '</code> — '
                . esc_html((string) ($failure['action'] ?? '')) . ': '
                . esc_html((string) ($failure['error'] ?? '')) . '</li>';
        }

        echo '</ul>';
    }

    /**
     * @param  array<int, mixed>  $held
     */
    private static function renderHeldDeletes(array $held): void
    {
        if ($held === []) {
            return;
        }

        echo '<h3>Deletions held back</h3>';
        echo '<p>More deletions than the safety cap allows. Check that Redirection still holds the rows you expect, '
            . 'then use “Sync including held deletions”.</p><ul>';

        foreach ($held as $from) {
            echo '<li><code>' . esc_html((string) $from) . '</code></li>';
        }

        echo '</ul>';
    }

    /**
     * @param  array<int, mixed>  $conflicts
     */
    private static function renderConflicts(array $conflicts): void
    {
        if ($conflicts === []) {
            return;
        }

        echo '<h3>Conflicts with hand-made redirects</h3>';
        echo '<p>Hostinger already redirects these sources somewhere else, and they were not created here, so they '
            . 'were left alone. Redirection still answers them in PHP.</p><ul>';

        foreach ($conflicts as $conflict) {
            $conflict = (array) $conflict;
            echo '<li><code>' . esc_html((string) ($conflict['from'] ?? '')) . '</code>: hPanel sends it to <code>'
                . esc_html((string) ($conflict['theirs'] ?? '')) . '</code>, Redirection to <code>'
                . esc_html((string) ($conflict['ours'] ?? '')) . '</code></li>';
        }

        echo '</ul>';
    }

    /**
     * @param  array<int, mixed>  $skipped
     */
    private static function renderSkipped(array $skipped): void
    {
        if ($skipped === []) {
            return;
        }

        echo '<h3>Left to PHP</h3><table class="widefat striped"><thead><tr><th>Source</th><th>Reason</th></tr></thead><tbody>';

        foreach ($skipped as $row) {
            $row = (array) $row;
            echo '<tr><td><code>' . esc_html((string) ($row['url'] ?? '')) . '</code></td><td>'
                . esc_html((string) ($row['reason'] ?? '')) . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private static function row(string $label, string $value): void
    {
        echo '<tr><th scope="row" style="width:16rem">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
}
