<?php

namespace Javaabu\DynamicMenubuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Javaabu\DynamicMenubuilder\Database\Factories\MenuFactory;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;
use Javaabu\DynamicMenubuilder\Facades\Menus;

/**
 * A menu: its items, and the locations it is shown in
 *
 * @property int $id
 * @property string $name
 */
class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function getTable(): string
    {
        return DynamicMenubuilder::table('menus');
    }

    /**
     * Every item, at every depth, in order
     */
    public function items(): HasMany
    {
        return $this->hasMany(DynamicMenubuilder::menuItemModel(), 'menu_id')
            ->orderBy('order')
            ->orderBy('id');
    }

    /**
     * The top-level items, in order
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    public function locationAssignments(): HasMany
    {
        return $this->hasMany(DynamicMenubuilder::menuLocationModel(), 'menu_id');
    }

    /**
     * The keys of the locations the menu is assigned to
     *
     * @return array<int, string>
     */
    public function locationKeys(): array
    {
        return $this->locationAssignments->pluck('location')->all();
    }

    /**
     * Show the menu in these locations (and no others). A location holds one menu, so this
     * takes the locations away from the menus they had.
     *
     * @param  array<int, string>  $locations
     */
    public function assignLocations(array $locations): void
    {
        $locations = array_values(array_unique(array_filter($locations, fn ($location) => Menus::hasLocation($location))));
        $assignment_class = DynamicMenubuilder::menuLocationModel();

        $assignment_class::query()
            ->where('menu_id', $this->getKey())
            ->whereNotIn('location', $locations)
            ->delete();

        foreach ($locations as $location) {
            $assignment_class::query()->updateOrCreate(['location' => $location], ['menu_id' => $this->getKey()]);
        }

        $this->unsetRelation('locationAssignments');

        Menus::flush();
    }

    public function scopeSearch(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $query->where('name', 'like', '%' . $search . '%');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Menus::flush());
        static::deleted(fn () => Menus::flush());
    }

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }
}
