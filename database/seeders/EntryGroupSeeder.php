<?php

namespace Database\Seeders;

use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\StatusGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntryGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $publication     = StatusGroup::where('handle', 'publication')->firstOrFail();
        $contentFields   = FieldGroup::where('slug', 'content-fields')->firstOrFail();
        $seoFields       = FieldGroup::where('slug', 'seo-fields')->firstOrFail();
        $topics          = CategoryGroup::where('slug', 'topics')->firstOrFail();
        $productCategories = CategoryGroup::where('slug', 'product-categories')->firstOrFail();

        $this->seedBlogGroup($publication, $contentFields, $seoFields, $topics);
        $this->seedProductsGroup($publication, $contentFields, $seoFields, $productCategories);
    }

    private function seedBlogGroup(
        StatusGroup $publication,
        FieldGroup $contentFields,
        FieldGroup $seoFields,
        CategoryGroup $topics,
    ): void {
        $layout = $this->createLayout('Blog Layout', [
            'Content' => ['body', 'excerpt'],
            'SEO'     => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'blog'],
            [
                'name'            => 'Blog',
                'description'     => 'Blog posts and articles.',
                'field_layout_id' => $layout->id,
                'sort_order'      => 1,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);
        $group->statusGroups()->syncWithoutDetaching([$publication->id]);
        $group->categoryGroups()->syncWithoutDetaching([$topics->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'blog_post'],
            [
                'name'       => 'Blog Post',
                'class'      => \App\EntryTypes\BlogPostEntryType::class,
                'sort_order' => 1,
            ]
        );
    }

    private function seedProductsGroup(
        StatusGroup $publication,
        FieldGroup $contentFields,
        FieldGroup $seoFields,
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
                'sort_order'      => 2,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);
        $group->statusGroups()->syncWithoutDetaching([$publication->id]);
        $group->categoryGroups()->syncWithoutDetaching([$productCategories->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'product'],
            [
                'name'       => 'Product',
                'class'      => \App\EntryTypes\ProductEntryType::class,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * Create a FieldLayout with named tabs, each containing field handles.
     *
     * @param string $name
     * @param array<string, string[]> $tabs  Tab name => [field slugs]
     */
    private function createLayout(string $name, array $tabs): FieldLayout
    {
        $layout = FieldLayout::create(['name' => $name]);

        $tabOrder = 1;
        foreach ($tabs as $tabName => $fieldSlugs) {
            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => $tabName,
                'sort_order'      => $tabOrder++,
            ]);

            $elementOrder = 1;
            foreach ($fieldSlugs as $slug) {
                $field = Field::where('slug', $slug)->first();
                if (! $field) {
                    continue;
                }

                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id'            => $field->id,
                    'required'            => false,
                    'sort_order'          => $elementOrder++,
                ]);
            }
        }

        return $layout;
    }
}
