<?php

namespace Javaabu\DynamicMenubuilder\Forms;

use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Stencil\MenuItemsField;
use Javaabu\DynamicMenubuilder\Stencil\MenuLocationsField;
use Javaabu\Stencil\Components\Section;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Form;

/**
 * The create and edit form of a menu: its name, its locations and its items. Extend it and set
 * yours in the controller's `formClass()` to add fields.
 */
class MenuForm extends Form
{
    protected function definition(): array
    {
        $details = [
            TextInput::make('name')
                ->label(__('dynamic-menubuilder::strings.fields.name'))
                ->required()
                ->maxLength(255),
        ];

        if (Menus::locations() !== []) {
            $details[] = MenuLocationsField::make('locations')
                ->label(__('dynamic-menubuilder::strings.fields.locations'))
                ->help(__('dynamic-menubuilder::strings.help.locations'));
        }

        return [
            Section::make(__('dynamic-menubuilder::strings.sections.details'))->schema($details),
            Section::make(__('dynamic-menubuilder::strings.sections.items'))->schema([
                MenuItemsField::make('items')
                    ->label(__('dynamic-menubuilder::strings.fields.items'))
                    ->hiddenLabel()
                    ->searchUrl($this->context('search_url')),
            ]),
        ];
    }
}
