<?php

namespace Javaabu\DynamicMenubuilder\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Support\Translations;

/**
 * Defaults for `MenuLinkable` models: the title comes from the `title` column (override
 * `getMenuLinkTitleColumn()`), there's no index page, every record is visible, and the
 * builder searches the title column
 *
 * When a record is deleted for good, its menu items are removed and their children move up.
 */
trait IsMenuLinkable
{
    public static function bootIsMenuLinkable(): void
    {
        static::deleted(function (self $record) {
            if (in_array(SoftDeletes::class, class_uses_recursive($record), true) && ! $record->isForceDeleting()) {
                return;
            }

            $record->removeFromMenus();
        });
    }

    /**
     * The menu items that link to this record
     */
    public function menuItems(): MorphMany
    {
        return $this->morphMany(DynamicMenubuilder::menuItemModel(), 'linkable');
    }

    /**
     * The column the default title and search use
     */
    public static function getMenuLinkTitleColumn(): string
    {
        return 'title';
    }

    public function getMenuLinkTitle(?string $locale = null): string
    {
        $column = static::getMenuLinkTitleColumn();
        $locale ??= app()->getLocale();

        $title = Translations::supports($this, $column) && method_exists($this, 'translate')
            ? $this->translate($column, $locale)
            : $this->getAttribute($column);

        return (string) ($title ?? $this->getKey());
    }

    public static function getMenuLinkIndexUrl(?string $locale = null): ?string
    {
        return null;
    }

    public function isMenuLinkVisible(): bool
    {
        return true;
    }

    public static function searchMenuLinks(Builder $query, string $search): Builder
    {
        return $query->where(static::getMenuLinkTitleColumn(), 'like', '%' . $search . '%');
    }

    /**
     * Delete the menu items that link to this record; their children take their place
     */
    public function removeFromMenus(): void
    {
        foreach ($this->menuItems()->get() as $item) {
            $item->children()->update(['parent_id' => $item->parent_id]);
            $item->delete();
        }
    }
}
