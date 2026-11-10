<?php

namespace Javaabu\DynamicMenubuilder\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Permissions\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasRoles;

    protected $guarded = [];
}
