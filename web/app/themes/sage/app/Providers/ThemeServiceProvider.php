<?php

namespace App\Providers;

use App\Admin\ContactSettingsPage;
use App\Admin\CategoryFaqMetabox;
use App\Admin\DeliveryTimerSettingsPage;
use App\Admin\NavMenuAccent;
use App\Admin\OrderNotificationsSettingsPage;
use App\Admin\ProductAttributeIcons;
use App\Api\CartCount;
use App\Api\Categories;
use App\Api\DeliverySchedule;
use App\Api\Healthcheck;
use App\Api\PostalCode;
use App\Console\FaqImporter;
use App\Console\LegacyBlockMigrator;
use App\SEO\CityHub;
use App\Shop\CatalogOrder;
use App\Shop\CartReturn;
use App\Shop\DeliveryWindowRules;
use App\Shop\LeadTimeRules;
use App\Shop\WholesaleDiscount;
use App\Shop\PostalDelivery;
use App\Shop\OrderNotifications;
use App\Shop\OrderSheetsSync;
use App\Shop\OrderAdminColumns;
use App\Shop\RestApiGuard;
use App\Modules\LocalLinking\LocalLinking;
use App\Modules\LocalLinking\PopularOrderAdmin;
use App\SEO\OpenGraph;
use App\SEO\ProductSchema;
use App\SEO\Robots;
use App\SEO\TermShortcodes;
use App\Console\OrderSheetsResend;
use App\Console\PostalCodeSchema;
use App\Services\PostalCodeImporter;
use App\Support\Context;
use App\Support\DeliveryTimer;
use Illuminate\Support\Facades\Blade;
use Roots\Acorn\Sage\SageServiceProvider;
use Timber\Timber;

class ThemeServiceProvider extends SageServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->app->singleton(Context::class);
        $this->app->singleton(DeliveryTimer::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        Timber::init();
        CartCount::boot();
        Categories::boot();
        DeliverySchedule::boot();
        Healthcheck::boot();
        ContactSettingsPage::boot();
        DeliveryTimerSettingsPage::boot();
        OrderNotificationsSettingsPage::boot();
        ProductAttributeIcons::boot();
        CategoryFaqMetabox::boot();
        NavMenuAccent::boot();
        DeliveryTimer::boot();
        PostalCode::boot();
        CityHub::boot();
        CatalogOrder::boot();
        CartReturn::boot();
        DeliveryWindowRules::boot();
        LeadTimeRules::boot();
        WholesaleDiscount::boot();
        PostalDelivery::boot();
        OrderNotifications::boot();
        OrderSheetsSync::boot();
        OrderAdminColumns::boot();
        RestApiGuard::boot();
        LocalLinking::boot();
        PopularOrderAdmin::boot();
        Robots::boot();
        TermShortcodes::boot();
        OpenGraph::boot();
        ProductSchema::boot();
        Blade::directive('id', function ($expression) {
            return "<?php if (!empty($expression)): ?>id=\"<?php echo e($expression); ?>\"<?php endif; ?>";
        });

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command(
                'kb sheets resend',
                [OrderSheetsResend::class, 'handle'],
                [
                    'shortdesc' => 'Queues an order for the Google Sheets sync again.',
                    'synopsis' => [
                        [
                            'type' => 'positional',
                            'name' => 'order_id',
                            'description' => 'The order to re-send.',
                        ],
                        [
                            'type' => 'assoc',
                            'name' => 'status',
                            'optional' => true,
                            'description' => "Status to send as. Defaults to the order's current one.",
                        ],
                    ],
                ],
            );

            \WP_CLI::add_command('postal-codes install', function () {
                if (PostalCodeSchema::install()) {
                    \WP_CLI::success('Created the postal_codes table.');

                    return;
                }

                \WP_CLI::log('The postal_codes table already exists.');
            });

            \WP_CLI::add_command('postal-codes import', function ($args) {
                $path = $args[0] ?? null;

                if (! $path) {
                    \WP_CLI::error('Path is required.');
                }

                if (! PostalCodeSchema::exists()) {
                    \WP_CLI::error('The postal_codes table is missing. Run `wp postal-codes install` first.');
                }

                $count = app(PostalCodeImporter::class)->import($path);

                \WP_CLI::success("Imported {$count} postal codes.");
            });

            \WP_CLI::add_command('kb-faq import', function ($args, $assoc) {
                $path = $args[0] ?? null;

                if (! $path) {
                    \WP_CLI::error('Path to faq-parsed.json is required.');
                }

                $dryRun = isset($assoc['dry-run']);

                try {
                    $result = (new FaqImporter())->import((string) $path, $dryRun);
                } catch (\InvalidArgumentException $exception) {
                    \WP_CLI::error($exception->getMessage());
                }

                foreach ($result['missing'] as $slug) {
                    \WP_CLI::warning("No product_cat term with slug '{$slug}'; skipped.");
                }

                foreach ($result['empty'] as $slug) {
                    \WP_CLI::warning("Category '{$slug}' has no usable questions; skipped.");
                }

                \WP_CLI::success(sprintf(
                    '%s %d categories / %d questions.',
                    $dryRun ? 'Would import' : 'Imported',
                    $result['terms'],
                    $result['questions'],
                ));
            });

            \WP_CLI::add_command('blocks migrate', function ($args, $assoc) {
                $postIds = array_values(array_filter(array_map(
                    'absint',
                    explode(',', (string) ($assoc['post_id'] ?? '')),
                )));

                if ($postIds === []) {
                    \WP_CLI::error('--post_id=<id[,id]> is required.');
                }

                $dryRun = isset($assoc['dry-run']);
                $migrator = new LegacyBlockMigrator();

                foreach ($postIds as $postId) {
                    $post = get_post($postId);

                    if (! $post) {
                        \WP_CLI::warning("Post {$postId} not found.");

                        continue;
                    }

                    $result = $migrator->migrate($post->post_content);

                    \WP_CLI::log("--- post {$postId}: {$post->post_title}");

                    foreach ($result['log'] as $line) {
                        \WP_CLI::log("    {$line}");
                    }

                    if (! $result['changed']) {
                        \WP_CLI::log('    nothing to migrate');

                        continue;
                    }

                    if ($dryRun) {
                        \WP_CLI::log('    (dry run, not saved)');

                        continue;
                    }

                    // Only post_content changes. wp_update_post() would also
                    // re-validate the page template, and it rejects the whole
                    // update when a post points at a template the theme no
                    // longer ships -- unrelated to this migration, but fatal
                    // to it. Write the column directly and keep a revision.
                    wp_save_post_revision($postId);

                    global $wpdb;

                    $written = $wpdb->update(
                        $wpdb->posts,
                        ['post_content' => $result['content']],
                        ['ID' => $postId],
                    );

                    if ($written === false) {
                        \WP_CLI::warning('    failed to write post_content');

                        continue;
                    }

                    clean_post_cache($postId);

                    $template = get_page_template_slug($postId);

                    if ($template !== '' && ! isset(wp_get_theme()->get_page_templates(get_post($postId))[$template])) {
                        \WP_CLI::warning("    post still points at a missing page template: {$template}");
                    }

                    \WP_CLI::log('    saved');
                }

                \WP_CLI::success($dryRun ? 'Dry run complete.' : 'Migration complete.');
            });
        }
    }
}
