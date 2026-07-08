<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'base_price' => fake()->randomFloat(2, 10, 500),
            'sale_price' => null,
            'status' => 'active',
            'is_featured' => false,
            'average_rating' => 0,
            'reviews_count' => 0,
        ];
    }
}
