<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RoleEnum::SuperAdmin->value, RoleEnum::Admin->value]);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->create($user);
    }
}
