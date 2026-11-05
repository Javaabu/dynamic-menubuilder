---
title: Permissions and policies
sidebar_position: 2
---

The package registers a permission group, `menus`, through [javaabu/permissions](https://github.com/Javaabu/permissions), and `MenuPolicy` for `Menu`:

| Permission | Lets the user |
| --- | --- |
| `view_menus` | See the menus and locations |
| `edit_menus` | Create menus, build them (and search records), assign locations |
| `delete_menus` | Delete menus |

Run `php artisan permissions:sync` after installing to create them for the guards in `config('permissions.guards')`, and give them to your roles. The descriptions ("View menus", …) are translated in the package's `lang/dv.json`.

`MenuPolicy` extends `ResourcePolicy`: `viewAny`/`view` need `view_menus`, `create`/`update` need `edit_menus`, `delete` needs `delete_menus`. Menus aren't soft deleted, so `restore`, `forceDelete` and `viewTrash` are always denied.

# Overriding the policy

Either in javaabu/permissions' config:

```php
// config/permissions.php
'policies' => [
    Javaabu\DynamicMenubuilder\Models\Menu::class => App\Policies\MenuPolicy::class,
],
```

or with the gate, in a service provider:

```php
Gate::policy(\Javaabu\DynamicMenubuilder\Models\Menu::class, \App\Policies\MenuPolicy::class);
```

Both win over the package's policy. To register nothing at all, set `register_permissions` to `false` and define the policy yourself.

# Guards

The pages authorize the user of the guard given to `Route::menus(['guard' => …])`, through that user's gate. Nothing assumes the `web` guard or `App\Models\User`: the policy takes any `Model` user. Register the routes once per guard that manages menus.

# Who sees menus

Viewing a menu on the site isn't about these permissions: use a location's `can()` and each item's visibility ([Menu locations](../basic-usage/menu-locations.md#who-sees-a-locations-menu)).
