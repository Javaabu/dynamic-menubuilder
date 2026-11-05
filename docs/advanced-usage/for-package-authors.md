---
title: For package authors
sidebar_position: 8
---

Packages whose models belong in menus (javaabu/post-types' posts, a CMS's pages, a shop's categories) can make them linkable without the app writing any code. This page is the contract; it's covered by semver within v1.

# Depending on the package

Make it optional: suggest it, install it for your tests, and only reference its classes when it's installed.

```json
{
    "suggest": {
        "javaabu/dynamic-menubuilder": "Add posts to menus (^1.0)"
    },
    "require-dev": {
        "javaabu/dynamic-menubuilder": "^1.0@dev"
    },
    "repositories": [
        { "type": "vcs", "url": "https://github.com/Javaabu/dynamic-menubuilder" }
    ]
}
```

`MenuLinkable` is an interface, so a class that implements it only loads when the package is installed. Two ways to deal with that:

- **Require it.** The simplest, when menus are central to your package (a CMS).
- **Keep it optional with a subclass.** Put the menu methods in a subclass that implements the interface, for example `Models\Linkable\Post extends Post implements MenuLinkable`, register the subclass as the source's model (only when the package is installed), and leave its morph class alone so menu items load the subclass. Because the base model's `deleted` event doesn't fire the subclass's trait, call `Linkable\Post::make()->forceFill($post->getAttributes())->removeFromMenus()` (or delete the items by `linkable_type`/`linkable_id`) from your base model's `deleted` listener.

# Making the model linkable

```php
namespace Javaabu\PostTypes\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\DynamicMenubuilder\Concerns\IsMenuLinkable;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;

class Post extends Model implements MenuLinkable
{
    use IsMenuLinkable;

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return $this->permalink($locale);
    }

    public function isMenuLinkVisible(): bool
    {
        return $this->isPublished();
    }
}
```

- `getMenuLinkTitle($locale)` defaults to the `title` column, translated when your model is translatable; override `getMenuLinkTitleColumn()` or the method itself.
- `getMenuLinkUrl($locale)` returns the permalink in that locale; `null` hides the item.
- `static getMenuLinkIndexUrl($locale)` is the model's index page. A model shared by several types (one table, a `type` column) can't know which type, so leave it `null` and give each source its own `indexUrl()`.
- `isMenuLinkVisible()` hides drafts and scheduled posts without removing them from menus.
- `static searchMenuLinks($query, $search)` is how the builder searches.

# Registering sources

Register one source per kind of record, from your service provider's `boot()`, only when the package is installed. Give each a stable key: menu items store it.

```php
use Javaabu\DynamicMenubuilder\Facades\Menus;

public function boot(): void
{
    if (! class_exists(Menus::class)) {
        return;
    }

    foreach (PostTypes::all() as $type) {
        Menus::linkable(Post::class, "post-type-{$type->slug}")
            ->label(fn () => $type->pluralLabel())
            ->query(fn ($query) => $query->where('type', $type->slug)->published())
            ->indexUrl(fn (string $locale) => $type->indexUrl($locale))
            ->indexTitle(fn (string $locale) => $type->pluralLabel($locale));
    }
}
```

- Registration is cheap and lazy: nothing is queried until the builder or a menu needs it.
- A later registration with the same key replaces an earlier one, so an app can override your source in its own provider.
- Let apps turn your sources off with a config option of yours.
- Items of a source that's no longer registered are hidden, and flagged in the builder.

# Deleting records

`IsMenuLinkable` removes a record's menu items when it's deleted (force deleted, for soft-deleting models), moving their children up. Call `$record->removeFromMenus()` yourself if you delete records without Eloquent events.

# Testing

```php
Menus::linkable(Post::class, 'post-type-news')->query(fn ($query) => $query->where('type', 'news'));

$menu = Menu::factory()->create();
Menus::location('header');
Menus::assign('header', $menu);
MenuItem::factory()->for($menu)->linkTo($post, 'post-type-news')->create();

$this->assertSame($post->permalink(), Menus::items('header')[0]->url);
```
