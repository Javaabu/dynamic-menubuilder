<?php

namespace Javaabu\DynamicMenubuilder\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;

/**
 * Registers the menu builder pages for `Route::menus()`
 */
class MenuRoutes
{
    /** The route action key that holds the guard the pages authorize against */
    public const string GUARD_ACTION = 'dynamic_menubuilder_guard';

    /**
     * @param  array{
     *     guard?: string|null,
     *     prefix?: string,
     *     name?: string,
     *     middleware?: string|array<int, string>|null,
     *     controllers?: array<string, class-string>
     * }  $options
     */
    public static function register(Router $router, array $options = []): void
    {
        $guard = $options['guard'] ?? static::defaultGuard();
        $prefix = $options['prefix'] ?? 'menus';
        $name = trim($options['name'] ?? 'menus', '.');
        $middleware = $options['middleware'] ?? ["auth:{$guard}"];
        $menus = $options['controllers']['menus'] ?? config('dynamic-menubuilder.controllers.menus');
        $linkables = $options['controllers']['linkables'] ?? config('dynamic-menubuilder.controllers.linkables');

        $router->group([
            'prefix' => $prefix,
            'as' => $name === '' ? '' : "{$name}.",
            'middleware' => $middleware,
            self::GUARD_ACTION => $guard,
        ], function (Router $router) use ($menus, $linkables) {
            $router->get('/linkables/{source}', [$linkables, 'index'])->name('linkables.index');

            $router->get('/', [$menus, 'index'])->name('index');
            $router->get('/create', [$menus, 'create'])->name('create');
            $router->post('/', [$menus, 'store'])->name('store');
            $router->get('/{menu}/edit', [$menus, 'edit'])->name('edit')->whereNumber('menu');
            $router->get('/{menu}', [$menus, 'show'])->name('show')->whereNumber('menu');
            $router->match(['put', 'patch'], '/{menu}', [$menus, 'update'])->name('update')->whereNumber('menu');
            $router->delete('/{menu}', [$menus, 'destroy'])->name('destroy')->whereNumber('menu');
        });
    }

    /**
     * The guard the current route authorizes against
     */
    public static function guard(Request $request): string
    {
        return $request->route()?->getAction(self::GUARD_ACTION) ?? static::defaultGuard();
    }

    /**
     * The route name prefix of the current route, e.g. `admin.menus` (empty when the routes have no name prefix)
     */
    public static function namePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        foreach (['linkables.index', 'index', 'create', 'store', 'edit', 'show', 'update', 'destroy'] as $action) {
            if ($name === $action) {
                return '';
            }

            $suffix = ".{$action}";

            if (str_ends_with($name, $suffix)) {
                return substr($name, 0, -strlen($suffix));
            }
        }

        return 'menus';
    }

    public static function defaultGuard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}
