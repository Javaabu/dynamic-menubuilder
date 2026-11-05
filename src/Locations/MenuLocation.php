<?php

namespace Javaabu\DynamicMenubuilder\Locations;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Javaabu\DynamicMenubuilder\Concerns\HasCan;

/**
 * A place in the app's layout that shows a menu (the header, the footer, …)
 *
 * Developers define locations; admins choose the menu each one shows.
 *
 * @implements Arrayable<string, mixed>
 */
class MenuLocation implements Arrayable
{
    use HasCan;

    protected Closure|string|null $label = null;

    protected Closure|string|null $description = null;

    protected ?int $max_depth = null;

    protected ?string $guard = null;

    public function __construct(
        protected string $key,
    ) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    /**
     * The name admins see. A string is translated with `__()` when shown.
     */
    public function label(Closure|string|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A hint for admins, e.g. "Shown at the top of every page"
     */
    public function description(Closure|string|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * How many levels the location shows: 1 for a flat list, 2 for one level of dropdowns.
     * The builder won't nest deeper, and deeper items aren't rendered.
     */
    public function maxDepth(?int $max_depth): static
    {
        $this->max_depth = $max_depth === null ? null : max(1, $max_depth);

        return $this;
    }

    /**
     * The guard whose user `can()` and item visibility (guests or users) check
     */
    public function guard(?string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->resolveText($this->label) ?? str($this->key)->headline()->toString();
    }

    public function getDescription(): ?string
    {
        return $this->resolveText($this->description);
    }

    public function getMaxDepth(): int
    {
        return $this->max_depth ?? max(1, (int) config('dynamic-menubuilder.max_depth', 3));
    }

    public function getGuard(): ?string
    {
        return $this->guard;
    }

    /**
     * The signed-in user of the location's guard
     */
    public function user(): ?Authenticatable
    {
        return auth()->guard($this->guard)->user();
    }

    /**
     * @return array{key: string, label: string, description: string|null, max_depth: int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->getKey(),
            'label' => $this->getLabel(),
            'description' => $this->getDescription(),
            'max_depth' => $this->getMaxDepth(),
        ];
    }

    protected function resolveText(Closure|string|null $text): ?string
    {
        if ($text instanceof Closure) {
            return app()->call($text);
        }

        return $text === null ? null : __($text);
    }
}
