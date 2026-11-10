# dynamic-menubuilder: notes for AI coding agents

`javaabu/dynamic-menubuilder` is a Laravel package for menus admins build by drag and drop: developer-defined locations, linkable models (sources), builder pages in Blade (javaabu/forms v2) and Inertia React (@javaabu/forms + Onu, delivered as `@javaabu/dynamic-menubuilder` through javaabu/manifest), rendering components for both, and optional javaabu/translatable support.

## Layout

| Path | What |
| --- | --- |
| `src/MenuManager.php`, `src/Facades/Menus.php` | Locations, sources, assignments and rendering (`items()`, `shared()`); per-request loading through the scoped `Rendering/MenuCache` |
| `src/Locations`, `src/Linkables` | `MenuLocation` and `LinkableSource`, with `Concerns/HasCan` (menu-builder's `can()`) |
| `src/Contracts/MenuLinkable.php`, `src/Concerns/IsMenuLinkable.php` | The contract models implement, and its defaults. Public API for other packages (post-types) |
| `src/Models` | `Menu`, `MenuItem`, `MenuLocationAssignment`, and `Translatable/MenuItem`. Always resolve them through `DynamicMenubuilder::menuItemModel()` etc. |
| `src/Rendering/MenuNode.php` | What the Blade and React components draw |
| `src/Support/MenuTree.php` | The builders' flat list: `toBuilder()`, `rules()`, `validate()`, `sync()` |
| `src/Support/Translations.php` | The only class that references javaabu/translatable (behind `class_exists`) |
| `src/Forms`, `src/Stencil` | `MenuForm` and the stencil fields `MenuItemsField` (type `menu-items`) and `MenuLocationsField` |
| `src/Http`, `src/Routing`, `src/Policies`, `src/Views` | Controllers, `Route::menus()`, `MenuPolicy`, `ViewRenderer` (Blade or Inertia) and the Blade component |
| `resources/views` | Pages, the builder (`stencil/`), the menu component |
| `resources/js/tree.ts` | Tree operations shared by both builders. Change them here, never in a builder |
| `resources/js/blade/menu-builder.ts` | The Blade builder's script, built into `dist/` (committed; CI fails when stale) |
| `resources/js/components`, `resources/js/Pages`, `resources/js/types.ts` | React: `Menu`, `MenuItemsField`, `StencilMenuItems`, the pages, the prop types |
| `tests/` | PHPUnit (`Unit`, `Feature`, `Http`, `Translatable`) and Vitest (`tests/js`, with fixtures written by `InertiaPagesTest` and `BladePagesTest`) |
| `docs`, `llms.txt`, `resources/boost` | Docs, the index for assistants, the Laravel Boost guideline and skill |

## Rules

- Follow the Javaabu PHP guidelines (`vendor/javaabu/boost-javaabu-guidelines`): snake_case variables and properties, camelCase methods, `#[Test]` methods named `it_…`, early returns, no `else`.
- Never add `javaabu/helpers` (or anything that pulls in `javaabu/settings ^1`) as a production dependency. CI checks.
- javaabu/translatable and inertiajs/inertia-laravel are optional: only `Support/Translations.php`, `Models/Translatable/` and `Views/ViewRenderer.php` (and the Inertia share in the service provider) may reference them, behind `class_exists`. CI runs the suite without them.
- The menu has one structure for every language; never add per-locale structure. Translated fields are `title` and `url` only.
- The builder's React field must stay a @javaabu/forms custom field (`useFieldState`, `splitFieldProps`, `usePublishField`), never hand-bound `value`/`onChange`.
- Keep Blade and React builders in step: same `data-*` contract, same keyboard behaviour, same strings (`builder.*`). A change to the builder's markup regenerates `tests/js/fixtures/blade-builder.html`.
- A change to a page's props changes `resources/js/types.ts`, the pages and the docs together. Then run `DYNAMIC_MENUBUILDER_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter "Inertia|fixture"` and commit `tests/js/fixtures`.
- After changing `resources/js/blade` or `resources/js/tree.ts`, run `pnpm build` and commit `dist/`.
- Every user-facing string goes in `lang/en/strings.php` and `lang/dv/strings.php` (same keys and placeholders; `LocalizationTest` checks), permission descriptions in `lang/dv.json`. Logical directions only. List uncertain Dhivehi in `docs/advanced-usage/dhivehi-and-rtl.md`.
- Every change needs tests, docs (linked from `llms.txt`; `DocsTest` checks links and config coverage), the Boost skill if it changes usage, and a line in `CHANGELOG.md`.

## Commands

```bash
composer install && pnpm install      # pnpm 10.28 (packageManager)
composer lint && composer test        # Pint and PHPUnit
pnpm lint && pnpm format:check && pnpm typecheck && pnpm test
pnpm build                            # dist/
```
