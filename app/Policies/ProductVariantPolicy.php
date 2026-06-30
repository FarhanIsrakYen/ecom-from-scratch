<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return $this->create($user);
    }

    private function manage(User $user): bool
    {
        return $user->hasAnyRole([RoleEnum::SuperAdmin->value, RoleEnum::Admin->value]);
    }
}
