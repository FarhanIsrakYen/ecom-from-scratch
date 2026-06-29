<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Customer => 'Customer',
        };
    }
}
