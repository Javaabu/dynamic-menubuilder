---
title: AI assistants
sidebar_position: 1.45
---

The package ships what AI coding assistants need to use it correctly:

- [`llms.txt`](https://github.com/Javaabu/dynamic-menubuilder/blob/main/llms.txt) at the repository root: the rules for using the package and an index of these docs.
- A [Laravel Boost](https://github.com/laravel/boost) guideline (`resources/boost/guidelines/core.blade.php`) and skill (`resources/boost/skills/javaabu-dynamic-menubuilder`). Boost finds them in the Composer package: run `php artisan boost:install` (or `boost:update`) after installing.
- `AGENTS.md` for assistants working on the package itself.
