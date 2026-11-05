<?php

namespace Javaabu\DynamicMenubuilder\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;
use Javaabu\DynamicMenubuilder\Http\Controllers\MenusController;
use Javaabu\DynamicMenubuilder\Models\Menu;

class CustomMenusController extends MenusController
{
    protected function saving(Request $request, Menu $menu, array $data): void
    {
        $menu->name = mb_strtoupper($menu->name);
    }
}
