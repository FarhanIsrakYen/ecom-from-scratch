<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect(RoleEnum::cases())->mapWithKeys(
            fn (RoleEnum $role) => [
                $role->value => Role::query()->updateOrCreate(
                    ['name' => $role->value],
                    ['label' => $role->label()],
                ),
            ],
        );

        $this->createUser('Super Admin', 'superadmin@example.com', $roles[RoleEnum::SuperAdmin->value]);
        $this->createUser('Admin', 'admin@example.com', $roles[RoleEnum::Admin->value]);
        $this->createUser('Customer', 'customer@example.com', $roles[RoleEnum::Customer->value]);
    }

    private function createUser(string $name, string $email, Role $role): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('Password123'),
                'status' => UserStatusEnum::Active->value,
                'email_verified_at' => now(),
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
