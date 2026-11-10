<?php

namespace Javaabu\DynamicMenubuilder\Tests\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The Inertia pages' props, and the shared menus. Also writes the JSON the React tests
 * render: run with DYNAMIC_MENUBUILDER_UPDATE_FIXTURES=1 to rewrite tests/js/fixtures.
 */
class InertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! class_exists(Inertia::class)) {
            $this->markTestSkipped('inertiajs/inertia-laravel is not installed.');
        }

        parent::setUp();

        Carbon::setTestNow('2026-11-05 10:00:00');

        config(['dynamic-menubuilder.views' => 'inertia']);

        $this->registerMenus();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The pages are package files (resources/js/Pages), resolved by javaabu/manifest
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('dynamic-menubuilder.share', ['header']);
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InertiaServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(['web', Middleware::class])->group(function () {
            Route::menus(['prefix' => 'admin/menus', 'name' => 'admin.menus']);
            Route::get('/about', fn () => Inertia::render('About'));
        });
    }

    protected function seedMenu(): Menu
    {
        $menu = Menu::factory()->create(['name' => 'މައި މެނޫ']);
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);

        MenuItem::factory()->for($menu)->create(['title' => 'Home', 'url' => '/']);
        $services = MenuItem::factory()->for($menu)->heading()->create(['title' => 'ޚިދުމަތްތައް']);
        MenuItem::factory()->childOf($services)->linkTo($page, 'pages')->create();
        MenuItem::factory()->for($menu)->indexOf('news')->create(['new_tab' => true]);

        Menus::assign('header', $menu);

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function fixture(string $file, array $props): void
    {
        $path = __DIR__ . "/../js/fixtures/{$file}";
        $json = json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        if (getenv('DYNAMIC_MENUBUILDER_UPDATE_FIXTURES') || ! is_file($path)) {
            file_put_contents($path, $json);
        }

        $this->assertEquals(
            $this->shape(json_decode(file_get_contents($path), true)),
            $this->shape($props),
            "tests/js/fixtures/{$file} is out of date: run DYNAMIC_MENUBUILDER_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter InertiaPagesTest",
        );
    }

    /**
     * The keys of a JSON value, recursively: what the React pages rely on
     */
    protected function shape(mixed $value): mixed
    {
        if (! is_array($value)) {
            return get_debug_type($value) === 'null' ? 'null' : 'scalar';
        }

        return array_map(fn ($item) => $this->shape($item), $value);
    }

    #[Test]
    public function it_renders_the_index_page(): void
    {
        $this->seedMenu();

        $this->actingAs($this->menusAdmin())
            ->get('/admin/menus')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('dynamic-menubuilder::Menus/Index')
                    ->where('title', 'Menus')
                    ->has('rows', 1)
                    ->where('rows.0.name', 'މައި މެނޫ')
                    ->where('rows.0.items_count', 4)
                    ->where('rows.0.locations', ['Header'])
                    ->where('locations.0.menu.name', 'މައި މެނޫ')
                    ->where('locations.1.menu', null)
                    ->where('strings.new', 'New menu')
                    ->where('menus.total', 1)
                    ->where('can.create', true);

                $this->fixture('index.json', $page->toArray()['props']);
            });
    }

    #[Test]
    public function it_renders_the_form_page(): void
    {
        $menu = $this->seedMenu();

        $this->actingAs($this->menusAdmin())
            ->get("/admin/menus/{$menu->id}/edit")
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($menu) {
                $page->component('dynamic-menubuilder::Menus/Form')
                    ->where('menu.id', $menu->id)
                    ->where('form.method', 'PATCH')
                    ->where('form.state.name', 'މައި މެނޫ')
                    ->where('form.state.locations', ['header'])
                    ->has('form.state.items', 4)
                    ->where('form.state.items.2.linkable_label', 'About us')
                    ->where('urls.search', 'http://localhost/admin/menus/linkables/{source}')
                    ->where('can.delete', true);

                $this->fixture('form.json', $page->toArray()['props']);
            });
    }

    #[Test]
    public function it_renders_the_create_page(): void
    {
        $this->actingAs($this->menusAdmin())
            ->get('/admin/menus/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('dynamic-menubuilder::Menus/Form')
                ->where('menu', null)
                ->where('form.method', 'POST')
                ->where('form.state.items', [])
                ->where('title', 'New menu'));
    }

    #[Test]
    public function it_renders_in_dhivehi(): void
    {
        $menu = $this->seedMenu();

        app()->setLocale('dv');

        $this->actingAs($this->menusAdmin())
            ->get("/admin/menus/{$menu->id}/edit")
            ->assertInertia(function (AssertableInertia $page) {
                $page->where('strings.back', 'މެނޫތަކަށް އެނބުރި');

                $this->fixture('form.dv.json', $page->toArray()['props']);
            });
    }

    #[Test]
    public function it_shares_the_configured_locations_menus(): void
    {
        $this->seedMenu();

        $this->get('/about')
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('About')
                    ->has('menus.header', 3)
                    ->missing('menus.footer')
                    ->where('menus.header.0.title', 'Home')
                    ->where('menus.header.1.children.0.title', 'About us')
                    ->where('menus.header.2.target', '_blank');

                $this->fixture('shared.json', $page->toArray()['props']['menus']);
            });
    }
}
