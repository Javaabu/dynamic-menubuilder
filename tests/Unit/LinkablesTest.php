<?php

namespace Javaabu\DynamicMenubuilder\Tests\Unit;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Linkables\LinkableSource;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Post;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\User;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LinkablesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_names_a_source_after_its_model(): void
    {
        $source = LinkableSource::make(Page::class);

        $this->assertSame('pages', $source->getKey());
        $this->assertSame('Pages', $source->getLabel());
        $this->assertSame(Page::class, $source->getModel());
    }

    #[Test]
    public function it_names_a_source_after_the_morph_alias(): void
    {
        Relation::morphMap(['page' => Page::class]);

        try {
            $this->assertSame('page', LinkableSource::make(Page::class)->getKey());
        } finally {
            Relation::morphMap([], false);
        }
    }

    #[Test]
    public function it_only_takes_linkable_models(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LinkableSource::make(User::class);
    }

    #[Test]
    public function it_scopes_the_records_of_a_source(): void
    {
        $this->registerMenus();

        $budget = Post::query()->create(['type' => 'news', 'name' => 'ބަޖެޓް 2027']);
        $fair = Post::query()->create(['type' => 'events', 'name' => 'Job fair']);

        $news = Menus::getLinkable('news');

        $this->assertTrue($news->contains($budget));
        $this->assertFalse($news->contains($fair));
        $this->assertNull($news->find($fair->id));
        $this->assertSame('events', Menus::sourceFor($fair)->getKey());
        $this->assertNull(Menus::sourceFor(Page::query()->create(['title' => 'Draft', 'slug' => 'draft'])->setAttribute('id', 999)));
    }

    #[Test]
    public function it_searches_a_source(): void
    {
        $this->registerMenus();

        Page::query()->create(['title' => 'About us', 'slug' => 'about']);
        Page::query()->create(['title' => 'ގުޅުއްވުމަށް', 'slug' => 'contact']);
        Post::query()->create(['type' => 'news', 'name' => 'About the budget']);

        $this->assertSame(['About us'], array_column(Menus::getLinkable('pages')->search('about'), 'label'));
        $this->assertSame(['ގުޅުއްވުމަށް'], array_column(Menus::getLinkable('pages')->search('ގުޅު'), 'label'));
        $this->assertSame(['About the budget'], array_column(Menus::getLinkable('news')->search('about'), 'label'));
        $this->assertCount(1, Menus::getLinkable('pages')->search(null, 1));
    }

    #[Test]
    public function it_resolves_index_urls(): void
    {
        $this->registerMenus();

        $this->assertSame(url('pages'), Menus::getLinkable('pages')->getIndexUrl());
        $this->assertSame(url('dv/news'), Menus::getLinkable('news')->getIndexUrl('dv'));
        $this->assertNull(Menus::getLinkable('events')->getIndexUrl());
        $this->assertFalse(Menus::getLinkable('events')->hasIndex());

        $source = LinkableSource::make(Page::class)->indexUrl(false);
        $this->assertNull($source->getIndexUrl());

        $source->indexUrl('/all-pages')->indexTitle('Every page');
        $this->assertSame('/all-pages', $source->getIndexUrl());
        $this->assertSame('Every page', $source->getIndexTitle());

        $this->assertSame(
            ['key' => 'pages', 'label' => 'Pages', 'has_index' => true, 'index_title' => 'Every page'],
            $source->toArray(),
        );
    }

    #[Test]
    public function it_gives_the_default_title_from_the_title_column(): void
    {
        $page = Page::query()->create(['title' => 'ޚަބަރު', 'slug' => 'news']);
        $post = Post::query()->create(['type' => 'news', 'name' => 'Budget']);

        $this->assertSame('ޚަބަރު', $page->getMenuLinkTitle());
        $this->assertSame('Budget', $post->getMenuLinkTitle());
        $this->assertTrue($post->isMenuLinkVisible());
        $this->assertNull(Post::getMenuLinkIndexUrl());
    }

    #[Test]
    public function it_removes_a_deleted_records_items_and_lifts_their_children(): void
    {
        $this->registerMenus();

        $menu = Menu::factory()->create();
        $page = Page::query()->create(['title' => 'About', 'slug' => 'about']);
        $item = MenuItem::factory()->for($menu)->linkTo($page, 'pages')->create();
        $child = MenuItem::factory()->childOf($item)->create();

        $this->assertCount(1, $page->menuItems);

        $page->delete();

        $this->assertNull(MenuItem::query()->find($item->id));
        $this->assertNull($child->refresh()->parent_id);
    }

    #[Test]
    public function it_keeps_a_soft_deleted_records_items_until_it_is_force_deleted(): void
    {
        $this->registerMenus();

        $menu = Menu::factory()->create();
        $post = Post::query()->create(['type' => 'news', 'name' => 'Budget']);
        $item = MenuItem::factory()->for($menu)->linkTo($post, 'news')->create();

        $post->delete();
        $this->assertNotNull(MenuItem::query()->find($item->id));
        $this->assertTrue($item->refresh()->isBroken());

        $post->forceDelete();
        $this->assertNull(MenuItem::query()->find($item->id));
    }
}
