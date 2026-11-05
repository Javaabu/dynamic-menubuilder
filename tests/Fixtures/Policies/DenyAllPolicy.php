<?php

namespace Javaabu\DynamicMenubuilder\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;

class DenyAllPolicy
{
    public function before(Model $user, string $ability): bool
    {
        return false;
    }
}
