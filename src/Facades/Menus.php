<?php

namespace Javaabu\DynamicMenubuilder\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Javaabu\DynamicMenubuilder\Linkables\LinkableSource;
use Javaabu\DynamicMenubuilder\Locations\MenuLocation;
use Javaabu\DynamicMenubuilder\MenuManager;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Rendering\MenuNode;

/**
 * @method static MenuLocation location(string $key, ?string $label = null)
 * @method static MenuLocation addLocation(MenuLocation $location)
 * @method static bool hasLocation(string $key)
 * @method static MenuLocation getLocation(string $key)
 * @method static array<string, MenuLocation> locations()
 * @method static LinkableSource linkable(string $model, ?string $key = null)
 * @method static LinkableSource addLinkable(LinkableSource $source)
 * @method static bool hasLinkable(string $key)
 * @method static LinkableSource getLinkable(string $key)
 * @method static array<string, LinkableSource> linkables()
 * @method static LinkableSource|null sourceFor(Model $record)
 * @method static Menu|null menuFor(string $location)
 * @method static void assign(string $location, Menu|int|null $menu)
 * @method static array<int, MenuNode> items(string $location, ?string $locale = null, Authenticatable|false|null $user = null, ?Request $request = null)
 * @method static array<string, array<int, array<string, mixed>>> shared(?array $locations = null, ?string $locale = null)
 * @method static void flush()
 * @method static void reset()
 *
 * @see MenuManager
 */
class Menus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MenuManager::class;
    }
}
