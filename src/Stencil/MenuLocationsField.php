<?php

namespace Javaabu\DynamicMenubuilder\Stencil;

use Illuminate\Database\Eloquent\Model;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Locations\MenuLocation;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\Stencil\Fields\CheckboxList;

/**
 * Checkboxes of the locations a menu shows in. A location holds one menu, so ticking one takes
 * it from the menu it had.
 */
class MenuLocationsField extends CheckboxList
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->options(fn () => collect(Menus::locations())
            ->mapWithKeys(fn (MenuLocation $location) => [$location->getKey() => $location->getLabel()])
            ->all());
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

        $record->assignLocations(array_values((array) ($state ?? [])));
    }

    protected function readFromRecord(mixed $record): mixed
    {
        if (! $record instanceof Menu || ! $record->exists) {
            return [];
        }

        return $record->locationKeys();
    }
}
