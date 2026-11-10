---
title: Upgrading
sidebar_position: 1.48
---

# To 1.0 from the unreleased version

Version 1.0 is the first release. The repository's earlier default branch held only the package skeleton (a service provider name and an empty test setup) and was never tagged, so there's nothing to migrate: install 1.0 as described in [Installation & Setup](installation-and-setup.md).

If you referenced the skeleton from a VCS repository:

- Require `javaabu/dynamic-menubuilder` `^1.0` (`^1.0@dev` until it's tagged). PHP 8.4 and Laravel 12 or 13 are required.
- The service provider is still `Javaabu\DynamicMenubuilder\DynamicMenubuilderServiceProvider`, auto-discovered.
- Run the migrations: `menus`, `menu_items` and `menu_locations` are new.
