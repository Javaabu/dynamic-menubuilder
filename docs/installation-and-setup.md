---
title: Installation & Setup
sidebar_position: 1.2
---

# Installing

The package and its Javaabu dependencies aren't on Packagist yet. Add their repositories, then require the package:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Javaabu/dynamic-menubuilder" },
    { "type": "vcs", "url": "https://github.com/Javaabu/stencil" },
    { "type": "vcs", "url": "https://github.com/Javaabu/permissions" },
    { "type": "vcs", "url": "https://github.com/Javaabu/forms" },
    { "type": "vcs", "url": "https://github.com/Javaabu/manifest" }
]
```

```bash
composer require javaabu/dynamic-menubuilder
php artisan migrate
php artisan permissions:sync
```

The migration creates three tables: `menus`, `menu_items` and `menu_locations` (which menu each location shows). Change their names in the config before migrating if your app already uses them ([Configuration](advanced-usage/configuration.md#models-and-tables)), or publish the migration with `php artisan vendor:publish --tag=dynamic-menubuilder-migrations` and call `DynamicMenubuilder::ignoreMigrations()` in a service provider.

`permissions:sync` creates `view_menus`, `edit_menus` and `delete_menus` ([Permissions and policies](advanced-usage/permissions-and-policies.md)).

# Setting up

Three steps: define where menus go, say what they can link to, and register the builder's routes.

```php
// app/Providers/AppServiceProvider.php
use App\Models\Page;
use App\Models\Post;
use Javaabu\DynamicMenubuilder\Facades\Menus;

public function boot(): void
{
    // Where menus go
    Menus::location('header', 'Header')->maxDepth(2);
    Menus::location('footer', 'Footer')->maxDepth(1);

    // What items can link to, besides custom links
    Menus::linkable(Page::class);
    Menus::linkable(Post::class, 'news')
        ->label('News')
        ->query(fn ($query) => $query->where('type', 'news'));
}
```

```php
// routes/web.php
Route::middleware('web')->group(function () {
    Route::menus(['prefix' => 'admin/menus']);
});
```

Visit `/admin/menus`, create a menu, tick **Header** and add items. Then render it in your layout:

```blade
<x-dynamic-menubuilder::menu location="header" />
```

Each piece has its own page: [Menu locations](basic-usage/menu-locations.md), [Linkable models](basic-usage/linkable-models.md), [Building menus](basic-usage/building-menus.md) and [Rendering menus](basic-usage/rendering-menus.md).

# Blade

The builder's Blade pages extend `layouts.app` and fill its `content` section; change both in the config. Its script is shipped through [javaabu/manifest](https://github.com/Javaabu/manifest) and injected into the page automatically. Apps that build with Vite pick it up with manifest's Vite plugin; others get the prebuilt copy in the package's `dist/`. The script needs no npm packages, so `php artisan manifest:check` doesn't ask Blade apps for React, @javaabu/forms or Onu.

# Inertia React

Set the builder to render with Inertia:

```dotenv
DYNAMIC_MENUBUILDER_VIEWS=inertia
```

The pages are `dynamic-menubuilder::Menus/Index` and `dynamic-menubuilder::Menus/Form`, resolved by javaabu/manifest's `resolvePage()`. Your app needs manifest's Vite plugin and these npm packages. The package declares them for its pages only (`for: 'pages'`), so `php artisan manifest:check` lists what's missing once your `package.json` has `@inertiajs/react`:

```bash
npm install react react-dom @inertiajs/react @javaabu/onu github:Javaabu/react-forms#next-gen
```

```js
// vite.config.js
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
  plugins: [laravel({ input: ["resources/js/app.tsx"] }), manifest(), react()],
})
```

```tsx
// resources/js/app.tsx
import { resolvePage } from "@javaabu/manifest/inertia"

createInertiaApp({
  resolve: (name) => resolvePage(name, import.meta.glob("./pages/**/*.tsx")),
  // ...
})
```

To render menus with React, share their locations with every page ([Rendering menus](basic-usage/rendering-menus.md#react)):

```php
// config/dynamic-menubuilder.php
'share' => ['header', 'footer'],
```

# Publishing

| Tag | What |
| --- | --- |
| `dynamic-menubuilder-config` | `config/dynamic-menubuilder.php` |
| `dynamic-menubuilder-migrations` | The migration |
| `dynamic-menubuilder-translations` | `lang/vendor/dynamic-menubuilder` |
| `dynamic-menubuilder-views` | `resources/views/vendor/dynamic-menubuilder` |

The React pages and components are overridden one file at a time through javaabu/manifest ([Overriding controllers and views](advanced-usage/overriding-controllers-and-views.md)).
