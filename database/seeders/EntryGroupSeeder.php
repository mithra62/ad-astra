<?php

namespace Database\Seeders;

use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryBehavior;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\StatusGroup;
use Database\Seeders\Concerns\BuildsLayouts;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntryGroupSeeder extends Seeder
{
    use BuildsLayouts, WithoutModelEvents;

    public function run(): void
    {
        $publication       = StatusGroup::where('handle', 'publication')->firstOrFail();
        $productStatus     = StatusGroup::where('handle', 'product-status')->firstOrFail();
        $topics            = CategoryGroup::where('handle', 'topics')->firstOrFail();
        $productCategories = CategoryGroup::where('handle', 'product-categories')->firstOrFail();

        $this->seedBlogGroup($publication, $topics);
        $this->seedProductsGroup($productStatus, $productCategories);
    }

    private function seedBlogGroup(
        StatusGroup   $publication,
        CategoryGroup $topics,
    ): void {
        $layout = $this->createLayout('Blog Layout', [
            'Content'    => ['body', 'excerpt'],
            'SEO'        => ['meta_title', 'meta_description'],
            'Related'    => ['related_entries'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'blog'],
            [
                'name'            => 'Blog',
                'description'     => 'Blog posts and articles.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 1,
            ]
        );

        $group->categoryGroups()->syncWithoutDetaching([$topics->id]);

        // Add "Publishing" tab to the layout if not already present.
        $this->addTabIfMissing($group->field_layout_id, 'Publishing', ['reading_time'], 99);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'blog_post'],
            [
                'name'              => 'Blog Post',
                'entry_behavior_id' => EntryBehavior::where('handle', 'blog-post')->value('id'),
                'sort_order'        => 1,
            ]
        );
    }

    private function seedProductsGroup(
        StatusGroup   $productStatus,
        CategoryGroup $productCategories,
    ): void {
        $layout = $this->createLayout('Products Layout', [
            'Description' => ['body', 'excerpt'],
            'SEO'         => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'products'],
            [
                'name'            => 'Products',
                'description'     => 'Product catalogue entries.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $productStatus->id,
                'sort_order'      => 2,
            ]
        );

        // Swap to product-status on existing group.
        $group->update(['status_group_id' => $productStatus->id]);

        $group->categoryGroups()->syncWithoutDetaching([$productCategories->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Pricing',   ['price', 'sale_price', 'sku'], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Inventory', ['stock_quantity', 'weight', 'dimensions'], 11);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'product'],
            [
                'name'              => 'Product',
                'entry_behavior_id' => EntryBehavior::where('handle', 'product')->value('id'),
                'sort_order'        => 1,
            ]
        );
    }

}
