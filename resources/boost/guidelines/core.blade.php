{{--
    javaabu/dynamic-menubuilder guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

@verbatim
## javaabu/dynamic-menubuilder

- Site menus admins edit come from `javaabu/dynamic-menubuilder`. Define where menus go with `Menus::location('header', 'Header')->maxDepth(2)` in a service provider, and render them with `<x-dynamic-menubuilder::menu location="header" />` (Blade) or `<Menu location="header" />` from `@javaabu/dynamic-menubuilder` (React, with the location in `config('dynamic-menubuilder.share')`). Don't hard-code those menus in layouts.
- Let menus link to a model with `implements MenuLinkable` + `use IsMenuLinkable` and a `getMenuLinkUrl(?string $locale)` method, then `Menus::linkable(Model::class)`. One model with several types is several sources: `Menus::linkable(Post::class, 'news')->query(...)`.
- The builder is `Route::menus(['prefix' => 'admin/menus', 'guard' => …])` inside the admin group, authorized with `view_menus`, `edit_menus`, `delete_menus` through javaabu/permissions.
- Always activate the `javaabu-dynamic-menubuilder` skill when working on site navigation, menu locations, linkable models, the menu builder pages or rendering menus.
@endverbatim
