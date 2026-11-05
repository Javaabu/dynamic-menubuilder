<?php

namespace Javaabu\DynamicMenubuilder\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Enums\MenuItemType;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;

/**
 * Converts between a menu's items and the flat list the builders edit, and saves that list
 *
 * The list is in tree order (a parent before its children), and each entry names its parent
 * by `key`, a string the builder makes up for new items (existing items use their id).
 */
class MenuTree
{
    /** The fields of an item the builder edits */
    public const array FIELDS = ['title', 'url', 'new_tab', 'rel', 'css_class', 'icon', 'visibility', 'locales'];

    /** The fields translated with javaabu/translatable */
    public const array TRANSLATABLE_FIELDS = ['title', 'url'];

    /**
     * The menu's items as the builders' list
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toBuilder(?Menu $menu, ?string $locale = null): array
    {
        if (! $menu || ! $menu->exists) {
            return [];
        }

        $locale ??= app()->getLocale();
        $item_class = DynamicMenubuilder::menuItemModel();

        $items = $item_class::query()
            ->where('menu_id', $menu->getKey())
            ->with('linkable')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MenuItem $item) => (string) $item->parent_id);

        $list = [];

        static::appendBuilderItems($list, $items, '', $locale);

        return $list;
    }

    /**
     * The builder's entry for an item
     *
     * @return array<string, mixed>
     */
    public static function builderItem(MenuItem $item, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $source = $item->linkableSource();
        $record = $item->linkedRecord();

        return [
            'key' => (string) $item->getKey(),
            'id' => $item->getKey(),
            'parent' => $item->parent_id === null ? null : (string) $item->parent_id,
            'type' => $item->type->value,
            'source' => $item->source,
            'linkable_id' => $item->linkable_id === null ? null : (string) $item->linkable_id,
            'linkable_label' => $record?->getMenuLinkTitle($locale) ?? ($item->type === MenuItemType::Index ? $source?->getIndexTitle($locale) : null),
            'source_label' => $source?->getLabel(),
            'broken' => $item->isBroken(),
            'title' => $item->getAttributeValue('title'),
            'url' => $item->getAttributeValue('url'),
            'new_tab' => $item->new_tab,
            'rel' => $item->rel,
            'css_class' => $item->css_class,
            'icon' => $item->icon,
            'visibility' => $item->visibility->value,
            'locales' => $item->locales ?? [],
            ...Translations::values($item, self::TRANSLATABLE_FIELDS),
        ];
    }

    /**
     * Validation rules for the list, keyed under the field's name
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $name = 'items'): array
    {
        $types = implode(',', array_column(MenuItemType::cases(), 'value'));
        $visibilities = implode(',', array_column(Visibility::cases(), 'value'));
        $locales = Translations::locales();

        $rules = [
            $name => ['nullable', 'array', 'max:500'],
            "{$name}.*" => ['array'],
            "{$name}.*.key" => ['required', 'string', 'max:64', 'distinct'],
            "{$name}.*.id" => ['nullable', 'integer'],
            "{$name}.*.parent" => ['nullable', 'string', 'max:64'],
            "{$name}.*.type" => ['required', 'string', "in:{$types}"],
            "{$name}.*.source" => ['nullable', 'required_unless:' . $name . '.*.type,link', 'string', 'max:255'],
            "{$name}.*.linkable_id" => ['nullable', 'required_if:' . $name . '.*.type,model', 'string', 'max:255'],
            "{$name}.*.new_tab" => ['nullable', 'boolean'],
            "{$name}.*.rel" => ['nullable', 'string', 'max:255'],
            "{$name}.*.css_class" => ['nullable', 'string', 'max:255'],
            "{$name}.*.icon" => ['nullable', 'string', 'max:255'],
            "{$name}.*.visibility" => ['nullable', 'string', "in:{$visibilities}"],
            "{$name}.*.locales" => ['nullable', 'array'],
            "{$name}.*.locales.*" => ['string', 'max:16'],
        ];

        if ($locales === []) {
            $rules["{$name}.*.title"] = ['nullable', 'required_if:' . $name . '.*.type,link', 'string', 'max:255'];
            $rules["{$name}.*.url"] = ['nullable', 'string', 'max:2048'];

            return $rules;
        }

        $default = Translations::defaultLocale();

        foreach ($locales as $locale) {
            $title_rules = ['nullable', 'string', 'max:255'];

            if ($locale === $default) {
                array_splice($title_rules, 1, 0, ['required_if:' . $name . '.*.type,link']);
            }

            $rules["{$name}.*.title_{$locale}"] = $title_rules;
            $rules["{$name}.*.url_{$locale}"] = ['nullable', 'string', 'max:2048'];
        }

        return $rules;
    }

    /**
     * Check what the rules can't: parents come before their children, the tree isn't deeper
     * than the menu's locations allow, and model and index items point at registered sources
     * and existing records
     *
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    public static function validate(array $items, int $max_depth, string $name = 'items'): void
    {
        $depths = [];
        $errors = [];

        foreach (array_values($items) as $index => $item) {
            $key = (string) ($item['key'] ?? '');
            $parent = filled($item['parent'] ?? null) ? (string) $item['parent'] : null;

            if ($parent !== null && ! isset($depths[$parent])) {
                $errors["{$name}.{$index}.parent"] = __('dynamic-menubuilder::strings.validation.parent');

                continue;
            }

            $depth = $parent === null ? 1 : $depths[$parent] + 1;
            $depths[$key] = $depth;

            if ($depth > $max_depth) {
                $errors[$name] ??= trans_choice('dynamic-menubuilder::strings.validation.depth', $max_depth, ['max' => $max_depth]);
            }

            $type = MenuItemType::tryFrom((string) ($item['type'] ?? ''));

            if ($type === null || $type === MenuItemType::Link) {
                continue;
            }

            $source_key = (string) ($item['source'] ?? '');

            if (! Menus::hasLinkable($source_key)) {
                $errors["{$name}.{$index}.source"] = __('dynamic-menubuilder::strings.validation.source');

                continue;
            }

            $source = Menus::getLinkable($source_key);

            if ($type === MenuItemType::Index && ! $source->hasIndex()) {
                $errors["{$name}.{$index}.source"] = __('dynamic-menubuilder::strings.validation.index');

                continue;
            }

            if ($type === MenuItemType::Model && ! $source->find((string) ($item['linkable_id'] ?? ''))) {
                $errors["{$name}.{$index}.linkable_id"] = __('dynamic-menubuilder::strings.validation.record');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Replace the menu's items with the list: update the ones with an id, create the new ones,
     * and delete the ones left out
     *
     * @param  array<int, array<string, mixed>>|null  $items
     */
    public static function sync(Menu $menu, ?array $items): void
    {
        $items = array_values($items ?? []);
        $item_class = DynamicMenubuilder::menuItemModel();

        DB::transaction(function () use ($menu, $items, $item_class) {
            $existing = $item_class::query()->where('menu_id', $menu->getKey())->get()->keyBy(fn (MenuItem $item) => (string) $item->getKey());

            /** @var array<string, MenuItem> $saved */
            $saved = [];
            $orders = [];

            foreach ($items as $data) {
                $id = filled($data['id'] ?? null) ? (string) $data['id'] : null;
                $item = $id !== null && $existing->has($id) ? $existing->get($id) : new $item_class;
                $parent_key = filled($data['parent'] ?? null) ? (string) $data['parent'] : null;
                $parent_id = $parent_key !== null && isset($saved[$parent_key]) ? $saved[$parent_key]->getKey() : null;
                $order_key = (string) $parent_id;

                $item->menu_id = $menu->getKey();
                $item->parent_id = $parent_id;
                $item->order = $orders[$order_key] = ($orders[$order_key] ?? -1) + 1;

                static::fill($item, $data);

                $item->save();

                $saved[(string) $data['key']] = $item;
            }

            $kept = array_map(fn (Model $item) => $item->getKey(), $saved);

            $item_class::query()
                ->where('menu_id', $menu->getKey())
                ->whereNotIn('id', $kept === [] ? [0] : $kept)
                ->delete();
        });

        Menus::flush();
    }

    /**
     * The smallest maximum depth of the locations, or the default when there are none
     *
     * @param  array<int, string>  $locations
     */
    public static function maxDepthFor(array $locations): int
    {
        $depths = [];

        foreach ($locations as $location) {
            if (Menus::hasLocation($location)) {
                $depths[] = Menus::getLocation($location)->getMaxDepth();
            }
        }

        return $depths === [] ? max(1, (int) config('dynamic-menubuilder.max_depth', 3)) : min($depths);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function fill(MenuItem $item, array $data): void
    {
        $type = MenuItemType::from((string) $data['type']);

        $item->type = $type;
        $item->source = $type === MenuItemType::Link ? null : ($data['source'] ?? null);

        if ($type === MenuItemType::Model) {
            $record = Menus::getLinkable((string) $data['source'])->find((string) $data['linkable_id']);

            $item->linkable_type = $record?->getMorphClass();
            $item->linkable_id = $record?->getKey();
        }

        if ($type !== MenuItemType::Model) {
            $item->linkable_type = null;
            $item->linkable_id = null;
        }

        $item->new_tab = (bool) ($data['new_tab'] ?? false);
        $item->rel = static::stringOrNull($data['rel'] ?? null);
        $item->css_class = static::stringOrNull($data['css_class'] ?? null);
        $item->icon = static::stringOrNull($data['icon'] ?? null);
        $item->visibility = Visibility::tryFrom((string) ($data['visibility'] ?? '')) ?? Visibility::Everyone;
        $item->locales = array_values(array_filter((array) ($data['locales'] ?? []))) ?: null;

        $locales = Translations::supports($item, 'title') ? Translations::locales() : [];

        if ($locales === []) {
            $item->setAttribute('title', static::stringOrNull($data['title'] ?? null));
            $item->setAttribute('url', $type === MenuItemType::Link ? static::stringOrNull($data['url'] ?? null) : null);

            return;
        }

        $translations = [];

        foreach ($locales as $locale) {
            $translations["title_{$locale}"] = static::stringOrNull($data["title_{$locale}"] ?? null);
            $translations["url_{$locale}"] = $type === MenuItemType::Link ? static::stringOrNull($data["url_{$locale}"] ?? null) : null;
        }

        $item->fill($translations);
    }

    protected static function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $list
     * @param  Collection<string, Collection<int, MenuItem>>  $items
     */
    protected static function appendBuilderItems(array &$list, $items, string $parent, string $locale): void
    {
        foreach ($items->get($parent, collect()) as $item) {
            $list[] = static::builderItem($item, $locale);

            static::appendBuilderItems($list, $items, (string) $item->getKey(), $locale);
        }
    }
}
