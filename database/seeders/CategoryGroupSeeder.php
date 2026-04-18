<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedTopics();
        $this->seedProductCategories();
    }

    private function seedTopics(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['slug' => 'topics'],
            ['name' => 'Topics', 'sort_order' => 1]
        );

        $topics = [
            ['name' => 'Technology', 'slug' => 'technology', 'sort_order' => 1],
            ['name' => 'Design',     'slug' => 'design',     'sort_order' => 2],
            ['name' => 'Business',   'slug' => 'business',   'sort_order' => 3],
        ];

        foreach ($topics as $topic) {
            Category::firstOrCreate(
                ['group_id' => $group->id, 'slug' => $topic['slug']],
                array_merge($topic, ['group_id' => $group->id])
            );
        }
    }

    private function seedProductCategories(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['slug' => 'product-categories'],
            ['name' => 'Product Categories', 'sort_order' => 2]
        );

        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'sort_order' => 1],
            ['name' => 'Clothing',    'slug' => 'clothing',    'sort_order' => 2],
            ['name' => 'Books',       'slug' => 'books',       'sort_order' => 3],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['group_id' => $group->id, 'slug' => $category['slug']],
                array_merge($category, ['group_id' => $group->id])
            );
        }
    }
}
