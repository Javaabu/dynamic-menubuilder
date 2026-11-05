<?php

namespace Javaabu\DynamicMenubuilder\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Javaabu\DynamicMenubuilder\Enums\MenuItemType;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'type' => MenuItemType::Link,
            'title' => $this->faker->words(2, true),
            'url' => '/' . $this->faker->slug(2),
        ];
    }

    public function heading(): static
    {
        return $this->state(['url' => null]);
    }

    public function childOf(MenuItem $parent): static
    {
        return $this->state(['menu_id' => $parent->menu_id, 'parent_id' => $parent->getKey()]);
    }

    public function linkTo(Model $record, string $source): static
    {
        return $this->state([
            'type' => MenuItemType::Model,
            'source' => $source,
            'linkable_type' => $record->getMorphClass(),
            'linkable_id' => $record->getKey(),
            'title' => null,
            'url' => null,
        ]);
    }

    public function indexOf(string $source): static
    {
        return $this->state([
            'type' => MenuItemType::Index,
            'source' => $source,
            'title' => null,
            'url' => null,
        ]);
    }
}
