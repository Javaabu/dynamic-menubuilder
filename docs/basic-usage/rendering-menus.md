---
title: Rendering menus
sidebar_position: 4
---

A location's menu is resolved for the current locale and user: hidden items, unpublished and deleted records, and items deeper than the location allows are left out, and the item for the current page is marked.

# Blade

```blade
<x-dynamic-menubuilder::menu location="header" />
```

renders, when the location has items (and nothing otherwise):

```html
<nav class="dynamic-menu" aria-label="Header" data-menu-location="header">
  <ul class="dynamic-menu__list dynamic-menu__list--depth-1" data-menu-depth="1">
    <li class="dynamic-menu__item dynamic-menu__item--current" data-menu-item="1">
      <a href="/" class="dynamic-menu__link" dir="auto" aria-current="page">ފުރަތަމަ ސަފްހާ</a>
    </li>
    <li class="dynamic-menu__item dynamic-menu__item--has-children" data-menu-item="2">
      <span class="dynamic-menu__heading" dir="auto">ޚިދުމަތްތައް</span>
      <ul class="dynamic-menu__list dynamic-menu__list--depth-2" data-menu-depth="2">…</ul>
    </li>
  </ul>
</nav>
```

| Attribute | What |
| --- | --- |
| `location` | The location (required) |
| `label` | The `<nav>`'s `aria-label`. Default: the location's label |
| `view` | Your own view, which gets `$items` (`MenuNode`s), `$location` and `$label` |
| `locale` | Render in another locale |
| others (`class`, `id`, …) | Go on the `<nav>` |

The classes are there for your CSS: `--active` (the current page is this item or under it), `--current` (this is the current page) and `--has-children`. The item's own CSS classes go on its `<li>`.

## Your own markup

Pass a view, or publish the package's (`--tag=dynamic-menubuilder-views`) and edit `components/menu.blade.php` and `components/partials/menu-items.blade.php`:

```blade
<x-dynamic-menubuilder::menu location="header" view="partials.header-menu" />
```

```blade
{{-- resources/views/partials/header-menu.blade.php --}}
<nav aria-label="{{ $label }}">
    @foreach($items as $item)
        @if($item->hasChildren())
            <details @if($item->active) open @endif>
                <summary>{{ $item->title }}</summary>
                @foreach($item->children as $child)
                    <a href="{{ $child->url }}" @if($child->current) aria-current="page" @endif>{{ $child->title }}</a>
                @endforeach
            </details>
        @else
            <a href="{{ $item->url }}" @if($item->current) aria-current="page" @endif>{{ $item->title }}</a>
        @endif
    @endforeach
</nav>
```

# PHP

```php
use Javaabu\DynamicMenubuilder\Facades\Menus;

$items = Menus::items('header');                 // the current locale and user
$items = Menus::items('header', 'dv');           // another locale
$items = Menus::items('header', user: $user);    // another user; false for a guest
```

Each item is a `Javaabu\DynamicMenubuilder\Rendering\MenuNode`:

| Property or method | What |
| --- | --- |
| `id` | The menu item's id |
| `title`, `url` | In the locale; `url` is null for a heading |
| `type` | `link`, `model` or `index` |
| `depth` | 1 at the top |
| `new_tab`, `target()` | Opens in a new tab; `_blank` or null |
| `rel`, `relAttribute()` | The item's `rel`; with `noopener` for new tabs |
| `css_class`, `icon` | From the builder |
| `current` | The URL is the current page (same path, and same host when it has one) |
| `active` | Current, or a child is active, or the current page is under the URL (`/news` for `/news/budget-2027`) |
| `children`, `hasChildren()` | The items under it |
| `isHeading()` | No URL |
| `toArray()` | For JSON and Inertia |

A location's menu and items are loaded once per request. They aren't cached across requests, because a record's title or URL can change without the menu being saved.

# React

Share the locations' menus with every Inertia page:

```php
// config/dynamic-menubuilder.php
'share' => ['header', 'footer'],   // or true for every location
```

The props get `menus.header` and `menus.footer` (`share_key` changes `menus`). Then:

```tsx
import { Menu } from "@javaabu/dynamic-menubuilder"

<Menu location="header" label="Main" className="main-nav" />
```

It renders the same markup as the Blade component, and nothing for an empty location.

| Prop | What |
| --- | --- |
| `location` | The location, read from the shared data |
| `items` | Render these `MenuNode`s instead |
| `shareKey` | Where the menus are in the props. Default `menus` |
| `label` | The `<nav>`'s accessible name. Default: the location |
| `renderItem` | `({ item, children }) => <li>…</li>`, to render items yourself |
| `classNames` | `{ list, item, link, heading }` |
| other `<nav>` props | Passed on |

```tsx
import { Menu, useMenu } from "@javaabu/dynamic-menubuilder"

const items = useMenu("footer") // MenuNode[]

<Menu
  location="header"
  renderItem={({ item, children }) => (
    <li key={item.id} className={item.active ? "font-semibold" : undefined}>
      {item.url ? <Link href={item.url}>{item.title}</Link> : <span>{item.title}</span>}
      {children}
    </li>
  )}
/>
```

The types (`MenuNode`, `SharedMenus`) are exported too.
