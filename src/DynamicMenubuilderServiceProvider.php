<?php

namespace Javaabu\DynamicMenubuilder;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Policies\MenuPolicy;
use Javaabu\DynamicMenubuilder\Rendering\MenuCache;
use Javaabu\DynamicMenubuilder\Routing\MenuRoutes;
use Javaabu\DynamicMenubuilder\Stencil\MenuItemsField;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;
use Javaabu\Permissions\Permissions;
use Javaabu\Stencil\Facades\Stencil;

class DynamicMenubuilderServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dynamic-menubuilder.php', 'dynamic-menubuilder');

        $this->app->singleton(MenuManager::class);
        $this->app->scoped(MenuCache::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
            $this->registerPublishing();
        }

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'dynamic-menubuilder');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dynamic-menubuilder');
        Blade::componentNamespace('Javaabu\\DynamicMenubuilder\\Views\\Components', 'dynamic-menubuilder');

        Stencil::view(MenuItemsField::TYPE, 'dynamic-menubuilder::stencil.menu-items');

        $this->registerRouteMacros();
        $this->registerPermissions();
        $this->registerFrontendAssets();
        $this->shareWithInertia();
    }

    protected function registerMigrations(): void
    {
        if (DynamicMenubuilder::$runs_migrations) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/dynamic-menubuilder.php' => config_path('dynamic-menubuilder.php'),
        ], 'dynamic-menubuilder-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'dynamic-menubuilder-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/dynamic-menubuilder'),
        ], 'dynamic-menubuilder-translations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/dynamic-menubuilder'),
        ], 'dynamic-menubuilder-views');
    }

    protected function registerRouteMacros(): void
    {
        Route::macro('menus', function (array $options = []) {
            MenuRoutes::register($this, $options);
        });
    }

    /**
     * view_menus, edit_menus and delete_menus, and the menu policy
     */
    protected function registerPermissions(): void
    {
        if (! config('dynamic-menubuilder.register_permissions', true)) {
            return;
        }

        Permissions::resource('menus', 'menus', ['view', 'edit', 'delete'], label: 'Menus');
        Permissions::policy(Menu::class, MenuPolicy::class);
    }

    /**
     * The Inertia pages (dynamic-menubuilder::Menus/Index, …), the React components apps import
     * as `@javaabu/dynamic-menubuilder`, and the Blade builder's script
     */
    protected function registerFrontendAssets(): void
    {
        $this->registerAssets('dynamic-menubuilder', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->js()
                ->pages()
                ->script('builder', 'js/blade/menu-builder.ts')
                ->dist('../dist')
                ->npm([
                    '@javaabu/forms' => 'github:Javaabu/react-forms#next-gen',
                    '@javaabu/onu' => '^0.2.1',
                ], peer_dependencies: [
                    'react' => '^19.0',
                    'react-dom' => '^19.0',
                    '@inertiajs/react' => '^2.0 || ^3.0',
                ]);
        });
    }

    /**
     * Share the configured locations' menus with every Inertia page
     */
    protected function shareWithInertia(): void
    {
        $share = config('dynamic-menubuilder.share', []);

        if (! class_exists(Inertia::class) || empty($share)) {
            return;
        }

        Inertia::share(config('dynamic-menubuilder.share_key', 'menus'), function () use ($share) {
            return Menus::shared($share === true ? null : (array) $share);
        });
    }
}
