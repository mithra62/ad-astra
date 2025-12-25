<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Category\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        return [
            'group_id' => Group::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
