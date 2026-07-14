<?php

namespace Database\Factories;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchHistory>
 */
class SearchHistoryFactory extends Factory
{
    protected $model = SearchHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'query' => fake()->words(3, true),
            'filters' => [],
        ];
    }
}
