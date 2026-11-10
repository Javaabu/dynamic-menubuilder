<?php

namespace Javaabu\DynamicMenubuilder\Rendering;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One rendered menu item, resolved for a locale and a user: what Blade views and React
 * components draw
 *
 * @implements Arrayable<string, mixed>
 */
class MenuNode implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, MenuNode>  $children
     */
    public function __construct(
        public readonly int|string $id,
        public readonly string $title,
        public readonly ?string $url,
        public readonly string $type,
        public readonly int $depth = 1,
        public readonly bool $new_tab = false,
        public readonly ?string $rel = null,
        public readonly ?string $css_class = null,
        public readonly ?string $icon = null,
        public readonly bool $current = false,
        public readonly bool $active = false,
        public readonly array $children = [],
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * A heading: an item without a link, usually a dropdown's label
     */
    public function isHeading(): bool
    {
        return blank($this->url);
    }

    public function target(): ?string
    {
        return $this->new_tab ? '_blank' : null;
    }

    /**
     * `rel`, with `noopener` for links that open in a new tab
     */
    public function relAttribute(): ?string
    {
        $rel = array_filter(explode(' ', (string) $this->rel));

        if ($this->new_tab && ! in_array('noopener', $rel, true)) {
            $rel[] = 'noopener';
        }

        return $rel === [] ? null : implode(' ', $rel);
    }

    /**
     * @return array{id: int|string, title: string, url: string|null, type: string, depth: int, target: string|null, rel: string|null, css_class: string|null, icon: string|null, current: bool, active: bool, children: array<int, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'type' => $this->type,
            'depth' => $this->depth,
            'target' => $this->target(),
            'rel' => $this->relAttribute(),
            'css_class' => $this->css_class,
            'icon' => $this->icon,
            'current' => $this->current,
            'active' => $this->active,
            'children' => array_map(fn (MenuNode $child) => $child->toArray(), $this->children),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
