<?php

namespace Javaabu\DynamicMenubuilder;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Models\MenuLocationAssignment;
use Javaabu\DynamicMenubuilder\Support\Translations;

/**
 * Resolves the configured models and tables of the package
 */
class DynamicMenubuilder
{
    /** The package's models, keyed by their config key */
    public const array MODELS = [
        'menu' => Menu::class,
        'menu_item' => MenuItem::class,
        'menu_location' => MenuLocationAssignment::class,
    ];

    /** The translatable variants the package ships, used when javaabu/translatable is enabled */
    public const array TRANSLATABLE_MODELS = [
        'menu_item' => Models\Translatable\MenuItem::class,
    ];

    /** Whether the service provider loads the package's migrations */
    public static bool $runs_migrations = true;

    /**
     * The model class for a key: the app's from config, the translatable variant when
     * javaabu/translatable is enabled, or the package's own
     *
     * @return class-string<Model>
     */
    public static function model(string $key): string
    {
        if (! array_key_exists($key, self::MODELS)) {
            throw new InvalidArgumentException("Unknown model [{$key}]. Use one of: " . implode(', ', array_keys(self::MODELS)) . '.');
        }

        $configured = config("dynamic-menubuilder.models.{$key}");

        if ($configured) {
            return $configured;
        }

        if (isset(self::TRANSLATABLE_MODELS[$key]) && Translations::enabled()) {
            return self::TRANSLATABLE_MODELS[$key];
        }

        return self::MODELS[$key];
    }

    /** @return class-string<Menu> */
    public static function menuModel(): string
    {
        return static::model('menu');
    }

    /** @return class-string<MenuItem> */
    public static function menuItemModel(): string
    {
        return static::model('menu_item');
    }

    /** @return class-string<MenuLocationAssignment> */
    public static function menuLocationModel(): string
    {
        return static::model('menu_location');
    }

    /**
     * The table name for a table key: `menus`, `menu_items` or `menu_locations`
     */
    public static function table(string $table): string
    {
        return config("dynamic-menubuilder.tables.{$table}") ?: $table;
    }

    public static function ignoreMigrations(): void
    {
        static::$runs_migrations = false;
    }
}
