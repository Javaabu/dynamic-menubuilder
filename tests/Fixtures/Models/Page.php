<?php

namespace Javaabu\DynamicMenubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\DynamicMenubuilder\Concerns\IsMenuLinkable;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;

/**
 * A linkable model with the trait's defaults and an index page
 */
class Page extends Model implements MenuLinkable
{
    use IsMenuLinkable;

    protected $guarded = [];

    protected $casts = ['published' => 'boolean'];

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return url(($locale && $locale !== 'en' ? "{$locale}/" : '') . "pages/{$this->slug}");
    }

    public static function getMenuLinkIndexUrl(?string $locale = null): ?string
    {
        return url('pages');
    }

    public function isMenuLinkVisible(): bool
    {
        return $this->published;
    }
}
