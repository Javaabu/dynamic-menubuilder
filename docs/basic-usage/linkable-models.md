---
title: Linkable models
sidebar_position: 2
---

Besides custom links, menu items can link to records of your models (a page, a post, a category) and to a model's index page (all news). The item follows the record: rename the page and the menu shows the new title; change its slug and the link follows; delete it and the item goes.

# Making a model linkable

Implement `MenuLinkable` and use the `IsMenuLinkable` trait. The only method you must write is the permalink:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\DynamicMenubuilder\Concerns\IsMenuLinkable;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;

class Page extends Model implements MenuLinkable
{
    use IsMenuLinkable;

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return route('pages.show', $this);
    }
}
```

Then register it, in a service provider's `boot()`:

```php
use Javaabu\DynamicMenubuilder\Facades\Menus;

Menus::linkable(Page::class);
```

or in the config: `'linkables' => [App\Models\Page::class]`.

The builder now has a **Pages** panel to search pages and add them to the menu.

# The contract

| Method | The trait's default | What |
| --- | --- | --- |
| `getMenuLinkTitle(?string $locale)` | The `title` column (translated when the model is translatable) | The title an item shows when the admin doesn't type one |
| `getMenuLinkUrl(?string $locale)` | none: you write it | The record's permalink; `null` hides the item |
| `static getMenuLinkIndexUrl(?string $locale)` | `null` | The model's index page; `null` means it has none, and the builder won't offer one |
| `isMenuLinkVisible()` | `true` | Whether menus show the record now, e.g. only published posts |
| `static searchMenuLinks(Builder $query, string $search)` | `title` like the search | How the builder searches records |

The trait also adds:

- `static getMenuLinkTitleColumn()`: the column of the default title and search, `title`. Override it for models with a `name`.
- `menuItems()`: the menu items that link to the record.
- `removeFromMenus()`: deletes those items; their children move up a level. It runs when the record is deleted (for soft-deleting models, when it's force deleted; until then the item is hidden).

```php
class Category extends Model implements MenuLinkable
{
    use IsMenuLinkable;

    public static function getMenuLinkTitleColumn(): string
    {
        return 'name';
    }

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return route('categories.show', $this);
    }

    public static function getMenuLinkIndexUrl(?string $locale = null): ?string
    {
        return route('categories.index');
    }
}

class Post extends Model implements MenuLinkable
{
    use IsMenuLinkable;

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return route('posts.show', $this);
    }

    public function isMenuLinkVisible(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }
}
```

`$locale` is the locale the menu is rendered in. Use it when your URLs have the locale in them, or when your model keeps each language in its own row and the link should go to that row ([Translations](../advanced-usage/translations.md#linkable-models)).

# Sources

What you register is a **source**: a model, optionally narrowed by a query. One model can give several sources, which is how a single `Post` model with a `type` column gives a **News** panel and an **Events** panel:

```php
Menus::linkable(Post::class, 'news')
    ->label('News')
    ->query(fn ($query) => $query->where('type', 'news')->whereNotNull('published_at'))
    ->indexUrl(fn (string $locale) => route('news.index'))
    ->indexTitle('All news');

Menus::linkable(Post::class, 'events')
    ->label('Events')
    ->query(fn ($query) => $query->where('type', 'events'))
    ->indexUrl(false);
```

| Method | Config key | What |
| --- | --- | --- |
| `linkable($model, $key)` | the array key | The key items store. Defaults to the model's morph alias, or its plural kebab-case name (`blog-posts`). Keep it stable: items find their source by it |
| `label($label)` | `label` | The panel's name in the builder. A string is translated. Defaults to the model's plural name |
| `query($closure)` | | Narrows the records the builder offers and menus show |
| `indexUrl($url)` | | The index page: a URL, a closure that gets the locale, or `false` for none. Defaults to the model's `getMenuLinkIndexUrl()` |
| `indexTitle($title)` | | The default title of the index item. Defaults to the label |
| `can($ability)` | `can` | Only show the source's items to users who pass it (checked when rendering) |

Items of a source that's no longer registered, or whose record is deleted, aren't rendered, and the builder marks them so the admin can remove them.

```php
Menus::linkables();           // ['pages' => LinkableSource, 'news' => ..., ...]
Menus::getLinkable('news');   // LinkableSource
Menus::sourceFor($post);      // the first source whose query contains the post
Menus::getLinkable('news')->search('budget');   // [['id' => 4, 'label' => 'Budget 2027']]
```

Packages that register their own models as sources (such as javaabu/post-types) follow the same contract: see [For package authors](../advanced-usage/for-package-authors.md).
