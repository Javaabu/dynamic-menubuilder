<?php

namespace Javaabu\DynamicMenubuilder\Rendering;

use Closure;

/**
 * What the current request has already loaded: the menu of each location and each menu's
 * items. Bound as a scoped service, so Octane starts every request empty.
 */
class MenuCache
{
    /** @var array<string, mixed> */
    protected array $items = [];

    public function remember(string $key, Closure $callback): mixed
    {
        if (! array_key_exists($key, $this->items)) {
            $this->items[$key] = $callback();
        }

        return $this->items[$key];
    }

    public function flush(): void
    {
        $this->items = [];
    }
}
