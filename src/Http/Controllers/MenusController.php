<?php

namespace Javaabu\DynamicMenubuilder\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Forms\MenuForm;
use Javaabu\DynamicMenubuilder\Locations\MenuLocation;
use Javaabu\DynamicMenubuilder\Models\Menu;
use Javaabu\DynamicMenubuilder\Routing\MenuRoutes;
use Javaabu\DynamicMenubuilder\Support\MenuTree;
use Javaabu\DynamicMenubuilder\Views\ViewRenderer;
use Javaabu\Stencil\Form;

/**
 * The menu builder: the list of menus and locations, and the page that builds a menu
 *
 * Every action answers JSON when the request expects it, and a Blade or Inertia page
 * otherwise. Extend it and set yours in `config('dynamic-menubuilder.controllers.menus')`
 * (or the `controllers` option of `Route::menus()`) to change it.
 */
class MenusController extends Controller
{
    public function __construct(
        protected ViewRenderer $views,
    ) {}

    /**
     * The stencil form of the create and edit pages
     *
     * @return class-string<MenuForm>
     */
    protected function formClass(): string
    {
        return MenuForm::class;
    }

    public function index(Request $request): View|Responsable|JsonResponse
    {
        $this->authorizeAbility($request, 'viewAny', $this->modelClass());

        $per_page = min(max($request->integer('per_page', (int) config('dynamic-menubuilder.per_page', 20)), 1), 100);
        $search = $request->query('search');

        $menus = $this->query($request)
            ->search(is_string($search) ? $search : null)
            ->with('locationAssignments')
            ->withCount('items')
            ->orderBy('name')
            ->paginate($per_page)
            ->withQueryString();

        $rows = $menus->getCollection()->map(fn (Menu $menu) => $this->row($request, $menu))->all();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => $rows,
                'locations' => $this->locations($request),
                'meta' => [
                    'current_page' => $menus->currentPage(),
                    'last_page' => $menus->lastPage(),
                    'per_page' => $menus->perPage(),
                    'total' => $menus->total(),
                ],
            ]);
        }

        return $this->views->render('menus.index', [
            ...$this->meta($request),
            'title' => __('dynamic-menubuilder::strings.menus'),
            'rows' => $rows,
            'menus' => $this->views->isInertia() ? $menus->toArray() : $menus,
            'locations' => $this->locations($request),
            'search' => is_string($search) ? $search : null,
        ]);
    }

    public function create(Request $request): View|Responsable|JsonResponse
    {
        $this->authorizeAbility($request, 'create', $this->modelClass());

        $form = $this->form($request, null);

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => ['form' => $form->render('json')]]);
        }

        return $this->formPage($request, $form, null);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility($request, 'create', $this->modelClass());

        $model_class = $this->modelClass();
        $menu = $this->save($request, $this->form($request, null), new $model_class);

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $this->data($request, $menu)], 201);
        }

        return redirect()
            ->to($this->url($request, 'edit', $menu))
            ->with('success', __('dynamic-menubuilder::strings.messages.created'));
    }

    public function show(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $menu = $this->find($request, $menu);

        $this->authorizeAbility($request, 'view', $menu);

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $this->data($request, $menu)]);
        }

        return redirect()->to($this->url($request, 'edit', $menu));
    }

    public function edit(Request $request, string $menu): View|Responsable|JsonResponse
    {
        $menu = $this->find($request, $menu);

        $this->authorizeAbility($request, 'update', $menu);

        $form = $this->form($request, $menu);

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => [
                'menu' => $this->data($request, $menu),
                'form' => $form->render('json'),
            ]]);
        }

        return $this->formPage($request, $form, $menu);
    }

    public function update(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $menu = $this->find($request, $menu);

        $this->authorizeAbility($request, 'update', $menu);

        $menu = $this->save($request, $this->form($request, $menu), $menu);

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $this->data($request, $menu)]);
        }

        return redirect()
            ->to($this->url($request, 'edit', $menu))
            ->with('success', __('dynamic-menubuilder::strings.messages.updated'));
    }

    public function destroy(Request $request, string $menu): RedirectResponse|JsonResponse
    {
        $menu = $this->find($request, $menu);

        $this->authorizeAbility($request, 'delete', $menu);

        $menu->delete();

        if ($request->expectsJson()) {
            return new JsonResponse(null, 204);
        }

        return redirect()
            ->to($this->url($request, 'index'))
            ->with('success', __('dynamic-menubuilder::strings.messages.deleted'));
    }

    /**
     * The create or edit page
     */
    protected function formPage(Request $request, Form $form, ?Menu $menu): View|Responsable
    {
        return $this->views->render('menus.form', [
            ...$this->meta($request, $menu),
            'title' => $menu ? $menu->name : __('dynamic-menubuilder::strings.actions.new'),
            'form' => $this->views->isInertia() ? $form->render('json') : $form,
            'menu' => $menu ? $this->data($request, $menu) : null,
        ]);
    }

    protected function form(Request $request, ?Menu $menu): Form
    {
        $form_class = $this->formClass();

        return $form_class::make()
            ->id('dynamic-menubuilder-menu-form')
            ->with(['search_url' => $this->searchUrl($request)])
            ->model($menu ?? $this->modelClass())
            ->action($menu ? $this->url($request, 'update', $menu) : $this->url($request, 'store'))
            ->method($menu ? 'PATCH' : 'POST')
            ->submit($menu ? __('dynamic-menubuilder::strings.actions.save') : __('dynamic-menubuilder::strings.actions.create'))
            ->summary();
    }

    /**
     * Validate the request against the form, check the tree, and save the menu, its locations
     * and its items together
     */
    protected function save(Request $request, Form $form, Menu $menu): Menu
    {
        $data = $form->validate($request);

        $locations = $form->hasField('locations')
            ? array_values((array) ($data['locations'] ?? []))
            : ($menu->exists ? $menu->locationKeys() : []);

        MenuTree::validate((array) ($data['items'] ?? []), MenuTree::maxDepthFor($locations));

        DB::transaction(function () use ($request, $form, $menu, $data) {
            $form->fillModel($menu, $data);

            $this->saving($request, $menu, $data);

            $menu->save();

            $form->saveRelationships($menu, $data);
        });

        Menus::flush();

        return $menu->refresh();
    }

    /**
     * Called before a menu is saved, with the validated data
     *
     * @param  array<string, mixed>  $data
     */
    protected function saving(Request $request, Menu $menu, array $data): void {}

    /**
     * The locations, the menu each one shows and what the user may do with it
     *
     * @return array<int, array<string, mixed>>
     */
    protected function locations(Request $request): array
    {
        return collect(Menus::locations())
            ->map(function (MenuLocation $location) use ($request) {
                $menu = Menus::menuFor($location->getKey());

                return [
                    ...$location->toArray(),
                    'menu' => $menu ? [
                        'id' => $menu->getKey(),
                        'name' => $menu->name,
                        'edit_url' => $this->user($request) && $this->gate($request)->allows('update', $menu) ? $this->url($request, 'edit', $menu) : null,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * One row of the index page
     *
     * @return array<string, mixed>
     */
    protected function row(Request $request, Menu $menu): array
    {
        return [
            'id' => $menu->getKey(),
            'name' => $menu->name,
            'items_count' => (int) ($menu->items_count ?? $menu->items()->count()),
            'locations' => $this->locationLabels($menu),
            'links' => $this->links($request, $menu),
            'can' => $this->abilities($request, $menu),
        ];
    }

    /**
     * A menu as JSON
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request, Menu $menu): array
    {
        return [
            'id' => $menu->getKey(),
            'name' => $menu->name,
            'locations' => $menu->locationKeys(),
            'items' => MenuTree::toBuilder($menu),
            'links' => $this->links($request, $menu),
            'can' => $this->abilities($request, $menu),
            'created_at' => $menu->created_at?->toIso8601String(),
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function locationLabels(Menu $menu): array
    {
        return collect($menu->locationKeys())
            ->filter(fn (string $key) => Menus::hasLocation($key))
            ->map(fn (string $key) => Menus::getLocation($key)->getLabel())
            ->values()
            ->all();
    }

    /**
     * What every page gets: URLs, what the user may do, the flashed status and, for the
     * Inertia pages, their translated text
     *
     * @return array<string, mixed>
     */
    protected function meta(Request $request, ?Menu $menu = null): array
    {
        $user = $this->user($request);

        return [
            'guard' => MenuRoutes::guard($request),
            'urls' => array_filter([
                'index' => $this->url($request, 'index'),
                'create' => $this->url($request, 'create'),
                'store' => $this->url($request, 'store'),
                'search' => $this->searchUrl($request),
                ...($menu ? $this->links($request, $menu) : []),
            ]),
            'can' => [
                'create' => $user !== null && $this->gate($request)->allows('create', $this->modelClass()),
                ...($menu ? $this->abilities($request, $menu) : ['view' => false, 'update' => false, 'delete' => false]),
            ],
            'status' => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],
            'strings' => $this->views->isInertia() ? $this->strings() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function links(Request $request, Menu $menu): array
    {
        return [
            'edit' => $this->url($request, 'edit', $menu),
            'update' => $this->url($request, 'update', $menu),
            'destroy' => $this->url($request, 'destroy', $menu),
        ];
    }

    /**
     * @return array{view: bool, update: bool, delete: bool}
     */
    protected function abilities(Request $request, Menu $menu): array
    {
        if (! $this->user($request)) {
            return ['view' => false, 'update' => false, 'delete' => false];
        }

        $gate = $this->gate($request);

        return [
            'view' => $gate->allows('view', $menu),
            'update' => $gate->allows('update', $menu),
            'delete' => $gate->allows('delete', $menu),
        ];
    }

    /**
     * The translated text of the Inertia pages (the Blade views use __() directly)
     *
     * @return array<string, string>
     */
    protected function strings(): array
    {
        return [
            'menus' => __('dynamic-menubuilder::strings.menus'),
            'locations' => __('dynamic-menubuilder::strings.sections.locations'),
            'location' => __('dynamic-menubuilder::strings.fields.location'),
            'menu' => __('dynamic-menubuilder::strings.fields.menu'),
            'name' => __('dynamic-menubuilder::strings.fields.name'),
            'items' => __('dynamic-menubuilder::strings.fields.items'),
            'new' => __('dynamic-menubuilder::strings.actions.new'),
            'back' => __('dynamic-menubuilder::strings.actions.back'),
            'edit' => __('dynamic-menubuilder::strings.actions.edit'),
            'delete' => __('dynamic-menubuilder::strings.actions.delete'),
            'actions' => __('dynamic-menubuilder::strings.actions.actions'),
            'search' => __('dynamic-menubuilder::strings.actions.search'),
            'search_placeholder' => __('dynamic-menubuilder::strings.search'),
            'confirm_delete' => __('dynamic-menubuilder::strings.messages.confirm_delete'),
            'empty' => __('dynamic-menubuilder::strings.messages.empty'),
            'no_menu' => __('dynamic-menubuilder::strings.messages.no_menu'),
            'no_locations' => __('dynamic-menubuilder::strings.messages.no_locations'),
        ];
    }

    protected function searchUrl(Request $request): string
    {
        $prefix = MenuRoutes::namePrefix($request);

        return str_replace('__source__', '{source}', route(($prefix === '' ? '' : "{$prefix}.") . 'linkables.index', ['source' => '__source__']));
    }

    protected function query(Request $request): Builder
    {
        return $this->modelClass()::query();
    }

    protected function find(Request $request, string $key): Menu
    {
        return $this->query($request)->findOrFail($key);
    }

    protected function authorizeAbility(Request $request, string $ability, mixed $arguments): void
    {
        abort_unless($this->user($request) && $this->gate($request)->allows($ability, $arguments), 403);
    }

    protected function gate(Request $request): Gate
    {
        $gate = app(Gate::class);
        $user = $this->user($request);

        return $user ? $gate->forUser($user) : $gate;
    }

    protected function user(Request $request): ?Model
    {
        $user = $request->user(MenuRoutes::guard($request));

        return $user instanceof Model ? $user : null;
    }

    /** @return class-string<Menu> */
    protected function modelClass(): string
    {
        return DynamicMenubuilder::menuModel();
    }

    /**
     * The URL of one of the routes, honouring the prefix the app registered them under
     */
    protected function url(Request $request, string $action, ?Menu $menu = null): string
    {
        $prefix = MenuRoutes::namePrefix($request);

        return route(($prefix === '' ? '' : "{$prefix}.") . $action, $menu ? ['menu' => $menu->getKey()] : []);
    }
}
