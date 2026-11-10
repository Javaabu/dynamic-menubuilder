<?php

namespace Javaabu\DynamicMenubuilder\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\DynamicMenubuilderServiceProvider;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\MenuManager;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Models\MenuLocationAssignment;
use Javaabu\DynamicMenubuilder\Stencil\MenuItemsField;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use Javaabu\DynamicMenubuilder\Views\ViewRenderer;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Stencil\Facades\Stencil;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_config_and_binds_the_manager(): void
    {
        $this->assertSame('blade', config('dynamic-menubuilder.views'));
        $this->assertSame(3, config('dynamic-menubuilder.max_depth'));
        $this->assertSame(app(MenuManager::class), app(MenuManager::class));
        $this->assertInstanceOf(MenuManager::class, Menus::getFacadeRoot());
    }

    #[Test]
    public function it_registers_the_publish_tags(): void
    {
        foreach (['config', 'migrations', 'translations', 'views'] as $tag) {
            $this->assertNotEmpty(
                ServiceProvider::pathsToPublish(DynamicMenubuilderServiceProvider::class, "dynamic-menubuilder-{$tag}"),
                "Missing publish tag dynamic-menubuilder-{$tag}",
            );
        }
    }

    #[Test]
    public function it_registers_the_route_macro_the_stencil_view_and_the_assets(): void
    {
        $this->assertTrue(Route::hasMacro('menus'));
        $this->assertSame('dynamic-menubuilder::stencil.menu-items', Stencil::viewFor(MenuItemsField::TYPE));
        $this->assertTrue(Manifest::hasPackage('dynamic-menubuilder'));
        $this->assertTrue(Manifest::has('dynamic-menubuilder::builder'));
    }

    #[Test]
    public function it_resolves_models_and_tables_from_config(): void
    {
        $this->assertSame(Menu::class, DynamicMenubuilder::menuModel());
        $this->assertSame(MenuItem::class, DynamicMenubuilder::menuItemModel());
        $this->assertSame(MenuLocationAssignment::class, DynamicMenubuilder::menuLocationModel());
        $this->assertSame('menus', DynamicMenubuilder::table('menus'));

        config(['dynamic-menubuilder.tables.menus' => 'site_menus']);
        $this->assertSame('site_menus', DynamicMenubuilder::table('menus'));
        $this->assertSame('site_menus', (new Menu)->getTable());

        $this->expectException(InvalidArgumentException::class);
        DynamicMenubuilder::model('widget');
    }

    #[Test]
    public function it_can_skip_the_migrations(): void
    {
        DynamicMenubuilder::ignoreMigrations();

        try {
            $this->assertFalse(DynamicMenubuilder::$runs_migrations);
        } finally {
            DynamicMenubuilder::$runs_migrations = true;
        }
    }

    #[Test]
    public function it_names_the_inertia_pages(): void
    {
        $renderer = new ViewRenderer;

        $this->assertSame('dynamic-menubuilder::Menus/Index', $renderer->inertiaPage('menus.index'));
        $this->assertFalse($renderer->isInertia());
    }

    #[Test]
    public function it_describes_locations(): void
    {
        $location = Menus::location('footer')->description(fn () => 'Shown at the bottom');

        $this->assertSame('Footer', $location->getLabel());
        $this->assertSame(['key' => 'footer', 'label' => 'Footer', 'description' => 'Shown at the bottom', 'max_depth' => 3], $location->toArray());
        $this->assertSame(1, $location->maxDepth(0)->getMaxDepth());
        $this->assertNull($location->getCan());
    }
}
