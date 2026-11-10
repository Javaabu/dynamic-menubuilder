<?php

namespace Javaabu\DynamicMenubuilder\Stencil;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Linkables\LinkableSource;
use Javaabu\DynamicMenubuilder\Locations\MenuLocation;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Support\MenuTree;
use Javaabu\DynamicMenubuilder\Support\Translations;
use Javaabu\Stencil\Contracts\SavesRelationships;
use Javaabu\Stencil\Fields\Field;

/**
 * The drag-and-drop builder of a menu's items, as a stencil field: rendered by the Blade view
 * `dynamic-menubuilder::stencil.menu-items` and the React `MenuItemsField`
 *
 * Its state is the flat list of `MenuTree::toBuilder()`, and saving it syncs the menu's items.
 */
class MenuItemsField extends Field implements SavesRelationships
{
    public const string TYPE = 'menu-items';

    protected string $type = self::TYPE;

    protected string|Closure|null $search_url = null;

    protected string|Closure|null $locations_field = 'locations';

    /**
     * The URL of the record search, with `{source}` where the source key goes
     */
    public function searchUrl(string|Closure|null $url): static
    {
        $this->search_url = $url;

        return $this;
    }

    /**
     * The name of the field holding the menu's locations, whose depths limit the builder
     */
    public function locationsField(string|Closure|null $name): static
    {
        $this->locations_field = $name;

        return $this;
    }

    public function savesToAttribute(): bool
    {
        return false;
    }

    public function saveRelationship(Model $record, mixed $state): void
    {
        if (! $record instanceof Menu) {
            return;
        }

        MenuTree::sync($record, is_array($state) ? $state : []);
    }

    protected function readFromRecord(mixed $record): mixed
    {
        return $record instanceof Menu ? MenuTree::toBuilder($record) : [];
    }

    public function normalizeState(mixed $state): mixed
    {
        return is_array($state) ? array_values($state) : [];
    }

    protected function castDehydratedState(mixed $state): mixed
    {
        return is_array($state) ? array_values($state) : [];
    }

    public function getAllValidationRules(): array
    {
        return MenuTree::rules($this->getStatePath());
    }

    protected function getFieldProps(): array
    {
        $locales = Translations::localeOptions();

        return [
            'sources' => array_values(array_map(fn (LinkableSource $source) => $source->toArray(), Menus::linkables())),
            'locations' => array_values(array_map(fn (MenuLocation $location) => $location->toArray(), Menus::locations())),
            'locationsField' => $this->evaluate($this->locations_field),
            'defaultMaxDepth' => max(1, (int) config('dynamic-menubuilder.max_depth', 3)),
            'searchUrl' => $this->evaluate($this->search_url),
            'locales' => $locales === [] ? null : $locales,
            'visibilities' => collect(Visibility::options())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'strings' => static::strings(),
        ];
    }

    /**
     * The builder's text, in the request's locale
     *
     * @return array<string, string>
     */
    public static function strings(): array
    {
        return trans('dynamic-menubuilder::strings.builder');
    }
}
