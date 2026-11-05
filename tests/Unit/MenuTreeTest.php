<?php

namespace Javaabu\DynamicMenubuilder\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Javaabu\DynamicMenubuilder\Enums\MenuItemType;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Support\MenuTree;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Post;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MenuTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMenus();
    }

    #[Test]
    public function it_lists_a_menus_items_in_tree_order(): void
    {
        $menu = Menu::factory()->create();
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);

        $second = MenuItem::factory()->for($menu)->create(['title' => 'Second', 'order' => 1]);
        $first = MenuItem::factory()->for($menu)->create(['title' => 'First', 'order' => 0]);
        $child = MenuItem::factory()->childOf($first)->linkTo($page, 'pages')->create();
        MenuItem::factory()->for($menu)->indexOf('news')->create(['order' => 2]);

        $list = MenuTree::toBuilder($menu);

        $this->assertSame([(string) $first->id, (string) $child->id, (string) $second->id], array_slice(array_column($list, 'key'), 0, 3));
        $this->assertSame((string) $first->id, $list[1]['parent']);
        $this->assertSame('model', $list[1]['type']);
        $this->assertSame('About us', $list[1]['linkable_label']);
        $this->assertSame('Pages', $list[1]['source_label']);
        $this->assertSame((string) $page->id, $list[1]['linkable_id']);
        $this->assertFalse($list[1]['broken']);
        $this->assertSame('News', $list[3]['linkable_label']);
        $this->assertSame([], MenuTree::toBuilder(null));
    }

    #[Test]
    public function it_creates_updates_moves_and_deletes_items(): void
    {
        $menu = Menu::factory()->create();
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        $kept = MenuItem::factory()->for($menu)->create(['title' => 'Old title']);
        $removed = MenuItem::factory()->for($menu)->create();
        $removed_child = MenuItem::factory()->childOf($removed)->create();
        $other_menu_item = MenuItem::factory()->create();

        MenuTree::sync($menu, [
            ['key' => 'new-1', 'type' => 'link', 'title' => 'ޚިދުމަތްތައް', 'url' => '', 'visibility' => 'users'],
            ['key' => (string) $kept->id, 'id' => $kept->id, 'parent' => 'new-1', 'type' => 'link', 'title' => 'New title', 'url' => '/new', 'new_tab' => '1', 'locales' => ['dv']],
            ['key' => 'new-2', 'parent' => 'new-1', 'type' => 'model', 'source' => 'pages', 'linkable_id' => (string) $page->id, 'title' => ''],
            ['key' => 'new-3', 'type' => 'index', 'source' => 'news', 'url' => '/ignored'],
        ]);

        $items = $menu->items()->get();

        $this->assertCount(4, $items);
        $this->assertNull(MenuItem::query()->find($removed->id));
        $this->assertNull(MenuItem::query()->find($removed_child->id));
        $this->assertNotNull($other_menu_item->fresh());

        $heading = $items->firstWhere('title', 'ޚިދުމަތްތައް');
        $this->assertNull($heading->url);
        $this->assertSame(Visibility::Users, $heading->visibility);
        $this->assertNull($heading->parent_id);

        $kept->refresh();
        $this->assertSame('New title', $kept->title);
        $this->assertSame($heading->id, $kept->parent_id);
        $this->assertSame(0, $kept->order);
        $this->assertTrue($kept->new_tab);
        $this->assertSame(['dv'], $kept->locales);

        $linked = $items->firstWhere('type', MenuItemType::Model);
        $this->assertSame(1, $linked->order);
        $this->assertTrue($linked->linkable->is($page));
        $this->assertNull($linked->title);

        $index = $items->firstWhere('type', MenuItemType::Index);
        $this->assertNull($index->url);
        $this->assertSame(1, $index->order);
    }

    #[Test]
    public function it_empties_a_menu(): void
    {
        $menu = Menu::factory()->create();
        MenuItem::factory()->for($menu)->count(2)->create();

        MenuTree::sync($menu, null);

        $this->assertSame(0, $menu->items()->count());
    }

    #[Test]
    public function it_validates_depth_parents_sources_and_records(): void
    {
        $page = Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        $event = Post::query()->create(['type' => 'events', 'name' => 'Fair']);

        MenuTree::validate([
            ['key' => 'a', 'type' => 'link'],
            ['key' => 'b', 'parent' => 'a', 'type' => 'model', 'source' => 'pages', 'linkable_id' => (string) $page->id],
        ], 2);

        $this->assertValidationErrors(['items'], fn () => MenuTree::validate([
            ['key' => 'a', 'type' => 'link'],
            ['key' => 'b', 'parent' => 'a', 'type' => 'link'],
        ], 1));

        $this->assertValidationErrors(['items.0.parent'], fn () => MenuTree::validate([
            ['key' => 'b', 'parent' => 'a', 'type' => 'link'],
        ], 3));

        $this->assertValidationErrors(['items.0.source', 'items.1.source', 'items.2.linkable_id'], fn () => MenuTree::validate([
            ['key' => 'a', 'type' => 'model', 'source' => 'unknown', 'linkable_id' => '1'],
            ['key' => 'b', 'type' => 'index', 'source' => 'events'],
            ['key' => 'c', 'type' => 'model', 'source' => 'news', 'linkable_id' => (string) $event->id],
        ], 3));
    }

    #[Test]
    public function it_gives_rules_for_the_list(): void
    {
        $rules = MenuTree::rules();

        $this->assertTrue(Validator::make(['items' => [['key' => 'a', 'type' => 'link', 'title' => 'ގޭ']]], $rules)->passes());
        $this->assertTrue(Validator::make(['items' => []], $rules)->passes());

        $errors = Validator::make(['items' => [
            ['key' => 'a', 'type' => 'link'],
            ['key' => 'a', 'type' => 'model', 'title' => 'x'],
            ['key' => 'c', 'type' => 'other', 'visibility' => 'nobody'],
        ]], $rules)->errors();

        $this->assertTrue($errors->has('items.0.title'));
        $this->assertTrue($errors->has('items.1.key'));
        $this->assertTrue($errors->has('items.1.source'));
        $this->assertTrue($errors->has('items.1.linkable_id'));
        $this->assertTrue($errors->has('items.2.type'));
        $this->assertTrue($errors->has('items.2.visibility'));
    }

    #[Test]
    public function it_finds_the_max_depth_of_locations(): void
    {
        $this->assertSame(1, MenuTree::maxDepthFor(['header', 'footer']));
        $this->assertSame(2, MenuTree::maxDepthFor(['header', 'unknown']));
        $this->assertSame(3, MenuTree::maxDepthFor([]));
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function assertValidationErrors(array $keys, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertEqualsCanonicalizing($keys, array_keys($exception->errors()));

            return;
        }

        $this->fail('No validation errors were thrown.');
    }
}
