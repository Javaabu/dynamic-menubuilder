# Changelog

All notable changes to `javaabu/dynamic-menubuilder` are documented in this file.

## 1.0.0 - unreleased

The first release.

- PHP 8.4, Laravel 12 and 13, Javaabu codestyle (Pint), CI on PHP 8.4/8.5 × Laravel 12/13 × lowest/stable, Codecov.
- Menu locations defined by the developer (`Menus::location()` or config), each with a label, a description, a maximum depth, a guard and `can()`.
- Linkable models (`MenuLinkable` + `IsMenuLinkable`) registered as sources (`Menus::linkable()`); one model can give several sources; deleting a record removes its items.
- Rendering: `Menus::items()` resolves a location's menu as a tree of `MenuNode`s for the locale and user, with the current and active items marked; `<x-dynamic-menubuilder::menu>` in Blade; `Menu` and `useMenu` in React from Inertia shared data (`share`).
- The builder: `Route::menus()` registers the index, create, edit, update, delete and record-search routes; pages in Blade (javaabu/forms) or Inertia React (@javaabu/forms and Onu), from one stencil form; drag and drop, Alt + arrow keys and buttons to move, indent and outdent items, announced for screen readers; JSON responses.
- `MenuItemsField`, the builder as a @javaabu/forms custom field, and `MenuTree` to validate and save menus in code.
- `view_menus`, `edit_menus` and `delete_menus` and `MenuPolicy` through javaabu/permissions; overridable controllers, form, policy and views; multi-auth.
- Optional javaabu/translatable support: per-locale titles and URLs on one structure shared by every language; items limited to locales.
- English and Dhivehi strings, right-to-left layouts, Thaana inputs.
- Docs, `llms.txt`, `AGENTS.md`, and a Laravel Boost guideline and skill.
