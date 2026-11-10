# Dynamic Menubuilder

[![Tests](https://github.com/n4ff4h/dynamic-menubuilder/actions/workflows/tests.yml/badge.svg?branch=next-gen)](https://github.com/n4ff4h/dynamic-menubuilder/actions/workflows/tests.yml?query=branch%3Anext-gen)
[![codecov](https://codecov.io/gh/Javaabu/dynamic-menubuilder/branch/next-gen/graph/badge.svg)](https://codecov.io/gh/Javaabu/dynamic-menubuilder)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/dynamic-menubuilder.svg)](https://packagist.org/packages/javaabu/dynamic-menubuilder)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Menus your admins build by drag and drop, for Laravel:

- **Locations:** you define where menus go (header, footer, …) and how deep each one nests. Admins choose the menu each location shows.
- **Links to your models:** pages, posts and categories opt in with an interface and a trait, giving their permalink, title and index page.
- **Builder:** drag and drop that works with the keyboard and with buttons, in Blade ([javaabu/forms](https://github.com/Javaabu/forms)) or Inertia React ([@javaabu/forms](https://github.com/Javaabu/react-forms) and [Onu](https://github.com/Javaabu/onu)), authorized with [javaabu/permissions](https://github.com/Javaabu/permissions).
- **Rendering:** a Blade component and a React component fed by Inertia shared data.
- **Translations:** with [javaabu/translatable](https://github.com/Javaabu/translatable), item titles and links are translated, and the structure is shared by every language.

```php
// AppServiceProvider::boot()
use Javaabu\DynamicMenubuilder\Facades\Menus;

Menus::location('header', 'Header')->maxDepth(2);
Menus::location('footer', 'Footer')->maxDepth(1);
Menus::linkable(Page::class);

// routes/web.php
Route::menus(['prefix' => 'admin/menus', 'middleware' => ['web', 'auth']]);
```

```blade
<x-dynamic-menubuilder::menu location="header" />
```

```tsx
import { Menu } from "@javaabu/dynamic-menubuilder"

<Menu location="header" />
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/docs/dynamic-menubuilder](https://docs.javaabu.com/docs/dynamic-menubuilder), and in [`docs/`](docs/intro.md). AI assistants can start from [`llms.txt`](llms.txt).

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving this package? Feel free to create an [issue](../../issues) on GitHub, we'll try to address it as soon as possible.

If you've found a bug regarding security please mail [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Testing

```bash
composer test        # PHPUnit
composer lint        # Pint
pnpm test            # Vitest
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [Arushad Ahmed (@dash8x)](http://arushad.com)
- [WovenCoast (@WovenCoast)](https://github.com/WovenCoast)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
