<?php

namespace Javaabu\DynamicMenubuilder\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Javaabu\DynamicMenubuilder\Concerns\IsMenuLinkable;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;

/**
 * One model, several sources: one per post type (as javaabu/post-types does)
 */
class Post extends Model implements MenuLinkable
{
    use IsMenuLinkable;
    use SoftDeletes;

    protected $guarded = [];

    public static function getMenuLinkTitleColumn(): string
    {
        return 'name';
    }

    public function getMenuLinkUrl(?string $locale = null): ?string
    {
        return url("{$this->type}/{$this->id}");
    }
}
