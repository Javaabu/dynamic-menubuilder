---
name: javaabu-dynamic-menubuilder
description: Build site menus with javaabu/dynamic-menubuilder. Use when defining menu locations (Menus::location()->maxDepth()->can()), rendering a location's menu (<x-dynamic-menubuilder::menu location="header" />, Menus::items(), the React Menu/useMenu from @javaabu/dynamic-menubuilder with config('dynamic-menubuilder.share')), making models linkable (MenuLinkable, IsMenuLinkable, getMenuLinkUrl, Menus::linkable() sources, one source per post type), registering the builder pages (Route::menus()) and their permissions (view_menus, edit_menus, delete_menus), saving menus in code (MenuTree::sync, assignLocations), overriding the controllers, form or views, using MenuItemsField in a React form, or translating menus with javaabu/translatable.
license: MIT
metadata:
  author: Javaabu
---

# Menus with javaabu/dynamic-menubuilder

The docs ship with the package: `vendor/javaabu/dynamic-menubuilder/llms.txt` lists every page in `vendor/javaabu/dynamic-menubuilder/docs/`.

| Task | Read |
| --- | --- |
| Locations, linkable models, the builder, rendering | This file |
| Every class, method, option, JSON response and JS export | [references/api.md](references/api.md) |
| Mistakes to avoid | [references/common-mistakes.md](references/common-mistakes.md) |

## 1. Define locations and sources

```php
// app/Providers/AppServiceProvider.php
use App\Models\Page;
use App\Models\Post;
use Javaabu\DynamicMenubuilder\Facades\Menus;

public function boot(): void
{
    Menus::location('header', 'Header')->maxDepth(2);        // one level of dropdowns
    Menus::location('footer', 'Footer')->maxDepth(1);        // a flat list
    Menus::location('staff-links', 'Staff links')
        ->guard('web_admin')
        ->can('view_dashboard');                              // gate ability or closure(?User $user)

    Menus::linkable(Page::class);                             // source key: pages
    Menus::linkable(Post::class, 'news')
        ->label('News')
        ->query(fn ($query) => $query->where('type', 'news')->whereNotNull('published_at'))
        ->indexUrl(fn (string $locale) => route('news.index'));
}
```

## 2. Make models linkable

```php
use Javaabu\DynamicMenubuilder\Concerns\IsMenuLinkable;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;

class Page extends Model implements MenuLinkable
{
    use IsMenuLinkable;   // title from `title`, no index page, always visible, removes its items when deleted

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return route('pages.show', $this);
    }

    public function isMenuLinkVisible(): bool
    {
        return $this->is_published;
    }
}
```

Override `getMenuLinkTitleColumn()` (returns `'name'`) for models without a `title`, and `static getMenuLinkIndexUrl(?string $locale)` to offer an index page.

## 3. Register the builder

```php
// routes/web.php
Route::middleware(['web'])->prefix('admin')->name('admin.')->group(function () {
    Route::menus(['guard' => 'web_admin']);   // admin/menus, admin.menus.*
});
```

```bash
php artisan migrate
php artisan permissions:sync     # view_menus, edit_menus, delete_menus
```

Blade by default; set `DYNAMIC_MENUBUILDER_VIEWS=inertia` for the React pages (`dynamic-menubuilder::Menus/Index`, `dynamic-menubuilder::Menus/Form`). Blade apps need no npm packages (the builder's script is self-contained). Inertia apps install `react`, `react-dom`, `@inertiajs/react`, `@javaabu/onu` and `@javaabu/forms`, which the package declares for pages only: `php artisan manifest:check` lists them once `package.json` has `@inertiajs/react`.

## 4. Render

```blade
<x-dynamic-menubuilder::menu location="header" class="main-nav" />
<x-dynamic-menubuilder::menu location="footer" view="partials.footer-menu" />
```

```php
// config/dynamic-menubuilder.php, for React
'share' => ['header', 'footer'],
```

```tsx
import { Menu, useMenu } from "@javaabu/dynamic-menubuilder"

<Menu location="header" label="Main" />
const footer = useMenu("footer")   // MenuNode[]
```

`Menus::items('header')` gives `MenuNode`s (`title`, `url` (null for headings), `children`, `current`, `active`, `target()`, `relAttribute()`, `css_class`, `icon`) for the current locale and the location's user.

## 5. Save menus in code

```php
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Support\MenuTree;

$menu = Menu::create(['name' => 'Main']);
$menu->assignLocations(['header']);

MenuTree::sync($menu, [
    ['key' => 'home', 'type' => 'link', 'title' => 'ފުރަތަމަ ސަފްހާ', 'url' => '/'],
    ['key' => 'services', 'type' => 'link', 'title' => 'ޚިދުމަތްތައް'],                 // no url: a heading
    ['key' => 'about', 'parent' => 'services', 'type' => 'model', 'source' => 'pages', 'linkable_id' => (string) $page->id],
    ['key' => 'news', 'type' => 'index', 'source' => 'news'],
]);
```

## 6. Translations (javaabu/translatable)

Installed and enabled, items use `title_{locale}` / `url_{locale}` (`title_en`, `title_dv`), and `locales` limits an item to some languages. The structure is shared by every language, so moves apply to all translations. Records give their own title and URL per locale through `getMenuLinkTitle($locale)` / `getMenuLinkUrl($locale)`.

## 7. Override

- Controller: extend `MenusController` (`formClass()`, `query()`, `saving()`), set `config('dynamic-menubuilder.controllers.menus')` or `Route::menus(['controllers' => ['menus' => …]])`.
- Policy: `config('permissions.policies')` or `Gate::policy(Menu::class, …)`.
- Form: extend `MenuForm` (stencil) and return it from `formClass()`.
- Views: `--tag=dynamic-menubuilder-views`; React files through javaabu/manifest overrides.
