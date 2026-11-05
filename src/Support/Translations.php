<?php

namespace Javaabu\DynamicMenubuilder\Support;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Translatable\Translatable;
use Throwable;

/**
 * The one place the package talks to the optional javaabu/translatable
 */
class Translations
{
    /**
     * Whether javaabu/translatable is installed, turned on and has active languages
     */
    public static function enabled(): bool
    {
        if (! class_exists(Translatable::class)) {
            return false;
        }

        try {
            return Translatable::enabled();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the model (or class) translates the field (or any field)
     */
    public static function supports(object|string|null $model, ?string $field = null): bool
    {
        if (! class_exists(Translatable::class)) {
            return false;
        }

        try {
            return Translatable::supports($model, $field);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The active locale codes, the default first; an empty array without translatable
     *
     * @return array<int, string>
     */
    public static function locales(): array
    {
        if (! static::enabled()) {
            return [];
        }

        return Translatable::locales()->map(fn ($locale) => $locale->code)->values()->all();
    }

    /**
     * The active locales for the front end: code, name, direction, default
     *
     * @return array<int, array<string, mixed>>
     */
    public static function localeOptions(): array
    {
        if (! static::enabled()) {
            return [];
        }

        return Translatable::locales()
            ->map(fn ($locale) => [
                'code' => $locale->code,
                'name' => $locale->native_name ?: $locale->name,
                'direction' => $locale->direction,
                'default' => (bool) $locale->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * The default locale: translatable's, or the app's
     */
    public static function defaultLocale(): string
    {
        if (static::enabled()) {
            return Translatable::defaultLocale();
        }

        return (string) config('app.locale', 'en');
    }

    /**
     * The value of a translatable field in a locale, without falling back to another locale.
     * Models that don't translate the field give its own value.
     */
    public static function value(Model $model, string $field, string $locale): mixed
    {
        if (! static::supports($model, $field) || ! method_exists($model, 'translate')) {
            return $model->getAttributeValue($field);
        }

        return $model->translate($field, $locale, false);
    }

    /**
     * Every locale's value of the fields, keyed by input name (`title_dv`), for the builder
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    public static function values(Model $model, array $fields): array
    {
        if (! static::supports($model)) {
            return [];
        }

        $values = [];

        foreach (static::locales() as $locale) {
            foreach ($fields as $field) {
                $values["{$field}_{$locale}"] = static::value($model, $field, $locale);
            }
        }

        return $values;
    }
}
