<?php

namespace Javaabu\DynamicMenubuilder\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;

/**
 * `can()` as in javaabu/menu-builder: an ability checked through the gate, or a closure that
 * gets the user (null for guests)
 */
trait HasCan
{
    protected Closure|string|null $can = null;

    /** @var array<int, mixed> */
    protected array $can_arguments = [];

    /**
     * Only show this to users who pass the ability (or the closure)
     */
    public function can(Closure|string|null $can, mixed $arguments = []): static
    {
        $this->can = $can;
        $this->can_arguments = Arr::wrap($arguments);

        return $this;
    }

    public function getCan(): Closure|string|null
    {
        return $this->can;
    }

    public function allows(?Authenticatable $user): bool
    {
        if ($this->can === null) {
            return true;
        }

        if ($this->can instanceof Closure) {
            return (bool) app()->call($this->can, ['user' => $user]);
        }

        if (! $user) {
            return false;
        }

        return app(Gate::class)->forUser($user)->allows($this->can, $this->can_arguments);
    }
}
