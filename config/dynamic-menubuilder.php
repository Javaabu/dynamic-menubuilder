<?php

use Javaabu\DynamicMenubuilder\Http\Controllers\LinkableOptionsController;
use Javaabu\DynamicMenubuilder\Http\Controllers\MenusController;

return [

    /*
    |--------------------------------------------------------------------------
    | Menu locations
    |--------------------------------------------------------------------------
    |
    | The places in your layout that show a menu. Admins choose which menu
    | each location shows. A location is a label, or an array with `label`,
    | `description`, `max_depth` (1 for a flat list), `guard` and `can` (an
    | ability the user needs to see the menu). Labels are translated with
    | __(). You can also define locations with Menus::location() in a
    | service provider.
    |
    */

    'locations' => [
        // 'header' => ['label' => 'Header', 'max_depth' => 2],
        // 'footer' => 'Footer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Linkable models
    |--------------------------------------------------------------------------
    |
    | Models (implementing MenuLinkable) whose records admins can add to
    | menus. A class name, or `key => ['model' => …, 'label' => …]`. Use
    | Menus::linkable() for sources that need a query or an index URL.
    |
    */

    'linkables' => [
        // App\Models\Page::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default maximum depth
    |--------------------------------------------------------------------------
    |
    | How many levels a location shows when it doesn't set `max_depth`.
    |
    */

    'max_depth' => 3,

    /*
    |--------------------------------------------------------------------------
    | Inertia shared data
    |--------------------------------------------------------------------------
    |
    | Locations whose menus are shared with every Inertia page, under
    | `share_key` (`menus.header`), for the React <Menu> component. Use
    | `true` to share every location. Needs inertiajs/inertia-laravel.
    |
    */

    'share' => [],

    'share_key' => 'menus',

    /*
    |--------------------------------------------------------------------------
    | Models and tables
    |--------------------------------------------------------------------------
    |
    | Extend the package's models and put yours here. Leave a model null to
    | use the package's own (or, for menu items, its javaabu/translatable
    | variant when translatable is enabled). Change table names before
    | running the migrations.
    |
    */

    'models' => [
        'menu' => null,
        'menu_item' => null,
        'menu_location' => null,
    ],

    'tables' => [
        'menus' => 'menus',
        'menu_items' => 'menu_items',
        'menu_locations' => 'menu_locations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default permissions
    |--------------------------------------------------------------------------
    |
    | Register view_menus, edit_menus and delete_menus and the menu policy
    | through javaabu/permissions. Override the policy with
    | config('permissions.policies') or Gate::policy().
    |
    */

    'register_permissions' => true,

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Which stack the builder pages render with: `blade` (javaabu/forms) or
    | `inertia` (@javaabu/forms React pages shipped through javaabu/manifest).
    | Requests that expect JSON always get JSON.
    |
    */

    'views' => env('DYNAMIC_MENUBUILDER_VIEWS', 'blade'),

    /*
    | The Blade layout the builder pages extend, and the section they fill.
    */

    'layout' => 'layouts.app',

    'layout_section' => 'content',

    /*
    |--------------------------------------------------------------------------
    | Pagination and search
    |--------------------------------------------------------------------------
    |
    | Menus per page on the index page, and how many records the builder's
    | record search returns.
    |
    */

    'per_page' => 20,

    'search_limit' => 20,

    /*
    |--------------------------------------------------------------------------
    | Controllers
    |--------------------------------------------------------------------------
    |
    | The controllers Route::menus() uses. Extend the package's controllers
    | to change their behaviour.
    |
    */

    'controllers' => [
        'menus' => MenusController::class,
        'linkables' => LinkableOptionsController::class,
    ],

];
