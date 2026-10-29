<?php

namespace App\Providers;

use App\Admin\ContactSettingsPage;
use App\Admin\CategoryFaqMetabox;
use App\Admin\CategoryTilesMetabox;
use App\Admin\DeliveryTimerSettingsPage;
use App\Admin\FuneralSettingsPage;
use App\Admin\NavMenuAccent;
use App\Admin\OrderNotificationsSettingsPage;
use App\Admin\ProductAttributeIcons;
use App\Api\CartCount;
use App\Api\Categories;
use App\Api\Cities;
use App\Api\DeliverySchedule;
use App\Api\Healthcheck;
use App\Api\PostalCode;
use App\SEO\CityHub;
use App\SEO\FloristSchema;
use App\Shop\CatalogOrder;
use App\Shop\CartReturn;
use App\Shop\DeliveryWindowRules;
use App\Shop\FuneralCartItem;
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
        Cities::boot();
        DeliverySchedule::boot();
        Healthcheck::boot();
        ContactSettingsPage::boot();
        DeliveryTimerSettingsPage::boot();
        OrderNotificationsSettingsPage::boot();
        FuneralSettingsPage::boot();
        ProductAttributeIcons::boot();
        CategoryFaqMetabox::boot();
        CategoryTilesMetabox::boot();
        NavMenuAccent::boot();
        DeliveryTimer::boot();
        PostalCode::boot();
        CityHub::boot();
        CatalogOrder::boot();
        CartReturn::boot();
        DeliveryWindowRules::boot();
        LeadTimeRules::boot();
        WholesaleDiscount::boot();
        FuneralCartItem::boot();
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
        FloristSchema::boot();
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
        }
    }
}
