<?php

namespace Javaabu\DynamicMenubuilder\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * A model whose records can be added to menus: pages, posts, categories, …
 *
 * Use the `IsMenuLinkable` trait for the defaults, implement `getMenuLinkUrl()`, and
 * register the model with `Menus::linkable(Model::class)`.
 */
interface MenuLinkable
{
    /**
     * The title a menu item shows when the admin doesn't type one
     */
    public function getMenuLinkTitle(?string $locale = null): string;

    /**
     * The record's permalink in a locale; null hides the item
     */
    public function getMenuLinkUrl(?string $locale = null): ?string;

    /**
     * The model's index (listing or archive) URL in a locale; null when it has none, which
     * also hides the "index page" option in the builder
     */
    public static function getMenuLinkIndexUrl(?string $locale = null): ?string;

    /**
     * Whether menus show the record now, e.g. only published posts
     */
    public function isMenuLinkVisible(): bool;

    /**
     * Narrow a query to the records the builder may search for
     */
    public static function searchMenuLinks(Builder $query, string $search): Builder;
}
