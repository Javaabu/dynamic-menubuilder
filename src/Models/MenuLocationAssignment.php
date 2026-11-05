<?php

namespace Javaabu\DynamicMenubuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;

/**
 * Which menu a location shows
 *
 * @property int $id
 * @property string $location
 * @property int $menu_id
 */
class MenuLocationAssignment extends Model
{
    protected $fillable = [
        'location',
        'menu_id',
    ];

    public function getTable(): string
    {
        return DynamicMenubuilder::table('menu_locations');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(DynamicMenubuilder::menuModel(), 'menu_id');
    }
}
