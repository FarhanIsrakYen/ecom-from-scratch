<?php

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $role = fake()->randomElement(RoleEnum::cases());

        return [
            'name' => $role->value,
            'label' => $role->label(),
        ];
    }

    public function role(RoleEnum $role): static
    {
        return $this->state(fn (): array => [
            'name' => $role->value,
            'label' => $role->label(),
        ]);
    }
}
