<?php

namespace App\Models;

use App\Enums\RoleEnum;

class UserPolicy
{
    public function viewAdmin(User $user): bool
    {
        return $user->hasAnyRole([
            RoleEnum::SuperAdmin->value,
            RoleEnum::Admin->value,
        ]);
    }
}
