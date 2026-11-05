<?php

namespace Javaabu\DynamicMenubuilder\Tests\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class JsonApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMenus();
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(fn () => Route::menus());
    }

    #[Test]
    public function it_lists_menus_and_locations(): void
    {
        $menu = Menu::factory()->create(['name' => 'މައި މެނޫ']);
        MenuItem::factory()->for($menu)->count(2)->create();
        Menus::assign('header', $menu);

        $this->actingAs($this->menusAdmin())
            ->getJson('/menus')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'މައި މެނޫ')
            ->assertJsonPath('data.0.items_count', 2)
            ->assertJsonPath('data.0.locations', ['Header'])
            ->assertJsonPath('data.0.can.delete', true)
            ->assertJsonPath('locations.0.key', 'header')
            ->assertJsonPath('locations.0.menu.name', 'މައި މެނޫ')
            ->assertJsonPath('locations.1.menu', null)
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function it_creates_shows_updates_and_deletes(): void
    {
        $admin = $this->menusAdmin();

        $id = $this->actingAs($admin)
            ->postJson('/menus', [
                'name' => 'Footer',
                'locations' => ['footer'],
                'items' => [['key' => 'a', 'type' => 'link', 'title' => 'ގުޅުއްވުމަށް', 'url' => '/contact']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.locations', ['footer'])
            ->assertJsonPath('data.items.0.title', 'ގުޅުއްވުމަށް')
            ->json('data.id');

        $this->actingAs($admin)->getJson("/menus/{$id}")->assertOk()->assertJsonPath('data.name', 'Footer');

        $this->actingAs($admin)
            ->getJson("/menus/{$id}/edit")
            ->assertOk()
            ->assertJsonPath('data.form.stencil', 1)
            ->assertJsonPath('data.form.state.items.0.title', 'ގުޅުއްވުމަށް');

        $this->actingAs($admin)->getJson('/menus/create')->assertOk()->assertJsonPath('data.form.method', 'POST');

        $this->actingAs($admin)
            ->patchJson("/menus/{$id}", ['name' => 'Footer links', 'locations' => ['footer'], 'items' => []])
            ->assertOk()
            ->assertJsonPath('data.items', []);

        $this->actingAs($admin)->deleteJson("/menus/{$id}")->assertNoContent();
        $this->assertSame(0, Menu::query()->count());
    }

    #[Test]
    public function it_answers_validation_errors_as_json(): void
    {
        $this->actingAs($this->menusAdmin())
            ->postJson('/menus', ['name' => 'x', 'locations' => ['footer'], 'items' => [
                ['key' => 'a', 'type' => 'link', 'title' => 'A'],
                ['key' => 'b', 'parent' => 'a', 'type' => 'link', 'title' => 'B'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    #[Test]
    public function it_404s_for_a_missing_menu(): void
    {
        $this->actingAs($this->menusAdmin())->getJson('/menus/999')->assertNotFound();
    }
}
