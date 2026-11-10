# Common mistakes with javaabu/dynamic-menubuilder

- **Hard-coding a menu admins should edit.** Render the location (`<x-dynamic-menubuilder::menu location="header" />`) instead of writing links in the layout.
- **Rendering an undefined location.** `Menus::items('sidebar')` throws until `Menus::location('sidebar')` (or the config) defines it. Define locations in a service provider that always loads, not in a controller.
- **Expecting routes without registering them.** Nothing registers routes automatically: call `Route::menus()` inside your admin group, with `guard` for non-default guards.
- **Using the menus permissions to hide a menu on the site.** `view_menus` is for the builder. Use the location's `can()` and item visibility for visitors.
- **Forgetting `getMenuLinkUrl()`.** `IsMenuLinkable` can't know your routes; the class won't load without it. Return `null` to hide a record.
- **One source for a model with types.** Posts of several types need one source each (`Menus::linkable(Post::class, 'news')->query(...)`), or the News panel lists events too.
- **Renaming a source key.** Items store the key; renamed keys break existing items (they're hidden and flagged as broken).
- **Calling `Menus::linkable()` with a model that isn't `MenuLinkable`.** It throws `InvalidArgumentException`.
- **Building a nested array for `MenuTree::sync()`.** It takes a flat list in tree order with `parent` keys; a parent must come before its children.
- **Nesting deeper than the locations allow.** A menu in the footer (`maxDepth(1)`) can't have children; validation fails with an `items` error, and deeper items aren't rendered.
- **Setting `title` with javaabu/translatable enabled.** Use `title_en`, `title_dv` (and `url_*`). There's one structure for all languages; don't make one menu per language.
- **Binding `MenuItemsField` by hand.** It's a @javaabu/forms custom field: give it `name` inside a `Form`; don't pass `value`/`onChange`.
- **Hand-editing `dist/`.** Change `resources/js/blade/menu-builder.ts` or `resources/js/tree.ts` and run `pnpm build`.
- **Caching menus forever.** Titles and URLs come from records at render time; the package loads a menu once per request on purpose.
