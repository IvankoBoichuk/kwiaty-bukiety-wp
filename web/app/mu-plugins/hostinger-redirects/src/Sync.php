<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

use WP_Error;
use WP_REST_Request;

/**
 * When the mirror is reconciled, and what a reconcile does.
 *
 * There is one operation -- read both sides, diff, apply -- and four ways in:
 *
 * - Redirection's own `redirection_redirect_updated` / `_deleted` actions,
 *   when the installed version fires them.
 * - Any non-GET request to Redirection's REST namespace, which is how its
 *   admin screen writes. This is the one that does not depend on the plugin's
 *   internals, so it is what actually catches an edit, an import or a bulk
 *   delete made in wp-admin.
 * - An hourly sweep, which is the safety net for anything the two above miss
 *   (a direct SQL edit, a failed run, a request that died mid-flight).
 * - By hand, from the Tools page or `wp hostinger-redirects sync`.
 *
 * All four queue the same idempotent run, so firing it twice costs one extra
 * list request and changes nothing. That is also why the first sync needs no
 * special path: the diff against an empty server is the initial import.
 */
final class Sync
{
    /**
     * The queued, debounced run. A change schedules it a few seconds out so a
     * bulk edit of fifty rows reconciles once rather than fifty times.
     */
    public const HOOK = 'kb_hostinger_redirects_sync';

    /**
     * The hourly sweep. Separate from HOOK so a pending single run does not
     * make wp_next_scheduled() report the recurring one as already there.
     */
    public const CRON_HOOK = 'kb_hostinger_redirects_sweep';

    public const AS_GROUP = 'kb-hostinger-redirects';

    /**
     * Seconds between a change and the run it triggers.
     */
    private const DEBOUNCE = 30;

    public static function boot(): void
    {
        add_action(self::HOOK, [self::class, 'handle']);
        add_action(self::CRON_HOOK, [self::class, 'handle']);
        add_action('init', [self::class, 'onInit']);

        // Both pass arguments the queue has no use for, so they go through a
        // wrapper rather than straight to queue().
        add_action('redirection_redirect_updated', [self::class, 'onRedirectionChanged']);
        add_action('redirection_redirect_deleted', [self::class, 'onRedirectionChanged']);

        add_filter('rest_request_after_callbacks', [self::class, 'onRestRequest'], 10, 3);
    }

    /**
     * Keeps the sweep scheduled while the module is on, and takes the very
     * first run as soon as it is configured: no report stored means the mirror
     * has never been built, so the redirects already in the database are
     * imported on the next cron tick.
     */
    public static function onInit(): void
    {
        // Nothing here is a front-end concern, and the stored report is not an
        // autoloaded option, so reading it on every visitor's request would
        // buy a query for no reason.
        if (! is_admin() && ! wp_doing_cron() && ! (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        if (! Config::isEnabled()) {
            // A token that was removed, or a database copied to staging: stop
            // waking up for work this install must not do.
            wp_clear_scheduled_hook(self::CRON_HOOK);

            return;
        }

        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + self::DEBOUNCE, 'hourly', self::CRON_HOOK);
        }

        if (get_option(Config::OPTION_REPORT) === false) {
            self::queue();
        }
    }

    public static function onRedirectionChanged(mixed $unused = null): void
    {
        unset($unused);

        self::queue();
    }

    /**
     * Redirection's admin screen is a React app talking to its own REST
     * namespace, so every create, edit, import and bulk action arrives as a
     * non-GET request under /redirection/v1.
     *
     * @param  mixed  $response  Passed through untouched.
     */
    public static function onRestRequest(mixed $response, mixed $handler, mixed $request): mixed
    {
        if (! $request instanceof WP_REST_Request) {
            return $response;
        }

        if ($request->get_method() === 'GET') {
            return $response;
        }

        if (! str_starts_with($request->get_route(), '/redirection/v1')) {
            return $response;
        }

        self::queue();

        return $response;
    }

    public static function handle(): void
    {
        self::run();
    }

    /**
     * Schedules a run, unless one is already waiting.
     */
    public static function queue(int $delay = self::DEBOUNCE): void
    {
        if (! Config::isEnabled()) {
            return;
        }

        if (function_exists('as_schedule_single_action')) {
            if (function_exists('as_has_scheduled_action') && as_has_scheduled_action(self::HOOK, [], self::AS_GROUP)) {
                return;
            }

            as_schedule_single_action(time() + $delay, self::HOOK, [], self::AS_GROUP);

            return;
        }

        // wp-cron refuses a second identical event within ten minutes, which
        // is the debounce when Action Scheduler is not loaded.
        wp_schedule_single_event(time() + $delay, self::HOOK);
    }

    /**
     * Reads both sides, applies the difference, stores the report.
     *
     * @param  bool  $dryRun  Plan only: no writes, and the stored report and
     *                        ledger are left as they were.
     * @param  bool  $force  Applies deletions past the safety cap.
     * @return array<string, mixed>  The report.
     */
    public static function run(bool $dryRun = false, bool $force = false): array
    {
        $report = [
            'time' => current_time('mysql'),
            'status' => 'ok',
            'dry_run' => $dryRun,
            'rows' => 0,
            'eligible' => 0,
            'remote' => 0,
            'created' => [],
            'deleted' => [],
            'failed' => [],
            'conflicts' => [],
            'skipped' => [],
            'held_deletes' => [],
            'message' => '',
        ];

        if (! Config::isEnabled()) {
            return self::finish($report, 'disabled', self::disabledReason(), $dryRun);
        }

        if (! Source::exists()) {
            return self::finish(
                $report,
                'no-redirection-table',
                sprintf('%s does not exist; nothing to mirror.', Source::table()),
                $dryRun,
            );
        }

        $username = self::resolveUsername();

        if ($username instanceof WP_Error) {
            return self::finish($report, 'error', $username->get_error_message(), $dryRun);
        }

        $client = new Client(Config::token(), $username, Config::domain());
        $remote = $client->all();

        if ($remote instanceof WP_Error) {
            if (! $dryRun) {
                self::requeueAfterFailure($remote);
            }

            return self::finish($report, 'error', $remote->get_error_message(), $dryRun);
        }

        $desired = Source::desired();
        $ledger = self::ledger();
        $plan = Plan::build($desired['map'], $remote, $ledger);

        $report['rows'] = Source::total();
        $report['eligible'] = count($desired['map']);
        $report['remote'] = count($remote);
        $report['skipped'] = array_slice($desired['skipped'], 0, 50);
        $report['conflicts'] = $plan['conflicts'];

        $deletes = $plan['delete'];

        /**
         * The brake. A schema change in Redirection, a half-restored database
         * or a mistyped filter all look the same from here: suddenly nothing
         * is eligible, and every mirrored redirect is up for deletion. Past
         * the cap the deletions are reported instead of sent, and a human
         * says yes with --force or the Tools page button.
         */
        if (! $force && count($deletes) > Config::deleteCap()) {
            $report['held_deletes'] = $deletes;
            $report['message'] = sprintf(
                '%d deletions were held back (the cap is %d). Review them, then run with --force.',
                count($deletes),
                Config::deleteCap(),
            );
            $deletes = [];
        }

        if ($dryRun) {
            $report['deleted'] = $deletes;
            $report['created'] = $plan['create'];
            $report['status'] = 'dry-run';

            return $report;
        }

        $next = $ledger;
        $budget = Config::writesPerRun();
        $exhausted = false;
        $rateLimited = false;

        // Deletions first: a changed target is a delete and a create of the
        // same source, and the create would collide the other way round.
        foreach ($deletes as $from) {
            if ($budget <= 0) {
                $exhausted = true;

                break;
            }

            $budget--;
            $result = $client->delete($from);

            if ($result instanceof WP_Error) {
                $report['failed'][] = ['from' => $from, 'action' => 'delete', 'error' => $result->get_error_message()];
                $rateLimited = $rateLimited || $result->get_error_code() === 'kb_hostinger_rate_limited';

                if ($rateLimited) {
                    break;
                }

                continue;
            }

            unset($next[$from]);
            $report['deleted'][] = $from;
        }

        foreach ($plan['create'] as $from => $to) {
            if ($rateLimited) {
                break;
            }

            if ($budget <= 0) {
                $exhausted = true;

                break;
            }

            $budget--;
            $result = $client->create($from, $to);

            if ($result instanceof WP_Error) {
                $report['failed'][] = ['from' => $from, 'action' => 'create', 'error' => $result->get_error_message()];
                $rateLimited = $rateLimited || $result->get_error_code() === 'kb_hostinger_rate_limited';

                continue;
            }

            $next[$from] = $to;
            $report['created'][] = ['from' => $from, 'to' => $to];
        }

        // Ours and still right: keep the ledger's copy of the target in step
        // with what both sides now agree on.
        foreach ($plan['unchanged'] as $from => $to) {
            if (array_key_exists($from, $ledger)) {
                $next[$from] = $to;
            }
        }

        // Ours, unwanted, and already gone from the server -- somebody deleted
        // it in hPanel. Drop it from the ledger so it stops being counted.
        foreach ($ledger as $from => $to) {
            if (! array_key_exists($from, $desired['map']) && ! array_key_exists($from, $remote)) {
                unset($next[$from]);
            }
        }

        self::saveLedger($next);

        if ($rateLimited) {
            $report['status'] = 'rate-limited';
            $report['message'] = 'The API rate limit was hit; the rest is queued.';
            self::queue(300);
        } elseif ($exhausted) {
            $report['status'] = 'partial';
            $report['message'] = sprintf('Stopped at %d writes; the rest is queued.', Config::writesPerRun());
            self::queue(60);
        } elseif ($report['failed'] !== []) {
            $report['status'] = 'partial';
            $report['message'] = sprintf('%d of the writes failed.', count($report['failed']));
        }

        $report['created'] = array_slice($report['created'], 0, 100);
        $report['deleted'] = array_slice($report['deleted'], 0, 100);
        $report['managed'] = count($next);

        update_option(Config::OPTION_REPORT, $report, false);

        return $report;
    }

    /**
     * Removes every redirect this module created and forgets them, leaving
     * hand-made ones in place. For switching the mirror off, or for handing
     * the site to a host that is not Hostinger.
     *
     * @return array<string, mixed>
     */
    public static function purge(): array
    {
        $report = [
            'time' => current_time('mysql'),
            'status' => 'ok',
            'deleted' => [],
            'failed' => [],
            'message' => '',
        ];

        // Deliberately behind the same gate as a sync: the ledger in a copy of
        // the production database names the live site's redirects, so a purge
        // run from staging would delete them there.
        if (! Config::isEnabled()) {
            return self::finish($report, 'disabled', self::disabledReason(), false);
        }

        $username = self::resolveUsername();

        if ($username instanceof WP_Error) {
            return self::finish($report, 'error', $username->get_error_message(), false);
        }

        $client = new Client(Config::token(), $username, Config::domain());
        $ledger = self::ledger();
        $next = $ledger;

        // Reading the list first is what lets the deletes name each redirect
        // the way Hostinger spelled it. A failure here is not fatal -- the
        // deletes fall back to our own spelling.
        $remote = $client->all();

        if ($remote instanceof WP_Error) {
            $report['message'] = 'The redirects could not be listed first: ' . $remote->get_error_message();
        }

        foreach (array_keys($ledger) as $from) {
            $result = $client->delete((string) $from);

            if ($result instanceof WP_Error) {
                $report['failed'][] = ['from' => $from, 'action' => 'delete', 'error' => $result->get_error_message()];

                continue;
            }

            unset($next[$from]);
            $report['deleted'][] = $from;
        }

        self::saveLedger($next);

        if ($report['failed'] !== []) {
            $report['status'] = 'partial';
        }

        update_option(Config::OPTION_REPORT, $report, false);

        return $report;
    }

    /**
     * @return array<string, string>
     */
    public static function ledger(): array
    {
        $ledger = get_option(Config::OPTION_LEDGER, []);

        if (! is_array($ledger)) {
            return [];
        }

        $clean = [];

        foreach ($ledger as $from => $to) {
            if (is_string($from) && is_string($to) && $from !== '') {
                $clean[$from] = $to;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public static function lastReport(): array
    {
        $report = get_option(Config::OPTION_REPORT, []);

        return is_array($report) ? $report : [];
    }

    /**
     * Why a run would do nothing, in the words the Tools page and the CLI
     * both show.
     */
    public static function disabledReason(): string
    {
        if (Config::token() === '') {
            return 'HOSTINGER_API_TOKEN is not set.';
        }

        return sprintf(
            'The environment is %s, not production. Set HOSTINGER_REDIRECTS_ENABLED=true to mirror from here anyway.',
            wp_get_environment_type(),
        );
    }

    /**
     * @return string|WP_Error
     */
    private static function resolveUsername(): string|WP_Error
    {
        $username = Config::username();

        if ($username !== '') {
            return $username;
        }

        $domain = Config::domain();

        if ($domain === '') {
            return new WP_Error('kb_hostinger_no_domain', 'The site domain could not be read from WP_HOME.');
        }

        $discovered = Client::discoverUsername(Config::token(), $domain);

        if ($discovered instanceof WP_Error) {
            return $discovered;
        }

        Config::rememberUsername($discovered);

        return $discovered;
    }

    /**
     * A failed list request is worth coming back to, except when the token
     * itself was refused -- that needs a person, and the hourly sweep will
     * notice when it is fixed.
     */
    private static function requeueAfterFailure(WP_Error $error): void
    {
        if ($error->get_error_code() === 'kb_hostinger_unauthorised') {
            return;
        }

        self::queue($error->get_error_code() === 'kb_hostinger_rate_limited' ? 300 : 120);
    }

    /**
     * @param  array<string, string>  $ledger
     */
    private static function saveLedger(array $ledger): void
    {
        ksort($ledger);

        update_option(Config::OPTION_LEDGER, $ledger, false);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private static function finish(array $report, string $status, string $message, bool $dryRun): array
    {
        $report['status'] = $status;
        $report['message'] = $message;

        if (! $dryRun) {
            update_option(Config::OPTION_REPORT, $report, false);
        }

        if ($status === 'error' && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[hostinger-redirects] ' . $message);
        }

        return $report;
    }
}
