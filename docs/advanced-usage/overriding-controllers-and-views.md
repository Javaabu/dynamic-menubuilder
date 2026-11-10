---
title: Overriding controllers and views
sidebar_position: 3
---

# Controllers

Extend the package's controller and point the config (or the macro) at yours:

```php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Javaabu\DynamicMenubuilder\Http\Controllers\MenusController as BaseMenusController;
use Javaabu\DynamicMenubuilder\Models\Menu;

class MenusController extends BaseMenusController
{
    protected function formClass(): string
    {
        return \App\Forms\MenuForm::class;
    }

    protected function saving(Request $request, Menu $menu, array $data): void
    {
        $menu->updated_by = $request->user()->id;
    }
}
```

```php
// config/dynamic-menubuilder.php
'controllers' => [
    'menus' => App\Http\Controllers\Admin\MenusController::class,
],

// or per registration
Route::menus(['controllers' => ['menus' => App\Http\Controllers\Admin\MenusController::class]]);
```

Methods meant for overriding:

| Method | What |
| --- | --- |
| `formClass()` | The stencil form class (`MenuForm`) |
| `query(Request)` | The menus the pages see |
| `saving(Request, Menu, array $data)` | Called before the menu saves, inside the transaction |
| `row(Request, Menu)`, `data(Request, Menu)` | The index row and the JSON of a menu |
| `locations(Request)` | The locations table of the index page |
| `strings()` | The Inertia pages' text |

The record search is `LinkableOptionsController` (`controllers.linkables`).

# The form

`MenuForm` is a [stencil](https://github.com/Javaabu/stencil) form. Extend it to add fields; they render in both stacks and save to the menu like any stencil field (add the columns and `$fillable` to your own `Menu` model):

```php
namespace App\Forms;

use Javaabu\DynamicMenubuilder\Forms\MenuForm as BaseMenuForm;
use Javaabu\Stencil\Fields\Textarea;

class MenuForm extends BaseMenuForm
{
    protected function definition(): array
    {
        return [
            ...parent::definition(),
            Textarea::make('notes')->label(__('Notes')),
        ];
    }
}
```

# Blade views

Publish them with `php artisan vendor:publish --tag=dynamic-menubuilder-views` and edit the copies in `resources/views/vendor/dynamic-menubuilder`:

| View | What |
| --- | --- |
| `menus/index.blade.php`, `menus/form.blade.php`, `menus/partials/*` | The pages |
| `stencil/menu-items.blade.php`, `stencil/partials/menu-item.blade.php` | The builder. Keep the `data-*` attributes: the script relies on them |
| `components/menu.blade.php`, `components/partials/menu-items.blade.php` | `<x-dynamic-menubuilder::menu>` |

# React pages and components

javaabu/manifest lets an app override any single file of the package's `resources/js`: put your copy at `resources/vendor/dynamic-menubuilder/js/<same path>`.

```bash
php artisan manifest:override dynamic-menubuilder js/Pages/Menus/Index.tsx
```

The props of each page are typed in `resources/js/types.ts` (`MenusIndexProps`, `MenusFormProps`).

# The builder's script

The Blade builder's script is `resources/js/blade/menu-builder.ts`, asset `dynamic-menubuilder::builder`. It starts every `[data-menu-builder]` on the page; call `initMenuBuilders(root)` after adding a builder to the page yourself.
