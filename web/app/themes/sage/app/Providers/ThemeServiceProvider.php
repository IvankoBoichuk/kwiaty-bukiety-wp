<?php

namespace App\Providers;

use App\Admin\ContactSettingsPage;
use App\Admin\DeliveryTimerSettingsPage;
use App\Admin\ProductAttributeIcons;
use App\Api\Categories;
use App\Api\Healthcheck;
use App\Api\PostalCode;
use App\Blocks\Blocks;
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
        Categories::boot();
        Healthcheck::boot();
        Blocks::boot();
        ContactSettingsPage::boot();
        DeliveryTimerSettingsPage::boot();
        ProductAttributeIcons::boot();
        DeliveryTimer::boot();
        PostalCode::boot();
        Blade::directive('id', function ($expression) {
            return "<?php if (!empty($expression)): ?>id=\"<?php echo e($expression); ?>\"<?php endif; ?>";
        });

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('postal-codes import', function ($args) {
                $path = $args[0] ?? null;

                if (! $path) {
                    \WP_CLI::error('Path is required.');
                }

                $count = app(PostalCodeImporter::class)->import($path);

                \WP_CLI::success("Imported {$count} postal codes.");
            });
        }
    }
}
