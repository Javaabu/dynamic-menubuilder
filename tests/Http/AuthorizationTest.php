<?php

namespace Javaabu\DynamicMenubuilder\Tests\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Policies\MenuPolicy;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Policies\DenyAllPolicy;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use Javaabu\Permissions\Permissions;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class AuthorizationTest extends TestCase
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
        });
    }

    #[Test]
    public function it_redirects_guests_to_log_in(): void
    {
        $this->get('/menus')->assertRedirect('/login');
        $this->getJson('/menus')->assertUnauthorized();
    }

    #[Test]
    public function it_needs_view_menus_to_list_them(): void
    {
        $this->actingAs($this->userWith([]))->get('/menus')->assertForbidden();
        $this->actingAs($this->userWith(['view_menus']))->get('/menus')->assertOk()->assertDontSee('New menu');
    }

    #[Test]
    public function it_needs_edit_menus_to_create_and_update(): void
    {
        $menu = Menu::factory()->create();
        $viewer = $this->userWith(['view_menus']);

        $this->actingAs($viewer)->get('/menus/create')->assertForbidden();
        $this->actingAs($viewer)->post('/menus', ['name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->get("/menus/{$menu->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->patch("/menus/{$menu->id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->getJson('/menus/linkables/pages')->assertForbidden();

        $editor = $this->userWith(['view_menus', 'edit_menus']);

        $this->actingAs($editor)->get('/menus/create')->assertOk();
        $this->actingAs($editor)->get("/menus/{$menu->id}/edit")->assertOk()->assertDontSee('data-menu-actions', false);
        $this->actingAs($editor)->getJson('/menus/linkables/pages')->assertOk();
    }

    #[Test]
    public function it_needs_delete_menus_to_delete(): void
    {
        $menu = Menu::factory()->create();

        $this->actingAs($this->userWith(['view_menus', 'edit_menus']))->delete("/menus/{$menu->id}")->assertForbidden();
        $this->actingAs($this->userWith(['delete_menus']))->delete("/menus/{$menu->id}")->assertRedirect('/menus');
    }

    #[Test]
    public function it_registers_the_permissions_and_policy(): void
    {
        Permissions::sync();

        $permission_class = config('permission.models.permission');

        $this->assertEqualsCanonicalizing(
            ['view_menus', 'edit_menus', 'delete_menus'],
            $permission_class::query()->where('model', 'menus')->pluck('name')->unique()->all(),
        );
        $this->assertInstanceOf(MenuPolicy::class, Gate::getPolicyFor(Menu::class));
    }

    #[Test]
    public function it_lets_the_app_override_the_policy(): void
    {
        Gate::policy(Menu::class, DenyAllPolicy::class);

        $this->actingAs($this->menusAdmin())->get('/menus')->assertForbidden();
    }

    protected function overridePolicyInConfig($app): void
    {
        $app['config']->set('permissions.policies', [Menu::class => DenyAllPolicy::class]);
    }

    protected function skipPermissions($app): void
    {
        $app['config']->set('dynamic-menubuilder.register_permissions', false);
    }

    #[Test]
    #[DefineEnvironment('overridePolicyInConfig')]
    public function it_lets_the_app_override_the_policy_from_config(): void
    {
        $this->assertInstanceOf(DenyAllPolicy::class, Gate::getPolicyFor(Menu::class));
    }

    #[Test]
    #[DefineEnvironment('skipPermissions')]
    public function it_can_skip_registering_permissions(): void
    {
        $this->assertNull(Permissions::group('menus'));
    }
}
