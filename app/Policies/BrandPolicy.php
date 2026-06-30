<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Brand $brand): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RoleEnum::SuperAdmin->value, RoleEnum::Admin->value]);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->create($user);
    }
}
