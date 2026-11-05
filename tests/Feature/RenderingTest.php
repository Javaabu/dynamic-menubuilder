<?php

namespace Javaabu\DynamicMenubuilder\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Rendering\MenuNode;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Post;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\User;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMenus();
    }

    protected function headerMenu(): Menu
    {
        $menu = Menu::factory()->create(['name' => 'Main']);
        Menus::assign('header', $menu);

        return $menu;
    }

    #[Test]
    public function it_resolves_a_locations_items_as_a_tree(): void
    {
        $menu = $this->headerMenu();
        $about = Page::query()->create(['title' => 'About us', 'slug' => 'about']);

        $home = MenuItem::factory()->for($menu)->create(['title' => 'ފުރަތަމަ ސަފްހާ', 'url' => '/', 'order' => 0]);
        $services = MenuItem::factory()->for($menu)->heading()->create(['title' => 'Services', 'order' => 1]);
        MenuItem::factory()->childOf($services)->linkTo($about, 'pages')->create(['order' => 0]);
        MenuItem::factory()->for($menu)->indexOf('news')->create(['order' => 2]);

        $items = Menus::items('header');

        $this->assertCount(3, $items);
        $this->assertSame('ފުރަތަމަ ސަފްހާ', $items[0]->title);
        $this->assertSame('/', $items[0]->url);
        $this->assertTrue($items[1]->isHeading());
        $this->assertSame('About us', $items[1]->children[0]->title);
        $this->assertSame(url('pages/about'), $items[1]->children[0]->url);
        $this->assertSame(2, $items[1]->children[0]->depth);
        $this->assertSame('News', $items[2]->title);
        $this->assertSame(url('news'), $items[2]->url);
        $this->assertSame($home->id, $items[0]->id);
    }

    #[Test]
    public function it_returns_nothing_for_a_location_without_a_menu(): void
    {
        $this->assertSame([], Menus::items('header'));
        $this->assertNull(Menus::menuFor('header'));
    }

    #[Test]
    public function it_throws_for_an_unknown_location(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Menus::items('sidebar');
    }

    #[Test]
    public function it_leaves_out_items_deeper_than_the_location_allows(): void
    {
        $menu = $this->headerMenu();
        Menus::assign('footer', $menu);

        $parent = MenuItem::factory()->for($menu)->create(['title' => 'Parent']);
        $child = MenuItem::factory()->childOf($parent)->create(['title' => 'Child']);
        MenuItem::factory()->childOf($child)->create(['title' => 'Grandchild']);

        $header = Menus::items('header');
        $this->assertCount(1, $header[0]->children);
        $this->assertSame([], $header[0]->children[0]->children);

        $this->assertSame([], Menus::items('footer')[0]->children);
    }

    #[Test]
    public function it_hides_unpublished_deleted_and_unregistered_records(): void
    {
        $menu = $this->headerMenu();
        $draft = Page::query()->create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);
        $post = Post::query()->create(['type' => 'news', 'name' => 'Budget 2027']);

        MenuItem::factory()->for($menu)->linkTo($draft, 'pages')->create();
        MenuItem::factory()->for($menu)->linkTo($post, 'news')->create();
        MenuItem::factory()->for($menu)->linkTo($post, 'unknown')->create();
        MenuItem::factory()->for($menu)->indexOf('events')->create();

        $this->assertSame(['Budget 2027'], array_map(fn (MenuNode $node) => $node->title, Menus::items('header')));

        $post->delete();
        Menus::flush();

        $this->assertSame([], Menus::items('header'));
    }

    #[Test]
    public function it_hides_a_parents_children_with_it(): void
    {
        $menu = $this->headerMenu();
        $parent = MenuItem::factory()->for($menu)->create(['visibility' => Visibility::Users]);
        MenuItem::factory()->childOf($parent)->create();

        $this->assertSame([], Menus::items('header'));
    }

    #[Test]
    public function it_shows_items_to_guests_or_users(): void
    {
        $menu = $this->headerMenu();
        MenuItem::factory()->for($menu)->create(['title' => 'Log in', 'visibility' => Visibility::Guests]);
        MenuItem::factory()->for($menu)->create(['title' => 'My account', 'visibility' => Visibility::Users]);

        $this->assertSame(['Log in'], array_map(fn (MenuNode $node) => $node->title, Menus::items('header')));

        $this->actingAs(User::query()->create(['name' => 'މަރިޔަމް']));
        Menus::flush();

        $this->assertSame(['My account'], array_map(fn (MenuNode $node) => $node->title, Menus::items('header')));
    }

    #[Test]
    public function it_checks_the_locations_can(): void
    {
        Menus::location('staff', 'Staff links')->can(fn ($user) => $user?->name === 'އައިޝަތު');
        $menu = Menu::factory()->create();
        Menus::assign('staff', $menu);
        MenuItem::factory()->for($menu)->create(['title' => 'Leave']);

        $this->assertSame([], Menus::items('staff'));

        $this->actingAs(User::query()->create(['name' => 'އައިޝަތު']));

        $this->assertCount(1, Menus::items('staff'));
    }

    #[Test]
    public function it_checks_a_gate_ability(): void
    {
        Menus::location('staff', 'Staff links')->can('see-staff-links');
        Gate::define('see-staff-links', fn ($user) => $user->name === 'ޙަސަން');
        $menu = Menu::factory()->create();
        Menus::assign('staff', $menu);
        MenuItem::factory()->for($menu)->create();

        $this->assertSame([], Menus::items('staff'));
        $this->assertSame([], Menus::items('staff', user: User::query()->create(['name' => 'Ali'])));
        $this->assertCount(1, Menus::items('staff', user: User::query()->create(['name' => 'ޙަސަން'])));
    }

    #[Test]
    public function it_limits_items_to_their_locales(): void
    {
        $menu = $this->headerMenu();
        MenuItem::factory()->for($menu)->create(['title' => 'English only', 'locales' => ['en']]);
        MenuItem::factory()->for($menu)->create(['title' => 'ހުރިހާ ބަހެއްގައި']);

        $this->assertCount(2, Menus::items('header', 'en'));
        $this->assertSame(['ހުރިހާ ބަހެއްގައި'], array_map(fn (MenuNode $node) => $node->title, Menus::items('header', 'dv')));
    }

    #[Test]
    public function it_marks_the_current_and_active_items(): void
    {
        $menu = $this->headerMenu();
        $news = MenuItem::factory()->for($menu)->indexOf('news')->create();
        MenuItem::factory()->childOf($news)->create(['title' => 'Archive', 'url' => '/news/archive']);
        MenuItem::factory()->for($menu)->create(['title' => 'Home', 'url' => '/']);
        MenuItem::factory()->for($menu)->create(['title' => 'Elsewhere', 'url' => 'https://example.org/news/archive']);

        $items = Menus::items('header', request: Request::create('/news/archive'));

        $this->assertTrue($items[0]->active);
        $this->assertFalse($items[0]->current);
        $this->assertTrue($items[0]->children[0]->current);
        $this->assertFalse($items[1]->active);
        $this->assertFalse($items[2]->active);

        $items = Menus::items('header', request: Request::create('/news/budget-2027'));
        $this->assertTrue($items[0]->active);
        $this->assertFalse($items[0]->children[0]->active);
    }

    #[Test]
    public function it_serializes_nodes(): void
    {
        $menu = $this->headerMenu();
        MenuItem::factory()->for($menu)->create(['title' => 'Partner', 'url' => 'https://example.org', 'new_tab' => true, 'rel' => 'sponsored', 'css_class' => 'btn', 'icon' => 'fa-star']);

        $node = Menus::items('header')[0]->toArray();

        $this->assertSame('_blank', $node['target']);
        $this->assertSame('sponsored noopener', $node['rel']);
        $this->assertSame('btn', $node['css_class']);
        $this->assertSame('fa-star', $node['icon']);
        $this->assertSame([], $node['children']);
        $this->assertSame(['header' => [$node], 'footer' => []], Menus::shared());
    }

    #[Test]
    public function it_renders_the_blade_component(): void
    {
        $menu = $this->headerMenu();
        $parent = MenuItem::factory()->for($menu)->heading()->create(['title' => 'ޚިދުމަތްތައް']);
        MenuItem::factory()->childOf($parent)->create(['title' => 'Permits', 'url' => '/permits', 'new_tab' => true]);

        $this->get('/'); // a request for active state

        $html = Blade::render('<x-dynamic-menubuilder::menu location="header" class="main-nav" />');

        $this->assertStringContainsString('<nav class="dynamic-menu main-nav" aria-label="Header" data-menu-location="header">', $html);
        $this->assertStringContainsString('ޚިދުމަތްތައް', $html);
        $this->assertStringContainsString('href="/permits"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
        $this->assertStringContainsString('data-menu-depth="2"', $html);
    }

    #[Test]
    public function it_renders_nothing_for_an_empty_location(): void
    {
        $this->assertSame('', trim(Blade::render('<x-dynamic-menubuilder::menu location="footer" />')));
    }

    #[Test]
    public function it_renders_the_blade_component_with_a_custom_view(): void
    {
        $menu = $this->headerMenu();
        MenuItem::factory()->for($menu)->create(['title' => 'Contact']);

        $html = Blade::render('<x-dynamic-menubuilder::menu location="header" view="custom-menu" label="Main" />');

        $this->assertSame('Main: Contact', trim($html));
    }

    #[Test]
    public function it_loads_a_menu_once_per_request(): void
    {
        $menu = $this->headerMenu();
        MenuItem::factory()->for($menu)->count(3)->create();

        Menus::items('header');

        DB::enableQueryLog();
        Menus::items('header');
        Menus::items('header');

        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_registers_locations_and_sources_from_config(): void
    {
        config([
            'dynamic-menubuilder.locations' => ['sidebar' => ['label' => 'Sidebar', 'max_depth' => 1], 'mobile' => 'Mobile'],
            'dynamic-menubuilder.linkables' => [Page::class, 'stories' => ['model' => Post::class, 'label' => 'Stories']],
        ]);

        Menus::reset();

        $this->assertSame(1, Menus::getLocation('sidebar')->getMaxDepth());
        $this->assertSame('Mobile', Menus::getLocation('mobile')->getLabel());
        $this->assertSame(3, Menus::getLocation('mobile')->getMaxDepth());
        $this->assertSame(['pages', 'stories'], array_keys(Menus::linkables()));
        $this->assertSame('Stories', Menus::getLinkable('stories')->getLabel());
    }
}
