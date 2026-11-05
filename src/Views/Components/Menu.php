<?php

namespace Javaabu\DynamicMenubuilder\Views\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Rendering\MenuNode;

/**
 * `<x-dynamic-menubuilder::menu location="header" />`: the menu assigned to a location, as a
 * `<nav>` with nested lists, or with your own view
 */
class Menu extends Component
{
    /** @var array<int, MenuNode> */
    public array $items;

    public string $label;

    public function __construct(
        public string $location,
        public ?string $view = null,
        ?string $label = null,
        public ?string $locale = null,
    ) {
        $menu_location = Menus::getLocation($location);

        $this->items = Menus::items($location, $locale);
        $this->label = $label ?? $menu_location->getLabel();
    }

    public function render(): View
    {
        return view($this->view ?? 'dynamic-menubuilder::components.menu');
    }

    public function shouldRender(): bool
    {
        return $this->items !== [];
    }
}
