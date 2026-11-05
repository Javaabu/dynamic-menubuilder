<?php

namespace Javaabu\DynamicMenubuilder\Views;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use RuntimeException;

/**
 * Renders a package page with the stack chosen in `config('dynamic-menubuilder.views')`
 */
class ViewRenderer
{
    public const string BLADE = 'blade';

    public const string INERTIA = 'inertia';

    /**
     * @param  string  $page  e.g. `menus.index`, rendered as the Blade view `dynamic-menubuilder::menus.index`
     *                        or the Inertia page `dynamic-menubuilder::Menus/Index`
     * @param  array<string, mixed>  $data
     */
    public function render(string $page, array $data = []): View|Responsable
    {
        if ($this->isInertia()) {
            return $this->renderInertia($page, $data);
        }

        return view("dynamic-menubuilder::{$page}", [
            'layout' => config('dynamic-menubuilder.layout', 'layouts.app'),
            'layout_section' => config('dynamic-menubuilder.layout_section', 'content'),
            ...$data,
        ]);
    }

    public function stack(): string
    {
        return config('dynamic-menubuilder.views', self::BLADE);
    }

    public function isInertia(): bool
    {
        return $this->stack() === self::INERTIA;
    }

    /**
     * The Inertia page name for a page: `menus.index` → `dynamic-menubuilder::Menus/Index`
     */
    public function inertiaPage(string $page): string
    {
        $segments = array_map(fn (string $segment) => str($segment)->studly()->toString(), explode('.', $page));

        return 'dynamic-menubuilder::' . implode('/', $segments);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderInertia(string $page, array $data): Responsable
    {
        if (! class_exists(Inertia::class)) {
            throw new RuntimeException('Install inertiajs/inertia-laravel to use the inertia views, or set dynamic-menubuilder.views to blade.');
        }

        return Inertia::render($this->inertiaPage($page), $data);
    }
}
