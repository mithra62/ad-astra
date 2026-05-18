<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Category\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
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
            // Random suffix keeps the (group_id, handle) unique index from
            // colliding when two factory-built categories happen to draw the
            // same word pair from Faker's bounded lorem vocabulary.
            'handle' => Str::slug($name) . '-' . Str::random(6),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
