<?php

namespace Javaabu\DynamicMenubuilder\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Permissions\Policies\ResourcePolicy;

/**
 * view_menus, edit_menus and delete_menus. Menus aren't soft deleted, so there's no trash.
 */
class MenuPolicy extends ResourcePolicy
{
    protected string $resource = 'menus';

    public function restore(Model $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(Model $user, Model $model): bool
    {
        return false;
    }

    public function viewTrash(Model $user): bool
    {
        return false;
    }
}
