<?php

namespace Javaabu\DynamicMenubuilder\Tests\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Rendering\MenuNode;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Controllers\CustomMenusController;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MultiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMenus();
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/login', fn () => 'login')->name('login');

            Route::menus();

            Route::prefix('admin')->name('admin.')->group(function () {
                Route::menus(['guard' => 'web_admin', 'controllers' => ['menus' => CustomMenusController::class]]);
            });
        });
    }

    #[Test]
    public function it_authorizes_admins_on_their_guard(): void
    {
        $menu = Menu::factory()->create(['name' => 'Main']);

        $this->actingAs($this->menusAdmin('web_admin'), 'web_admin')
            ->get('/admin/menus')
            ->assertOk()
            ->assertSee("/admin/menus/{$menu->id}/edit", false);
    }

    #[Test]
    public function it_keeps_users_out_of_the_admin_guards_pages(): void
    {
        $this->actingAs($this->menusAdmin('web'), 'web')->get('/admin/menus')->assertRedirect('/login');
    }

    #[Test]
    public function it_keeps_admins_out_of_the_user_guards_pages(): void
    {
        $this->actingAs($this->menusAdmin('web_admin'), 'web_admin')->get('/menus')->assertRedirect('/login');
    }

    #[Test]
    public function it_uses_the_permissions_of_the_routes_guard(): void
    {
        $this->actingAs($this->userWith(['view_menus'], 'web_admin'), 'web_admin')->get('/admin/menus')->assertOk();
        $this->actingAs($this->userWith([], 'web_admin'), 'web_admin')->get('/admin/menus')->assertForbidden();
    }

    #[Test]
    public function it_uses_the_controller_given_to_the_macro_and_stays_in_its_prefix(): void
    {
        $this->actingAs($this->menusAdmin('web_admin'), 'web_admin')
            ->post('/admin/menus', ['name' => 'main'])
            ->assertRedirect('/admin/menus/' . Menu::query()->value('id') . '/edit');

        $this->assertSame('MAIN', Menu::query()->value('name'));

        $this->actingAs($this->menusAdmin('web_admin'), 'web_admin')
            ->get('/admin/menus/' . Menu::query()->value('id') . '/edit')
            ->assertSee('data-search-url="http://localhost/admin/menus/linkables/{source}"', false);
    }

    #[Test]
    public function it_uses_the_controller_from_config(): void
    {
        config(['dynamic-menubuilder.controllers.menus' => CustomMenusController::class]);
        Route::middleware('web')->prefix('other')->name('other.')->group(fn () => Route::menus());

        $this->actingAs($this->menusAdmin())->post('/other/menus', ['name' => 'footer']);

        $this->assertSame('FOOTER', Menu::query()->value('name'));
    }

    #[Test]
    public function it_registers_routes_without_a_name_prefix(): void
    {
        Route::middleware('web')->group(fn () => Route::menus(['name' => '', 'prefix' => 'bare']));
        Route::getRoutes()->refreshNameLookups();

        $this->assertTrue(Route::has('index'));

        $this->actingAs($this->menusAdmin())
            ->post('/bare', ['name' => 'Bare'])
            ->assertRedirect('/bare/' . Menu::query()->value('id') . '/edit');
    }

    #[Test]
    public function it_checks_visibility_against_the_locations_guard(): void
    {
        Menus::location('admin-bar', 'Admin bar')->guard('web_admin');
        $menu = Menu::factory()->create();
        Menus::assign('admin-bar', $menu);
        MenuItem::factory()->for($menu)->create(['title' => 'Dashboard', 'visibility' => Visibility::Users]);

        $this->actingAs($this->userWith([], 'web'), 'web');
        $this->assertSame([], Menus::items('admin-bar'));

        $this->actingAs($this->userWith([], 'web_admin'), 'web_admin');
        Menus::flush();
        $this->assertSame(['Dashboard'], array_map(fn (MenuNode $node) => $node->title, Menus::items('admin-bar')));
    }
}
