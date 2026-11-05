<?php

namespace Javaabu\DynamicMenubuilder\Tests\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BladePagesTest extends TestCase
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
            Route::menus(['prefix' => 'admin/menus', 'name' => 'admin.menus']);
        });
    }

    #[Test]
    public function it_lists_the_menus_and_locations(): void
    {
        $main = Menu::factory()->create(['name' => 'މައި މެނޫ']);
        MenuItem::factory()->for($main)->count(2)->create();
        Menu::factory()->create(['name' => 'Footer links']);
        Menus::assign('header', $main);

        $this->actingAs($this->menusAdmin())
            ->get('/admin/menus')
            ->assertOk()
            ->assertSee('މައި މެނޫ')
            ->assertSee('Footer links')
            ->assertSee('data-menu-location-row="header"', false)
            ->assertSee('data-menu-location-row="footer"', false)
            ->assertSee("/admin/menus/{$main->id}/edit", false)
            ->assertSee('New menu');
    }

    #[Test]
    public function it_searches_the_menus(): void
    {
        Menu::factory()->create(['name' => 'Header menu']);
        Menu::factory()->create(['name' => 'ފޫޓަރ']);

        $this->actingAs($this->menusAdmin())
            ->get('/admin/menus?search=' . rawurlencode('ފޫ'))
            ->assertOk()
            ->assertSee('ފޫޓަރ')
            ->assertDontSee('Header menu');
    }

    #[Test]
    public function it_renders_the_builder(): void
    {
        $menu = Menu::factory()->create(['name' => 'Main']);
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        $services = MenuItem::factory()->for($menu)->heading()->create(['title' => 'ޚިދުމަތްތައް']);
        MenuItem::factory()->childOf($services)->linkTo($page, 'pages')->create();
        Menus::assign('header', $menu);

        $this->actingAs($this->menusAdmin())
            ->get("/admin/menus/{$menu->id}/edit")
            ->assertOk()
            ->assertSee('data-menu-builder', false)
            ->assertSee('data-search-url="http://localhost/admin/menus/linkables/{source}"', false)
            ->assertSee('data-location-depths=\'{"header":2,"footer":1}\'', false)
            ->assertSee('data-menu-builder-source="pages"', false)
            ->assertSee('data-menu-builder-source="news"', false)
            ->assertSee('ޚިދުމަތްތައް')
            ->assertSee('About us')
            ->assertSee('name="items[1][parent]" value="' . $services->id . '"', false)
            ->assertSee('data-depth="2"', false)
            ->assertSee('name="locations[]"', false)
            ->assertSee('Delete')
            // the builder's script, from dist/ (the test app has no Vite build)
            ->assertSee('dynamic-menubuilder/assets/menu-builder-', false);
    }

    #[Test]
    public function it_creates_a_menu(): void
    {
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);

        $response = $this->actingAs($this->menusAdmin())
            ->post('/admin/menus', [
                'name' => 'މައި މެނޫ',
                'locations' => ['header'],
                'items' => [
                    ['key' => 'new-1', 'type' => 'link', 'title' => 'ފުރަތަމަ ސަފްހާ', 'url' => '/'],
                    ['key' => 'new-2', 'parent' => 'new-1', 'type' => 'model', 'source' => 'pages', 'linkable_id' => (string) $page->id],
                ],
            ]);

        $menu = Menu::query()->firstOrFail();

        $response->assertRedirect("/admin/menus/{$menu->id}/edit")->assertSessionHas('success', 'Menu created.');

        $this->assertSame('މައި މެނޫ', $menu->name);
        $this->assertSame(['header'], $menu->locationKeys());
        $this->assertSame(['ފުރަތަމަ ސަފްހާ'], array_map(fn ($node) => $node->title, Menus::items('header')));
        $this->assertSame('About us', Menus::items('header')[0]->children[0]->title);
    }

    #[Test]
    public function it_updates_a_menu_and_moves_locations(): void
    {
        $menu = Menu::factory()->create(['name' => 'Old']);
        $other = Menu::factory()->create();
        Menus::assign('footer', $other);
        $item = MenuItem::factory()->for($menu)->create(['title' => 'Old title']);
        MenuItem::factory()->for($menu)->create();

        $this->actingAs($this->menusAdmin())
            ->patch("/admin/menus/{$menu->id}", [
                'name' => 'New',
                'locations' => ['footer'],
                'items' => [
                    ['key' => (string) $item->id, 'id' => $item->id, 'type' => 'link', 'title' => 'ބަދަލުކުރި', 'url' => '/x'],
                ],
            ])
            ->assertRedirect("/admin/menus/{$menu->id}/edit")
            ->assertSessionHasNoErrors();

        $this->assertSame('New', $menu->refresh()->name);
        $this->assertSame(1, $menu->items()->count());
        $this->assertSame('ބަދަލުކުރި', $item->refresh()->title);
        $this->assertTrue(Menus::menuFor('footer')->is($menu));
        $this->assertSame([], $other->refresh()->locationKeys());
    }

    #[Test]
    public function it_rejects_items_deeper_than_the_locations_allow(): void
    {
        $menu = Menu::factory()->create(['name' => 'Footer']);

        $this->actingAs($this->menusAdmin())
            ->from("/admin/menus/{$menu->id}/edit")
            ->patch("/admin/menus/{$menu->id}", [
                'name' => 'Footer',
                'locations' => ['footer'],
                'items' => [
                    ['key' => 'a', 'type' => 'link', 'title' => 'Parent'],
                    ['key' => 'b', 'parent' => 'a', 'type' => 'link', 'title' => 'Child'],
                ],
            ])
            ->assertRedirect("/admin/menus/{$menu->id}/edit")
            ->assertSessionHasErrors('items');

        $this->assertSame(0, $menu->items()->count());
        $this->assertSame([], $menu->refresh()->locationKeys());
    }

    #[Test]
    public function it_validates_the_name_and_item_titles(): void
    {
        $this->actingAs($this->menusAdmin())
            ->post('/admin/menus', ['name' => '', 'items' => [['key' => 'a', 'type' => 'link', 'title' => '']]])
            ->assertSessionHasErrors(['name', 'items.0.title']);

        $this->assertSame(0, Menu::query()->count());
    }

    #[Test]
    public function it_keeps_old_input_in_the_builder_after_a_failed_save(): void
    {
        $menu = Menu::factory()->create();

        $this->actingAs($this->menusAdmin())
            ->withSession(['_old_input' => ['name' => 'x', 'items' => [
                ['key' => 'new-1', 'type' => 'link', 'title' => 'ޓެސްޓް', 'url' => '/t'],
                ['key' => 'new-2', 'parent' => 'new-1', 'type' => 'link', 'title' => 'Child', 'url' => ''],
            ]]])
            ->get("/admin/menus/{$menu->id}/edit")
            ->assertOk()
            ->assertSee('value="ޓެސްޓް"', false)
            ->assertSee('name="items[1][parent]" value="new-1"', false);
    }

    #[Test]
    public function it_deletes_a_menu(): void
    {
        $menu = Menu::factory()->create();
        MenuItem::factory()->for($menu)->create();
        Menus::assign('header', $menu);

        $this->actingAs($this->menusAdmin())
            ->delete("/admin/menus/{$menu->id}")
            ->assertRedirect('/admin/menus')
            ->assertSessionHas('success', 'Menu deleted.');

        $this->assertSame(0, Menu::query()->count());
        $this->assertSame(0, MenuItem::query()->count());
        $this->assertNull(Menus::menuFor('header'));
    }

    #[Test]
    public function it_redirects_show_to_edit(): void
    {
        $menu = Menu::factory()->create();

        $this->actingAs($this->menusAdmin())
            ->get("/admin/menus/{$menu->id}")
            ->assertRedirect("/admin/menus/{$menu->id}/edit");
    }

    #[Test]
    public function it_searches_a_sources_records(): void
    {
        Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        Page::query()->create(['title' => 'ގުޅުއްވުމަށް', 'slug' => 'contact']);

        $this->actingAs($this->menusAdmin())
            ->getJson('/admin/menus/linkables/pages?search=' . rawurlencode('ގުޅު'))
            ->assertOk()
            ->assertJsonPath('data.0.label', 'ގުޅުއްވުމަށް')
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->menusAdmin())
            ->getJson('/admin/menus/linkables/unknown')
            ->assertNotFound();
    }

    /**
     * Writes the builder's HTML that tests/js/blade-builder.test.ts drives. Run with
     * DYNAMIC_MENUBUILDER_UPDATE_FIXTURES=1 to rewrite it.
     */
    #[Test]
    public function it_writes_the_blade_builder_fixture(): void
    {
        $menu = Menu::factory()->create(['name' => 'Main']);
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        MenuItem::factory()->for($menu)->create(['title' => 'Home', 'url' => '/']);
        $services = MenuItem::factory()->for($menu)->heading()->create(['title' => 'ޚިދުމަތްތައް']);
        MenuItem::factory()->childOf($services)->linkTo($page, 'pages')->create();
        MenuItem::factory()->for($menu)->create(['title' => 'Contact', 'url' => '/contact']);
        Menus::assign('header', $menu);

        $html = $this->actingAs($this->menusAdmin())->get("/admin/menus/{$menu->id}/edit")->assertOk()->getContent();

        preg_match('/<form\b.*<\/form>/s', $html, $matches);

        $form = preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value=""', $matches[0]) . "\n";
        $path = __DIR__ . '/../js/fixtures/blade-builder.html';

        if (getenv('DYNAMIC_MENUBUILDER_UPDATE_FIXTURES') || ! is_file($path)) {
            file_put_contents($path, $form);
        }

        $this->assertSame(file_get_contents($path), $form, 'tests/js/fixtures/blade-builder.html is out of date: run DYNAMIC_MENUBUILDER_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter fixture');
    }

    #[Test]
    public function it_renders_the_create_page_in_dhivehi(): void
    {
        app()->setLocale('dv');

        $this->actingAs($this->menusAdmin())
            ->get('/admin/menus/create')
            ->assertOk()
            ->assertSee('އައު މެނޫ')
            ->assertSee('މެނޫ ހަދާ')
            ->assertSee('އައިޓަމް އިތުރުކުރޭ')
            ->assertSee('dir="rtl"', false);
    }
}
