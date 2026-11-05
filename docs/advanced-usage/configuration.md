---
title: Configuration
sidebar_position: 1
---

Publish the config with `php artisan vendor:publish --tag=dynamic-menubuilder-config`. Every option has a default; most apps only set `locations`, `linkables` and, for React, `share`.

# Locations and linkables

| Key | Default | What |
| --- | --- | --- |
| `locations` | `[]` | `key => label`, or `key => ['label', 'description', 'max_depth', 'guard', 'can']`. Merged with `Menus::location()` ([Menu locations](../basic-usage/menu-locations.md)) |
| `linkables` | `[]` | Model classes, or `key => ['model', 'label', 'can']`. Merged with `Menus::linkable()` ([Linkable models](../basic-usage/linkable-models.md)) |
| `max_depth` | `3` | The depth of locations that don't set one, and of menus in no location |

# Inertia shared data

| Key | Default | What |
| --- | --- | --- |
| `share` | `[]` | Locations whose menus every Inertia page gets; `true` for all. Needs inertiajs/inertia-laravel |
| `share_key` | `menus` | The prop they go under |

# Models and tables

| Key | Default | What |
| --- | --- | --- |
| `models.menu` | `null` | Your `Menu` model (extend `Javaabu\DynamicMenubuilder\Models\Menu`) |
| `models.menu_item` | `null` | Your `MenuItem` model. Null uses the package's, or its translatable variant when javaabu/translatable is enabled |
| `models.menu_location` | `null` | Your `MenuLocationAssignment` model |
| `tables.menus` | `menus` | |
| `tables.menu_items` | `menu_items` | |
| `tables.menu_locations` | `menu_locations` | Set table names before migrating |

# Permissions

| Key | Default | What |
| --- | --- | --- |
| `register_permissions` | `true` | Register `view_menus`, `edit_menus`, `delete_menus` and `MenuPolicy` through javaabu/permissions ([Permissions and policies](permissions-and-policies.md)) |

# Views

| Key | Default | What |
| --- | --- | --- |
| `views` | `blade` (`DYNAMIC_MENUBUILDER_VIEWS`) | `blade` or `inertia` |
| `layout` | `layouts.app` | The layout the Blade pages extend |
| `layout_section` | `content` | The section they fill |
| `per_page` | `20` | Menus per page on the index page (`?per_page=` up to 100) |
| `search_limit` | `20` | Records the builder's search returns (`?limit=` up to 100) |

# Controllers

| Key | Default | What |
| --- | --- | --- |
| `controllers.menus` | `MenusController` | The pages |
| `controllers.linkables` | `LinkableOptionsController` | The record search |

See [Overriding controllers and views](overriding-controllers-and-views.md).
