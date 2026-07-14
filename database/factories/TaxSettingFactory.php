<?php

namespace Database\Factories;

use App\Models\TaxSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxSetting>
 */
class TaxSettingFactory extends Factory
{
    protected $model = TaxSetting::class;

    public function definition(): array
    {
        return [
            'name' => 'VAT',
            'country' => 'Bangladesh',
            'state' => null,
            'city' => null,
            'rate' => fake()->randomFloat(4, 1, 15),
            'is_default' => false,
            'status' => 'active',
        ];
    }
}
