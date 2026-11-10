<?php

namespace Javaabu\DynamicMenubuilder;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;
use Javaabu\DynamicMenubuilder\Enums\MenuItemType;
use Javaabu\DynamicMenubuilder\Linkables\LinkableSource;
use Javaabu\DynamicMenubuilder\Locations\MenuLocation;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Models\MenuItem;
use Javaabu\DynamicMenubuilder\Rendering\MenuCache;
use Javaabu\DynamicMenubuilder\Rendering\MenuNode;

/**
 * The menu locations and linkable sources the app registered, and the menus they render.
 * Use it through the `Menus` facade.
 */
class MenuManager
{
    /** @var array<string, MenuLocation> */
    protected array $locations = [];

    /** @var array<string, LinkableSource> */
    protected array $linkables = [];

    protected bool $loaded_config = false;

    /**
     * Define a menu location: `Menus::location('header', 'Header')->maxDepth(2)`
     */
    public function location(string $key, ?string $label = null): MenuLocation
    {
        $this->loadConfig();

        $location = MenuLocation::make($key);

        if ($label !== null) {
            $location->label($label);
        }

        return $this->locations[$key] = $location;
    }

    /**
     * Register a location you built yourself
     */
    public function addLocation(MenuLocation $location): MenuLocation
    {
        $this->loadConfig();

        return $this->locations[$location->getKey()] = $location;
    }

    public function hasLocation(string $key): bool
    {
        $this->loadConfig();

        return isset($this->locations[$key]);
    }

    public function getLocation(string $key): MenuLocation
    {
        $this->loadConfig();

        return $this->locations[$key]
            ?? throw new InvalidArgumentException("Unknown menu location [{$key}]. Define it with Menus::location('{$key}') or in config('dynamic-menubuilder.locations').");
    }

    /**
     * @return array<string, MenuLocation>
     */
    public function locations(): array
    {
        $this->loadConfig();

        return $this->locations;
    }

    /**
     * Let admins add records of a `MenuLinkable` model to menus:
     * `Menus::linkable(Post::class, 'news')->label('News')->query(fn ($query) => $query->where('type', 'news'))`
     *
     * @param  class-string<Model&MenuLinkable>  $model
     */
    public function linkable(string $model, ?string $key = null): LinkableSource
    {
        $this->loadConfig();

        $source = LinkableSource::make($model, $key);

        return $this->linkables[$source->getKey()] = $source;
    }

    /**
     * Register a source you built yourself
     */
    public function addLinkable(LinkableSource $source): LinkableSource
    {
        $this->loadConfig();

        return $this->linkables[$source->getKey()] = $source;
    }

    public function hasLinkable(string $key): bool
    {
        $this->loadConfig();

        return isset($this->linkables[$key]);
    }

    public function getLinkable(string $key): LinkableSource
    {
        $this->loadConfig();

        return $this->linkables[$key]
            ?? throw new InvalidArgumentException("Unknown linkable source [{$key}]. Register it with Menus::linkable().");
    }

    /**
     * @return array<string, LinkableSource>
     */
    public function linkables(): array
    {
        $this->loadConfig();

        return $this->linkables;
    }

    /**
     * The source a record belongs to: the first registered source for its model whose query
     * contains it
     */
    public function sourceFor(Model $record): ?LinkableSource
    {
        foreach ($this->linkables() as $source) {
            if ($source->contains($record)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * The menu assigned to a location, or null
     */
    public function menuFor(string $location): ?Menu
    {
        $this->getLocation($location);

        return $this->cache()->remember("location.{$location}", function () use ($location) {
            $assignment_class = DynamicMenubuilder::menuLocationModel();

            $assignment = $assignment_class::query()->with('menu')->where('location', $location)->first();

            return $assignment?->menu;
        });
    }

    /**
     * Show a menu in a location, replacing the menu it had
     */
    public function assign(string $location, Menu|int|null $menu): void
    {
        $this->getLocation($location);

        $assignment_class = DynamicMenubuilder::menuLocationModel();

        if ($menu === null) {
            $assignment_class::query()->where('location', $location)->delete();
            $this->flush();

            return;
        }

        $assignment_class::query()->updateOrCreate(
            ['location' => $location],
            ['menu_id' => $menu instanceof Menu ? $menu->getKey() : $menu],
        );

        $this->flush();
    }

    /**
     * The items of a location's menu, as a tree resolved for the locale and the location's
     * user: hidden, deleted and unpublished items are left out, and items deeper than the
     * location's maximum depth aren't included
     *
     * @return array<int, MenuNode>
     */
    public function items(string $location, ?string $locale = null, Authenticatable|false|null $user = null, ?Request $request = null): array
    {
        $menu_location = $this->getLocation($location);
        $locale ??= app()->getLocale();
        $user = $user === null ? $menu_location->user() : ($user ?: null);

        if (! $menu_location->allows($user)) {
            return [];
        }

        $menu = $this->menuFor($location);

        if (! $menu) {
            return [];
        }

        $items = $this->menuItems($menu);
        $request ??= app()->bound('request') ? request() : null;

        return $this->buildNodes($items->groupBy(fn (MenuItem $item) => (string) $item->parent_id), '', 1, $menu_location->getMaxDepth(), $locale, $user, $request);
    }

    /**
     * Every location's resolved items, keyed by location: what the Inertia shared data holds
     *
     * @param  array<int, string>|null  $locations
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function shared(?array $locations = null, ?string $locale = null): array
    {
        $shared = [];

        foreach ($locations ?? array_keys($this->locations()) as $location) {
            $shared[$location] = array_map(fn (MenuNode $node) => $node->toArray(), $this->items($location, $locale));
        }

        return $shared;
    }

    /**
     * Every item of a menu, with their records, loaded once per request
     *
     * @return Collection<int, MenuItem>
     */
    public function menuItems(Menu $menu): Collection
    {
        return $this->cache()->remember("menu.{$menu->getKey()}", function () use ($menu) {
            $item_class = DynamicMenubuilder::menuItemModel();

            return $item_class::query()
                ->where('menu_id', $menu->getKey())
                ->with('linkable')
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Forget the menus and items loaded during this request
     */
    public function flush(): void
    {
        $this->cache()->flush();
    }

    /**
     * Forget the registered locations and sources (for tests)
     */
    public function reset(): void
    {
        $this->locations = [];
        $this->linkables = [];
        $this->loaded_config = false;
        $this->flush();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, MenuItem>>  $children
     * @return array<int, MenuNode>
     */
    protected function buildNodes($children, string $parent, int $depth, int $max_depth, string $locale, ?Authenticatable $user, ?Request $request): array
    {
        if ($depth > $max_depth) {
            return [];
        }

        $nodes = [];

        foreach ($children->get($parent, collect()) as $item) {
            if (! $this->isVisible($item, $locale, $user)) {
                continue;
            }

            $url = $item->displayUrl($locale);

            // A model or index item without a URL has nowhere to go
            if ($url === null && $item->type !== MenuItemType::Link) {
                continue;
            }

            $child_nodes = $this->buildNodes($children, (string) $item->getKey(), $depth + 1, $max_depth, $locale, $user, $request);
            $current = $request !== null && $this->isCurrent($url, $request);
            $active = $current
                || $this->containsActive($child_nodes)
                || ($request !== null && $this->isWithin($url, $request));

            $nodes[] = new MenuNode(
                id: $item->getKey(),
                title: $item->displayTitle($locale),
                url: $url,
                type: $item->type->value,
                depth: $depth,
                new_tab: $item->new_tab,
                rel: $item->rel,
                css_class: $item->css_class,
                icon: $item->icon,
                current: $current,
                active: $active,
                children: $child_nodes,
            );
        }

        return $nodes;
    }

    protected function isVisible(MenuItem $item, string $locale, ?Authenticatable $user): bool
    {
        if (! $item->visibility->allows($user !== null)) {
            return false;
        }

        if (! $item->showsIn($locale)) {
            return false;
        }

        if ($item->type === MenuItemType::Link) {
            return true;
        }

        $source = $item->linkableSource();

        if (! $source || ! $source->allows($user)) {
            return false;
        }

        if ($item->type === MenuItemType::Index) {
            return true;
        }

        $record = $item->linkedRecord();

        return $record !== null && $record->isMenuLinkVisible();
    }

    /**
     * @param  array<int, MenuNode>  $nodes
     */
    protected function containsActive(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->active) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the URL is the current page (same host, if it has one, and path)
     */
    protected function isCurrent(?string $url, Request $request): bool
    {
        $path = $this->localPath($url, $request);

        return $path !== null && $path === $this->requestPath($request);
    }

    /**
     * Whether the current page is under the URL (`/news` for `/news/budget-2027`)
     */
    protected function isWithin(?string $url, Request $request): bool
    {
        $path = $this->localPath($url, $request);

        if ($path === null || $path === '/') {
            return false;
        }

        return str_starts_with($this->requestPath($request), rtrim($path, '/') . '/');
    }

    protected function localPath(?string $url, Request $request): ?string
    {
        if (blank($url) || str_starts_with($url, '#') || preg_match('/^(mailto|tel|javascript):/i', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        if (isset($parts['host']) && strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }

        return '/' . trim($parts['path'] ?? '', '/');
    }

    protected function requestPath(Request $request): string
    {
        return '/' . trim($request->path(), '/');
    }

    protected function cache(): MenuCache
    {
        return app(MenuCache::class);
    }

    /**
     * Register the locations and sources in config, once
     */
    protected function loadConfig(): void
    {
        if ($this->loaded_config) {
            return;
        }

        $this->loaded_config = true;

        foreach ((array) config('dynamic-menubuilder.locations', []) as $key => $options) {
            if (isset($this->locations[$key])) {
                continue;
            }

            $options = is_array($options) ? $options : ['label' => $options];

            $this->locations[$key] = MenuLocation::make($key)
                ->label($options['label'] ?? null)
                ->description($options['description'] ?? null)
                ->maxDepth($options['max_depth'] ?? null)
                ->guard($options['guard'] ?? null)
                ->can($options['can'] ?? null);
        }

        foreach ((array) config('dynamic-menubuilder.linkables', []) as $key => $options) {
            $options = is_array($options) ? $options : ['model' => $options];
            $source = LinkableSource::make($options['model'], is_string($key) ? $key : null);

            if (isset($this->linkables[$source->getKey()])) {
                continue;
            }

            $this->linkables[$source->getKey()] = $source
                ->label($options['label'] ?? null)
                ->can($options['can'] ?? null);
        }
    }
}
