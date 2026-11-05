# javaabu/dynamic-menubuilder API

The same as `docs/advanced-usage/api-reference.md` in the package. Config options: `docs/advanced-usage/configuration.md`.

# `Menus` facade

`Javaabu\DynamicMenubuilder\Facades\Menus`, over the `MenuManager` singleton.

| Method | Returns |
| --- | --- |
| `location(string $key, ?string $label = null)` | `MenuLocation`, registered |
| `addLocation(MenuLocation $location)` | `MenuLocation` |
| `hasLocation(string $key)` | `bool` |
| `getLocation(string $key)` | `MenuLocation`; throws `InvalidArgumentException` when unknown |
| `locations()` | `array<string, MenuLocation>` |
| `linkable(string $model, ?string $key = null)` | `LinkableSource`, registered |
| `addLinkable(LinkableSource $source)` | `LinkableSource` |
| `hasLinkable(string $key)` | `bool` |
| `getLinkable(string $key)` | `LinkableSource`; throws when unknown |
| `linkables()` | `array<string, LinkableSource>` |
| `sourceFor(Model $record)` | `?LinkableSource`: the first whose query contains the record |
| `menuFor(string $location)` | `?Menu` |
| `assign(string $location, Menu\|int\|null $menu)` | `void` |
| `items(string $location, ?string $locale = null, Authenticatable\|false\|null $user = null, ?Request $request = null)` | `array<int, MenuNode>`. `$user` null means the location's signed-in user, `false` a guest |
| `shared(?array $locations = null, ?string $locale = null)` | `array<string, array>`: each location's items as arrays |
| `menuItems(Menu $menu)` | Every item of the menu with its record, loaded once per request |
| `flush()` | Forgets what this request loaded (called when menus and items are saved) |
| `reset()` | Forgets the registered locations and sources (for tests) |

# `MenuLocation`

`label()`, `description()`, `maxDepth()`, `guard()`, `can(Closure|string|null $ability, $arguments = [])`; `getKey()`, `getLabel()`, `getDescription()`, `getMaxDepth()`, `getGuard()`, `getCan()`, `user()`, `allows(?Authenticatable $user)`, `toArray()`.

# `LinkableSource`

`make($model, $key)`, `label()`, `query(Closure)`, `indexUrl(Closure|string|false|null)`, `indexTitle()`, `can()`; `getKey()`, `getModel()`, `getLabel()`, `getIndexUrl(?string $locale)`, `getIndexTitle(?string $locale)`, `hasIndex()`, `newQuery()`, `contains(Model)`, `find($id)`, `search(?string $search, int $limit = 20, ?string $locale = null)`, `allows()`, `toArray()`.

# `MenuLinkable` and `IsMenuLinkable`

`getMenuLinkTitle(?string $locale)`, `getMenuLinkUrl(?string $locale)` (required), `static getMenuLinkIndexUrl(?string $locale)`, `isMenuLinkVisible()`, `static searchMenuLinks(Builder $query, string $search)`; the trait adds `static getMenuLinkTitleColumn()` (`title`), `menuItems()` and `removeFromMenus()`.

# Models

`Menu` (`name`): `items()` (every item, in order), `rootItems()`, `locationAssignments()`, `locationKeys()`, `assignLocations(array $locations)`, `scopeSearch()`.

`MenuItem`:

| Attribute | |
| --- | --- |
| `menu_id`, `parent_id`, `order` | Structure |
| `type` | `MenuItemType` enum: `Link`, `Model`, `Index` |
| `source`, `linkable_type`, `linkable_id` | What it links to |
| `title`, `url` | Translated with javaabu/translatable |
| `new_tab`, `rel`, `css_class`, `icon` | |
| `visibility` | `Visibility` enum: `Everyone`, `Guests`, `Users` |
| `locales` | `array|null`: the locales it shows in |

Methods: `menu()`, `parent()`, `children()`, `linkable()`, `linkableSource()`, `linkedRecord()`, `isBroken()`, `titleIn(?string $locale)`, `urlIn(?string $locale)`, `displayTitle(?string $locale)`, `displayUrl(?string $locale)`, `showsIn(string $locale)`.

`MenuLocationAssignment` (`location`, `menu_id`): which menu a location shows.

# `DynamicMenubuilder`

`model(string $key)`, `menuModel()`, `menuItemModel()`, `menuLocationModel()`, `table(string $table)`, `ignoreMigrations()`.

# `MenuTree`

`Javaabu\DynamicMenubuilder\Support\MenuTree`:

| Method | |
| --- | --- |
| `toBuilder(?Menu $menu, ?string $locale = null)` | The builder's list: `key`, `id`, `parent`, `type`, `source`, `linkable_id`, `linkable_label`, `source_label`, `broken`, the fields, and `title_{locale}`/`url_{locale}` with translatable |
| `builderItem(MenuItem $item, ?string $locale = null)` | One entry |
| `withDepths(array $items)` | Adds `depth` from the parent keys |
| `rules(string $name = 'items')` | Validation rules for the list |
| `validate(array $items, int $max_depth, string $name = 'items')` | Parents, depth, sources and records; throws `ValidationException` |
| `sync(Menu $menu, ?array $items)` | Saves the list in a transaction |
| `maxDepthFor(array $locations)` | The smallest depth of the locations, or the default |

# Stencil fields

`Javaabu\DynamicMenubuilder\Stencil\MenuItemsField` (type `menu-items`; `searchUrl()`, `locationsField()`) and `MenuLocationsField` (a checkbox list of the locations that saves the assignments). `Javaabu\DynamicMenubuilder\Forms\MenuForm` puts them together.

# JSON API

Every route of `Route::menus()` answers JSON when the request expects it (`Accept: application/json`):

| Request | Response |
| --- | --- |
| `GET menus` | `{ data: [{ id, name, items_count, locations, links, can }], locations: [{ key, label, description, max_depth, menu }], meta: { current_page, last_page, per_page, total } }` |
| `GET menus/create` | `{ data: { form } }` (stencil JSON) |
| `POST menus` | `201 { data: menu }` |
| `GET menus/{menu}` | `{ data: menu }` |
| `GET menus/{menu}/edit` | `{ data: { menu, form } }` |
| `PATCH menus/{menu}` | `{ data: menu }` |
| `DELETE menus/{menu}` | `204` |
| `GET menus/linkables/{source}?search=&limit=` | `{ data: [{ id, label }] }` |

A menu is `{ id, name, locations, items, links, can, created_at, updated_at }`, where `items` is `MenuTree::toBuilder()`. `POST` and `PATCH` take `{ name, locations: [], items: [] }`; validation errors are the usual `422`.

# React

From `@javaabu/dynamic-menubuilder` (through javaabu/manifest):

| Export | |
| --- | --- |
| `Menu`, `useMenu` | Render a location from the Inertia shared data |
| `MenuItemsField` | The builder as a @javaabu/forms field |
| `StencilMenuItems`, `menuStencilComponents` | For `StencilForm` |
| `MenuActions`, `StatusAlerts` | Page helpers |
| `blockEnd`, `moveUp`, `moveDown`, `indent`, `outdent`, `remove`, `moveTo`, `normalize`, `withDepths`, `parents`, `canIndent`, … | The tree operations both builders use (`tree.ts`) |
| Types | `MenuNode`, `SharedMenus`, `BuilderItem`, `BuilderStrings`, `MenusIndexProps`, `MenusFormProps`, … |

The Blade builder's script exports `BladeMenuBuilder` and `initMenuBuilders(root?)` from `@javaabu/dynamic-menubuilder/blade/menu-builder`.
