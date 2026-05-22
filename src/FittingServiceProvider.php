<?php

namespace CryptaTech\Seat\Fitting;

use CryptaTech\Seat\Fitting\Commands\ImportTranslations;
use CryptaTech\Seat\Fitting\Commands\UpgradeFits;
use Illuminate\Support\Facades\Gate;
use Seat\Services\AbstractSeatPlugin;

class FittingServiceProvider extends AbstractSeatPlugin
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->add_routes();
        $this->add_views();
        $this->add_translations();
        $this->add_commands();

        $this->addPublications();

        $this->addMigrations();

        $this->registerSdeTables(['dgmAttributeTypes', 'dgmTypeAttributes', 'dgmEffects', 'dgmTypeEffects', 'invFlags']); // Make sure we have sufficient dogma

        $this->registerPermissionBypass();
    }

    /**
     * When config('fitting.config.bypass_permissions') is true, every fitting.* gate ability
     * resolves to allowed regardless of the user's roles. This is the staging-server escape
     * hatch — production must leave FITTING_BYPASS_PERMISSIONS unset (or false).
     */
    private function registerPermissionBypass(): void
    {
        if (! config('fitting.config.bypass_permissions')) {
            return;
        }

        Gate::before(function ($user, $ability) {
            if (is_string($ability) && str_starts_with($ability, 'fitting.')) {
                return true;
            }

            return null;
        });
    }

    private function addPublications(): void
    {
        $this->publishes([
            __DIR__.'/Config/fitting.exportlinks.php' => config_path('fitting.exportlinks.php'),
        ],
            ['config', 'seat'],
        );

        $this->publishes([
            __DIR__.'/resources/assets/css' => public_path('web/css'),
            __DIR__.'/resources/assets/js' => public_path('web/js'),
        ]);
    }

    /**
     * Include the routes.
     */
    public function add_routes()
    {
        if (! $this->app->routesAreCached()) {
            include __DIR__.'/Http/routes.php';
        }
    }

    private function add_commands()
    {
        $this->commands([
            UpgradeFits::class,
            ImportTranslations::class,
        ]);
    }

    public function add_translations()
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'fitting');
    }

    /**
     * Set the path and namespace for the views.
     */
    public function add_views()
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'fitting');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/fitting.config.php',
            'fitting.config'
        );

        $this->mergeConfigFrom(
            __DIR__.'/Config/fitting.sidebar.php',
            'package.sidebar'
        );

        $this->registerPermissions(
            __DIR__.'/Config/Permissions/fitting.permissions.php',
            'fitting'
        );
    }

    private function addMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations/');
    }

    /**
     * Return the plugin public name as it should be displayed into settings.
     *
     * @example SeAT Web
     */
    public function getName(): string
    {
        return 'Fitting';
    }

    /**
     * Return the plugin repository address.
     *
     * @example https://github.com/eveseat/web
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/akinams053/seat-fitting';
    }

    /**
     * Return the plugin technical name as published on package manager.
     *
     * @example web
     */
    public function getPackagistPackageName(): string
    {
        return 'seat-fitting';
    }

    /**
     * Return the plugin vendor tag as published on package manager.
     *
     * @example eveseat
     */
    public function getPackagistVendorName(): string
    {
        return 'akinams053';
    }
}
