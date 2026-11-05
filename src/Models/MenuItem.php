<?php

namespace Javaabu\DynamicMenubuilder\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;
use Javaabu\DynamicMenubuilder\Database\Factories\MenuItemFactory;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Enums\MenuItemType;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Linkables\LinkableSource;
use Javaabu\DynamicMenubuilder\Support\Translations;

/**
 * One item of a menu: a custom link, a record of a linkable source, or a source's index page
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int $order
 * @property MenuItemType $type
 * @property string|null $source
 * @property string|null $linkable_type
 * @property int|string|null $linkable_id
 * @property string|null $title
 * @property string|null $url
 * @property bool $new_tab
 * @property string|null $rel
 * @property string|null $css_class
 * @property string|null $icon
 * @property Visibility $visibility
 * @property array<int, string>|null $locales
 */
class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'order',
        'type',
        'source',
        'linkable_type',
        'linkable_id',
        'title',
        'url',
        'new_tab',
        'rel',
        'css_class',
        'icon',
        'visibility',
        'locales',
    ];

    protected $attributes = [
        'type' => 'link',
        'visibility' => 'everyone',
        'new_tab' => false,
        'order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'visibility' => Visibility::class,
            'new_tab' => 'boolean',
            'order' => 'integer',
            'locales' => 'array',
        ];
    }

    public function getTable(): string
    {
        return DynamicMenubuilder::table('menu_items');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(DynamicMenubuilder::menuModel(), 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('order')->orderBy('id');
    }

    /**
     * The record a model item links to
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The registered source of a model or index item
     */
    public function linkableSource(): ?LinkableSource
    {
        if (blank($this->source) || ! Menus::hasLinkable($this->source)) {
            return null;
        }

        return Menus::getLinkable($this->source);
    }

    /**
     * The record a model item links to, when it still exists and implements the contract
     */
    public function linkedRecord(): ?MenuLinkable
    {
        if ($this->type !== MenuItemType::Model) {
            return null;
        }

        $record = $this->linkable;

        return $record instanceof MenuLinkable ? $record : null;
    }

    /**
     * Whether a model or index item lost what it links to: the record was deleted, or its
     * source isn't registered any more
     */
    public function isBroken(): bool
    {
        if ($this->type === MenuItemType::Link) {
            return false;
        }

        if (! $this->linkableSource()) {
            return true;
        }

        return $this->type === MenuItemType::Model && ! $this->linkedRecord();
    }

    /**
     * The item's own title in a locale, without falling back to another locale
     */
    public function titleIn(?string $locale = null): ?string
    {
        $title = Translations::value($this, 'title', $locale ?? app()->getLocale());

        return filled($title) ? (string) $title : null;
    }

    /**
     * The item's own URL in a locale (custom links), without falling back to another locale
     */
    public function urlIn(?string $locale = null): ?string
    {
        $url = Translations::value($this, 'url', $locale ?? app()->getLocale());

        return filled($url) ? (string) $url : null;
    }

    /**
     * The title shown in a locale: the item's own, then the linked record's or the source's,
     * then the item's title in its own language
     */
    public function displayTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $title = $this->titleIn($locale);

        if ($title !== null) {
            return $title;
        }

        $record = $this->linkedRecord();

        if ($record) {
            return $record->getMenuLinkTitle($locale);
        }

        if ($this->type === MenuItemType::Index && $source = $this->linkableSource()) {
            return $source->getIndexTitle($locale);
        }

        $fallback = $this->getAttributeValue('title');

        return filled($fallback) ? (string) $fallback : '';
    }

    /**
     * The URL in a locale: the custom link (falling back to its own language), the record's
     * permalink, or the source's index page. Null for a heading or a broken item.
     */
    public function displayUrl(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return match ($this->type) {
            MenuItemType::Link => $this->urlIn($locale) ?? (filled($this->getAttributeValue('url')) ? (string) $this->getAttributeValue('url') : null),
            MenuItemType::Model => $this->linkedRecord()?->getMenuLinkUrl($locale),
            MenuItemType::Index => $this->linkableSource()?->getIndexUrl($locale),
        };
    }

    /**
     * Whether the item shows in a locale
     */
    public function showsIn(string $locale): bool
    {
        $locales = $this->locales;

        return empty($locales) || in_array($locale, $locales, true);
    }

    /**
     * Keep morph types and policies pointing at the package's base model
     */
    public function getMorphClass(): string
    {
        $class = DynamicMenubuilder::MODELS['menu_item'];

        return static::class === DynamicMenubuilder::TRANSLATABLE_MODELS['menu_item'] ? $class : parent::getMorphClass();
    }

    protected static function booted(): void
    {
        static::saved(fn () => Menus::flush());
        static::deleted(fn () => Menus::flush());
    }

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }
}
