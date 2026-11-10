<?php

namespace Javaabu\DynamicMenubuilder\Tests\Translatable;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Models\Translatable\MenuItem as TranslatableMenuItem;
use Javaabu\DynamicMenubuilder\Rendering\MenuNode;
use Javaabu\DynamicMenubuilder\Support\MenuTree;
use Javaabu\DynamicMenubuilder\Support\Translations;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Translatable\Page;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use Javaabu\Translatable\Testing\InteractsWithLanguages;
use Javaabu\Translatable\Translatable;
use Javaabu\Translatable\TranslatableServiceProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * With javaabu/translatable installed and enabled. The rest of the suite runs without it.
 */
class TranslatableMenusTest extends TestCase
{
    use InteractsWithLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! class_exists(Translatable::class)) {
            $this->markTestSkipped('javaabu/translatable is not installed.');
        }

        parent::setUp();

        $this->setUpLanguages(['en', 'dv']);

        Menus::location('header', 'Header')->maxDepth(2);
        Menus::linkable(Page::class, 'pages')->label('Pages');
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), TranslatableServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(fn () => Route::menus());
    }

    /**
     * A header menu: a translated link, a heading with a page under it, and an English-only link
     */
    protected function seedMenu(): Menu
    {
        $menu = Menu::factory()->create();
        Menus::assign('header', $menu);

        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        $page->addTranslation('dv', 'title', 'އަޅުގަނޑުމެންގެ ވާހަކަ');

        MenuTree::sync($menu, [
            ['key' => 'contact', 'type' => 'link', 'title_en' => 'Contact', 'title_dv' => 'ގުޅުއްވުމަށް', 'url_en' => '/contact', 'url_dv' => '/dv/contact'],
            ['key' => 'more', 'type' => 'link', 'title_en' => 'More', 'title_dv' => ''],
            ['key' => 'about', 'parent' => 'more', 'type' => 'model', 'source' => 'pages', 'linkable_id' => (string) $page->id],
            ['key' => 'english', 'type' => 'link', 'title_en' => 'Careers', 'url_en' => '/careers', 'locales' => ['en']],
        ]);

        return $menu;
    }

    #[Test]
    public function it_uses_the_translatable_item_model(): void
    {
        $this->assertTrue(Translations::enabled());
        $this->assertSame(TranslatableMenuItem::class, DynamicMenubuilder::menuItemModel());
        $this->assertTrue(Translations::supports(DynamicMenubuilder::menuItemModel(), 'title'));
        $this->assertTrue(Translations::supports(DynamicMenubuilder::menuItemModel(), 'url'));
        $this->assertFalse(Translations::supports(DynamicMenubuilder::menuItemModel(), 'css_class'));
        $this->assertSame(MenuItem::class, (new TranslatableMenuItem)->getMorphClass());
        $this->assertSame(['en', 'dv'], Translations::locales());
        $this->assertSame('en', Translations::defaultLocale());
    }

    #[Test]
    public function it_uses_the_plain_model_when_translatable_is_off_or_the_app_sets_one(): void
    {
        $this->withoutTranslatable(fn () => $this->assertSame(MenuItem::class, DynamicMenubuilder::menuItemModel()));

        config(['dynamic-menubuilder.models.menu_item' => MenuItem::class]);
        $this->assertSame(MenuItem::class, DynamicMenubuilder::menuItemModel());
    }

    #[Test]
    public function it_saves_and_renders_each_locales_titles_and_links(): void
    {
        $this->seedMenu();

        $english = Menus::items('header', 'en');
        $this->assertSame(['Contact', 'More', 'Careers'], array_map(fn (MenuNode $node) => $node->title, $english));
        $this->assertSame('/contact', $english[0]->url);
        $this->assertSame('About us', $english[1]->children[0]->title);

        $dhivehi = Menus::items('header', 'dv');
        $this->assertSame(['ގުޅުއްވުމަށް', 'More'], array_map(fn (MenuNode $node) => $node->title, $dhivehi));
        $this->assertSame('/dv/contact', $dhivehi[0]->url);
        $this->assertSame('އަޅުގަނޑުމެންގެ ވާހަކަ', $dhivehi[1]->children[0]->title);
        $this->assertSame(url('dv/pages/about'), $dhivehi[1]->children[0]->url);
    }

    #[Test]
    public function it_syncs_structure_changes_to_every_translation(): void
    {
        $menu = $this->seedMenu();
        $list = MenuTree::toBuilder($menu);

        // Move "Careers" to the top, put "Contact" under "More", remove nothing, and add a link
        $careers = $list[3];
        $contact = $list[0];
        $more = $list[1];
        $about = $list[2];

        MenuTree::sync($menu, [
            $careers,
            $more,
            [...$contact, 'parent' => $more['key']],
            $about,
            ['key' => 'new', 'type' => 'link', 'title_en' => 'Events', 'title_dv' => 'ހަރަކާތްތައް', 'url_en' => '/events'],
        ]);

        Menus::flush();

        $shape = fn (array $nodes) => array_map(fn (MenuNode $node) => [$node->title, array_map(fn (MenuNode $child) => $child->title, $node->children)], $nodes);

        $this->assertSame([
            ['Careers', []],
            ['More', ['Contact', 'About us']],
            ['Events', []],
        ], $shape(Menus::items('header', 'en')));

        $this->assertSame([
            ['More', ['ގުޅުއްވުމަށް', 'އަޅުގަނޑުމެންގެ ވާހަކަ']],
            ['ހަރަކާތްތައް', []],
        ], $shape(Menus::items('header', 'dv')));

        // the Dhivehi link falls back to its English URL
        $this->assertSame('/events', Menus::items('header', 'dv')[1]->url);
    }

    #[Test]
    public function it_gives_the_builder_every_locales_values_and_rules(): void
    {
        $menu = $this->seedMenu();
        $list = MenuTree::toBuilder($menu);

        $this->assertSame('ގުޅުއްވުމަށް', $list[0]['title_dv']);
        $this->assertSame('/dv/contact', $list[0]['url_dv']);
        $this->assertSame('Contact', $list[0]['title_en']);
        $this->assertNull($list[1]['title_dv']);
        $this->assertSame('އަޅުގަނޑުމެންގެ ވާހަކަ', MenuTree::toBuilder($menu, 'dv')[2]['linkable_label']);
        $this->assertSame(['en'], $list[3]['locales']);

        $rules = MenuTree::rules();
        $this->assertContains('required_if:items.*.type,link', $rules['items.*.title_en']);
        $this->assertNotContains('required_if:items.*.type,link', $rules['items.*.title_dv']);
        $this->assertArrayNotHasKey('items.*.title', $rules);
    }

    #[Test]
    public function it_saves_through_the_builder_page(): void
    {
        $this->actingAs($this->menusAdmin())
            ->post('/menus', [
                'name' => 'Main',
                'locations' => ['header'],
                'items' => [['key' => 'a', 'type' => 'link', 'title_en' => 'Home', 'title_dv' => 'ފުރަތަމަ ސަފްހާ', 'url_en' => '/']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ފުރަތަމަ ސަފްހާ', Menus::items('header', 'dv')[0]->title);

        $this->actingAs($this->menusAdmin())
            ->post('/menus', ['name' => 'x', 'items' => [['key' => 'a', 'type' => 'link', 'title_dv' => 'ފުރަތަމަ']]])
            ->assertSessionHasErrors('items.0.title_en');
    }

    #[Test]
    public function it_renders_per_locale_inputs_in_the_blade_builder(): void
    {
        $menu = $this->seedMenu();

        $this->actingAs($this->menusAdmin())
            ->get("/menus/{$menu->id}/edit")
            ->assertOk()
            ->assertSee('name="items[0][title_dv]" value="ގުޅުއްވުމަށް"', false)
            ->assertSee('dir="rtl" lang="dv"', false)
            ->assertSee('name="items[0][url_en]"', false)
            ->assertSee('name="items[3][locales][]" value="en" checked', false);
    }

    #[Test]
    public function it_renders_the_blade_component_in_the_current_locale(): void
    {
        $this->seedMenu();

        app()->setLocale('dv');

        $html = Blade::render('<x-dynamic-menubuilder::menu location="header" />');

        $this->assertStringContainsString('ގުޅުއްވުމަށް', $html);
        $this->assertStringNotContainsString('Careers', $html);
    }
}
