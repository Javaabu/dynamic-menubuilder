---
title: Menu locations
sidebar_position: 1
---

A location is a place in your layout that shows a menu: the header, the footer, a sidebar. You define locations in code; admins choose which menu each one shows. A location shows one menu, and one menu can be shown in several locations.

# Defining locations

In a service provider's `boot()`:

```php
use Javaabu\DynamicMenubuilder\Facades\Menus;

Menus::location('header', 'Header')
    ->description('Shown at the top of every page')
    ->maxDepth(2);

Menus::location('footer', 'Footer')->maxDepth(1);
```

Or in `config/dynamic-menubuilder.php`:

```php
'locations' => [
    'header' => ['label' => 'Header', 'max_depth' => 2, 'description' => 'Shown at the top of every page'],
    'footer' => 'Footer',
],
```

Labels and descriptions are translated with `__()` when shown, so `'Header'` can have a Dhivehi line in your `lang/dv.json`. Pass a closure to build the text yourself.

| Method | Config key | What |
| --- | --- | --- |
| `label($label)` | `label` | The name admins see. Defaults to the key, headlined (`main-nav` → "Main Nav") |
| `description($text)` | `description` | A hint under the label in the builder |
| `maxDepth($depth)` | `max_depth` | How many levels the location shows: 1 for a flat list, 2 for one level of dropdowns. Defaults to `config('dynamic-menubuilder.max_depth')` (3) |
| `guard($guard)` | `guard` | The guard whose user `can()` and item visibility check. Defaults to the default guard |
| `can($ability, $arguments)` | `can` | Only show the menu to users who pass a gate ability, or a closure that gets the user |

# Depth

The builder won't let admins nest items deeper than the menu's locations allow: when a menu is in the header (2) and the footer (1), it can only be 1 level deep, and saving a deeper menu fails validation. When rendering, items deeper than the location's maximum are left out.

# Who sees a location's menu

`can()` works like [javaabu/menu-builder](https://github.com/Javaabu/menu-builder)'s: a string is a gate ability checked for the location's user, a closure gets the user (`null` for guests).

```php
Menus::location('staff-links', 'Staff links')
    ->guard('web_admin')
    ->can('view_dashboard');

Menus::location('members', 'Members area')
    ->can(fn (?User $user) => $user?->is_member);
```

A location whose `can()` fails renders nothing. Items have their own visibility too (everyone, signed-out visitors, signed-in users), checked against the location's guard ([Building menus](building-menus.md#item-fields)).

# Assigning menus

Admins tick a menu's locations on its page in the builder. Ticking a location takes it from the menu it showed before. In code:

```php
Menus::assign('header', $menu);   // a Menu or its id
Menus::assign('header', null);    // show nothing
Menus::menuFor('header');         // the Menu, or null

$menu->assignLocations(['header', 'footer']); // exactly these locations
$menu->locationKeys();                         // ['header', 'footer']
```

# Reading locations

```php
Menus::locations();            // ['header' => MenuLocation, ...]
Menus::hasLocation('header');  // true
Menus::getLocation('header');  // MenuLocation; throws for an unknown key
```

Rendering an unknown location throws an `InvalidArgumentException`, so a typo in a layout fails loudly.
