<?php

namespace Database\Factories;

use App\Models\PopularSearch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PopularSearch>
 */
class PopularSearchFactory extends Factory
{
    protected $model = PopularSearch::class;

    public function definition(): array
    {
        return [
            'query' => fake()->words(3, true),
            'search_count' => fake()->numberBetween(1, 100),
            'last_searched_at' => now(),
        ];
    }
}
