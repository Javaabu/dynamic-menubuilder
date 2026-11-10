---
title: Testing
sidebar_position: 7
---

# In your app

Build menus with the factories and `MenuTree`, then assert on what renders:

```php
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;

#[Test]
public function it_shows_the_header_menu(): void
{
    $menu = Menu::factory()->create();
    Menus::assign('header', $menu);

    $services = MenuItem::factory()->for($menu)->heading()->create(['title' => 'ޚިދުމަތްތައް']);
    MenuItem::factory()->childOf($services)->linkTo($page, 'pages')->create();
    MenuItem::factory()->for($menu)->indexOf('news')->create();

    $this->get('/')
        ->assertSee('ޚިދުމަތްތައް')
        ->assertSee(route('pages.show', $page), false);
}
```

| Factory state | |
| --- | --- |
| `MenuItem::factory()->heading()` | A link without a URL |
| `->childOf(MenuItem $parent)` | In the parent's menu, under it |
| `->linkTo(Model $record, string $source)` | A record item |
| `->indexOf(string $source)` | A source's index page |

Your locations and sources are registered in your service provider, so they exist in tests too. To start from nothing, call `Menus::reset()`; the menus and items a request loaded are forgotten with `Menus::flush()` (saving does that for you).

Test the builder pages like any controller: post the same data the builder does.

```php
$this->actingAs($admin)
    ->post('/admin/menus', [
        'name' => 'Main',
        'locations' => ['header'],
        'items' => [
            ['key' => 'a', 'type' => 'link', 'title' => 'Home', 'url' => '/'],
            ['key' => 'b', 'parent' => 'a', 'type' => 'model', 'source' => 'pages', 'linkable_id' => (string) $page->id],
        ],
    ])
    ->assertSessionHasNoErrors();
```

With javaabu/translatable, use its `InteractsWithLanguages` (`$this->setUpLanguages(['en', 'dv'])`) and post `title_en`/`title_dv` instead of `title`.

# The package's own tests

```bash
composer test                 # PHPUnit
pnpm test                     # Vitest
DYNAMIC_MENUBUILDER_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter "Inertia|fixture"
```

The PHP tests write the JSON props and the Blade builder's HTML that the Vitest suite renders (`tests/js/fixtures`), and fail when they're out of date. The `Translatable` suite needs javaabu/translatable; CI also runs the rest without it and without Inertia.
