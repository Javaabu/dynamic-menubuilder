<?php

namespace Javaabu\DynamicMenubuilder\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Javaabu\DynamicMenubuilder\Models\Menu;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
        ];
    }
}
