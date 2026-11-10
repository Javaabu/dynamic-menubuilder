---
title: Introduction
sidebar_position: 1.0
---

# Dynamic Menubuilder

[Dynamic Menubuilder](https://github.com/Javaabu/dynamic-menubuilder) lets the admins of your Laravel app build the site's menus by drag and drop, while you decide where menus go and what they can link to.

- **Menu locations.** You define the places in your layout that show a menu (the header, the footer, a sidebar) and how deep each one nests. Admins choose which menu each location shows. See [Menu locations](basic-usage/menu-locations.md).
- **Links to your models.** Pages, posts, categories and any other model opt in with an interface and a trait, giving their permalink, title and index page. One model can give several kinds of links (one per post type). See [Linkable models](basic-usage/linkable-models.md).
- **The builder.** Pages to list, create and build menus, in Blade ([javaabu/forms](https://github.com/Javaabu/forms)) or Inertia React ([@javaabu/forms](https://github.com/Javaabu/react-forms) and [Onu](https://github.com/Javaabu/onu)). Items move by dragging, with the keyboard or with buttons. Routes come from a macro, controllers and the policy are yours to override, and permissions are registered through [javaabu/permissions](https://github.com/Javaabu/permissions). See [Building menus](basic-usage/building-menus.md).
- **Rendering.** A Blade component, `<x-dynamic-menubuilder::menu location="header" />`, and a React component, `<Menu location="header" />`, fed by Inertia shared data. Both mark the current page. See [Rendering menus](basic-usage/rendering-menus.md).
- **Translations.** With [javaabu/translatable](https://github.com/Javaabu/translatable), item titles and links are translated, and the menu's structure is shared by every language: move an item once and every translation follows. See [Translations](advanced-usage/translations.md).
- **Dhivehi first.** Every string is in English and Dhivehi, the builder works right to left, and the title inputs take Thaana. See [Dhivehi and RTL](advanced-usage/dhivehi-and-rtl.md).

```php
// app/Providers/AppServiceProvider.php
use App\Models\Page;
use Javaabu\DynamicMenubuilder\Facades\Menus;

public function boot(): void
{
    Menus::location('header', 'Header')->maxDepth(2);
    Menus::location('footer', 'Footer')->maxDepth(1);

    Menus::linkable(Page::class);
}
```

```php
// routes/web.php
Route::middleware('web')->group(function () {
    Route::menus(['prefix' => 'admin/menus']);
});
```

```blade
{{-- resources/views/layouts/app.blade.php --}}
<x-dynamic-menubuilder::menu location="header" />
```

Start with [Installation & Setup](installation-and-setup.md).
