---
title: Building menus
sidebar_position: 3
---

The builder is a set of pages to list, create, build and delete menus. They render in Blade (javaabu/forms) or Inertia React (@javaabu/forms and Onu), from the same controller and the same [stencil](https://github.com/Javaabu/stencil) form.

# Routes

Register the routes with the macro, inside the middleware your admin pages use:

```php
// routes/web.php
Route::middleware('web')->group(function () {
    Route::menus(['prefix' => 'admin/menus', 'name' => 'admin.menus']);
});
```

| Option | Default | What |
| --- | --- | --- |
| `prefix` | `menus` | The URL prefix |
| `name` | `menus` | The route name prefix (`menus.index`, …); `''` for none |
| `guard` | the default guard | The guard whose user is authorized |
| `middleware` | `['auth:{guard}']` | Middleware on the routes, on top of the group's |
| `controllers` | from config | `['menus' => …, 'linkables' => …]` |

| Method | URI | Name | What |
| --- | --- | --- | --- |
| GET | `menus` | `menus.index` | The locations and the menu each shows, and the menus (searchable, paginated) |
| GET | `menus/create` | `menus.create` | The form for a new menu |
| POST | `menus` | `menus.store` | Creates a menu, then goes to its builder |
| GET | `menus/{menu}/edit` | `menus.edit` | The builder |
| GET | `menus/{menu}` | `menus.show` | JSON for API requests; otherwise redirects to the builder |
| PUT/PATCH | `menus/{menu}` | `menus.update` | Saves the name, locations and items together |
| DELETE | `menus/{menu}` | `menus.destroy` | Deletes the menu and its items |
| GET | `menus/linkables/{source}` | `menus.linkables.index` | Searches a source's records: `?search=` and `?limit=` give `{ data: [{ id, label }] }` |

For admins of another guard, register the routes again:

```php
Route::middleware('web')->prefix('admin')->name('admin.')->group(function () {
    Route::menus(['guard' => 'web_admin']);
});
```

Every action answers JSON when the request expects it ([API reference](../advanced-usage/api-reference.md#json-api)).

# The builder

The builder page has the menu's **name**, the **locations** it shows in, and its **items**:

- **Add items** lists a **Link** panel (a title and a URL; leave the URL empty for a heading, such as a dropdown's label) and one panel per [linkable source](linkable-models.md), where admins search records and add them, or add the source's index page.
- **Menu structure** is the menu, top to bottom. Each item has a drag handle, its title and what it links to, and buttons to **Move up**, **Move down**, **Move out of its parent** (outdent), **Move under the item above** (indent), **Edit** and **Remove**. Removing an item moves its children up a level.
- Saving sends the whole menu at once: new items are created, changed ones updated, moved ones re-parented and re-ordered, and items left out deleted.

## Moving items with the keyboard

| Keys (on an item's drag handle) | Blade | React |
| --- | --- | --- |
| <kbd>Alt</kbd>+<kbd>↑</kbd> / <kbd>Alt</kbd>+<kbd>↓</kbd> | Up / down among its siblings | Up / down one place |
| <kbd>Alt</kbd>+<kbd>→</kbd> / <kbd>Alt</kbd>+<kbd>←</kbd> | Indent / outdent (swapped right to left) | Indent / outdent (swapped right to left) |
| <kbd>Space</kbd>, arrows, <kbd>Space</kbd> | | Pick up, move, drop (Onu's `SortableList`) |
| <kbd>Tab</kbd> | The buttons do the same | The buttons do the same |

Every move is announced to screen readers ("Contact moved to position 2 of 3", "Contact is now under Services"). Dragging with a mouse also works; in the Blade builder, dragging sideways while dropping changes the depth.

## Depth

The builder knows the depth of every location: tick **Footer** (one level) and the indent buttons turn off. Saving a menu deeper than its locations allow fails with a message on the items.

## Item fields

Open an item with **Edit**:

| Field | What |
| --- | --- |
| Title | The text. For records and index pages it's optional: empty uses the record's title (shown as the placeholder) |
| URL | Links only; empty makes a heading |
| Show to | Everyone, signed-out visitors or signed-in users (of the location's guard) |
| Icon | A CSS class for an icon, e.g. `fa-solid fa-house`, rendered as `<i class="…">` |
| CSS classes | Added to the item's `<li>` |
| Link relationship (rel) | `rel` on the link; `noopener` is added for new tabs |
| Open in a new tab | `target="_blank"` |
| Show in these languages | With javaabu/translatable: the languages the item shows in ([Translations](../advanced-usage/translations.md)) |

# Blade and React side by side

Which stack renders the pages is a config option, `views` (`blade` by default):

```dotenv
DYNAMIC_MENUBUILDER_VIEWS=inertia
```

| | Blade | Inertia React |
| --- | --- | --- |
| Index | `dynamic-menubuilder::menus.index` | `dynamic-menubuilder::Menus/Index` |
| Create and edit | `dynamic-menubuilder::menus.form` | `dynamic-menubuilder::Menus/Form` |
| The builder field | `dynamic-menubuilder::stencil.menu-items` and the `dynamic-menubuilder::builder` script | `MenuItemsField` (`StencilMenuItems` for stencil) |

Both render `MenuForm`, a stencil form with `name`, `locations` (`MenuLocationsField`) and `items` (`MenuItemsField`).

## The builder in your own React form

`MenuItemsField` is a [@javaabu/forms custom field](https://github.com/Javaabu/react-forms/blob/next-gen/docs/advanced-usage/custom-fields.md): it reads and writes the form's value (including Inertia `useForm()` data), resets with `resetOnSuccess`, clears its errors when edited and posts hidden inputs for native submits.

```tsx
import { Form, Text } from "@javaabu/forms"
import { MenuItemsField } from "@javaabu/dynamic-menubuilder"

<Form model={menu} action={`/admin/menus/${menu.id}`} method="PATCH">
  <Text name="name" />
  <MenuItemsField name="items" maxDepth={2} searchUrl="/admin/menus/linkables/{source}" sources={sources} strings={strings} />
</Form>
```

To render the package's stencil form in a page of your own, register the field's component:

```tsx
import { StencilForm } from "@javaabu/stencil"
import { menuStencilComponents } from "@javaabu/dynamic-menubuilder"

<StencilForm schema={form} components={menuStencilComponents} />
```

The props come from the PHP field (`MenuItemsField::getProps()`): `sources`, `locations`, `locationsField`, `defaultMaxDepth`, `searchUrl`, `locales`, `visibilities` and `strings`.

## Without the pages

Build and save menus in code with `MenuTree`:

```php
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Support\MenuTree;

$menu = Menu::create(['name' => 'Main']);
$menu->assignLocations(['header']);

MenuTree::sync($menu, [
    ['key' => 'home', 'type' => 'link', 'title' => 'ފުރަތަމަ ސަފްހާ', 'url' => '/'],
    ['key' => 'services', 'type' => 'link', 'title' => 'ޚިދުމަތްތައް'],
    ['key' => 'permits', 'parent' => 'services', 'type' => 'model', 'source' => 'pages', 'linkable_id' => '12'],
    ['key' => 'news', 'type' => 'index', 'source' => 'news'],
]);
```

The list is in tree order; `parent` names an earlier item's `key`. `MenuTree::validate($items, $max_depth)` checks parents, depth, sources and records, and `MenuTree::toBuilder($menu)` gives the list back.

# Permissions

The pages need `view_menus` to list, `edit_menus` to create, build and search records, and `delete_menus` to delete. See [Permissions and policies](../advanced-usage/permissions-and-policies.md).
