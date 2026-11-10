<?php

namespace Javaabu\DynamicMenubuilder\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Routing\MenuRoutes;

/**
 * The builder's record search: `GET {prefix}/linkables/{source}?search=…` gives
 * `{ data: [{ id, label }] }`. Needs the ability to create menus.
 */
class LinkableOptionsController extends Controller
{
    public function index(Request $request, string $source): JsonResponse
    {
        $user = $request->user(MenuRoutes::guard($request));

        abort_unless($user instanceof Model && app(Gate::class)->forUser($user)->allows('create', DynamicMenubuilder::menuModel()), 403);
        abort_unless(Menus::hasLinkable($source), 404);

        $search = $request->query('search');
        $limit = min(max($request->integer('limit', (int) config('dynamic-menubuilder.search_limit', 20)), 1), 100);

        return new JsonResponse([
            'data' => Menus::getLinkable($source)->search(is_string($search) ? $search : null, $limit),
        ]);
    }
}
