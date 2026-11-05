---
title: Translations
sidebar_position: 4
---

Menus can be translated with [javaabu/translatable](https://github.com/Javaabu/translatable). It's optional: without it, an item has one title and one URL, and everything else works the same.

# Setting up

```bash
composer require javaabu/translatable
php artisan translatable:add-languages en dv
```

Nothing else: when translatable is installed, enabled and has active languages, the package uses `Javaabu\DynamicMenubuilder\Models\Translatable\MenuItem`, whose `title` and `url` are translated with translatable's JSON storage. The `translations` and `lang` columns are already in the package's migration. If you set your own model in `models.menu_item`, add translatable to it yourself (`implements JsonTranslatable`, `use IsJsonTranslatable`, `protected array $translatable = ['title', 'url']`).

# One structure, every language

A menu has **one structure shared by every language**: which items it has, their order, their nesting and the records they link to. Only the texts are per language:

| Per language | Shared |
| --- | --- |
| Title, URL (custom links) | Order, parents, item type, linked record or source, visibility, icon, CSS classes, `rel`, new tab |

So translation syncing comes for free: when an admin adds an item, moves it, nests it or removes it, every translation changes with it. There's no copy per language to fall out of step.

When a language needs something different:

- **A different title or URL**: fill it in for that language. The builder shows one title and URL input per language (`title_en`, `title_dv`), with the right direction and the Thaana keyboard for Dhivehi.
- **An item only some languages have**: tick its languages under **Show in these languages**. An item with none ticked shows in every language. The item keeps its place in the structure.

# Fallbacks

When rendering in a language:

| Item | Title | URL |
| --- | --- | --- |
| Link | Its title in the language, then its title in its own language | Its URL in the language, then its URL in its own language |
| Record | Its title in the language, then the record's title in the language (`getMenuLinkTitle($locale)`) | `getMenuLinkUrl($locale)` |
| Index page | Its title in the language, then the source's index title | The source's index URL for the locale |

Only the default language's title is required for links; leaving a Dhivehi title empty shows the English one.

# Linkable models

The record decides its own title and URL in each language: `getMenuLinkTitle($locale)` and `getMenuLinkUrl($locale)` get the locale the menu is rendered in. The trait's default title uses translatable when the model's title column is translatable (JSON storage).

For models that keep each language in its own row (translatable's DB storage, where a Dhivehi post is a separate row pointing at its English parent), link items to the row of the default language and return the translation's URL:

```php
public function getMenuLinkUrl(?string $locale = null): ?string
{
    $translation = $locale ? $this->getTranslation($locale) : null;

    return route('posts.show', $translation ?? $this);
}
```

Use `isMenuLinkVisible()` to hide records that aren't published in any form; to hide a record only in languages it has no translation for, return `null` from `getMenuLinkUrl()` for those locales.

# In code

```php
use Javaabu\DynamicMenubuilder\Support\MenuTree;

MenuTree::sync($menu, [
    ['key' => 'contact', 'type' => 'link', 'title_en' => 'Contact', 'title_dv' => 'ގުޅުއްވުމަށް', 'url_en' => '/contact', 'url_dv' => '/dv/contact'],
    ['key' => 'careers', 'type' => 'link', 'title_en' => 'Careers', 'url_en' => '/careers', 'locales' => ['en']],
]);

Menus::items('header', 'dv');   // ގުޅުއްވުމަށް → /dv/contact; no Careers
$item->titleIn('dv');           // the item's own Dhivehi title, or null
$item->displayTitle('dv');      // with the fallbacks above
```

`Javaabu\DynamicMenubuilder\Support\Translations` is the one class that talks to translatable; it's safe to call without it installed (`Translations::enabled()`, `supports()`, `locales()`, `defaultLocale()`).
