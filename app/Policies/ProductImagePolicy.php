<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\ProductImage;
use App\Models\User;

class ProductImagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, ProductImage $productImage): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, ProductImage $productImage): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ProductImage $productImage): bool
    {
        return $this->create($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasAnyRole([RoleEnum::SuperAdmin->value, RoleEnum::Admin->value]);
    }
}
