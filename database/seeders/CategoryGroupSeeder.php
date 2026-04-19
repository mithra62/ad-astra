<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\Field\Type as FieldType;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\FieldValue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    private FieldType $text;
    private FieldType $textarea;

    public function run(): void
    {
        $this->text     = FieldType::where('object', \App\Field\Types\Text::class)->firstOrFail();
        $this->textarea = FieldType::where('object', \App\Field\Types\Textarea::class)->firstOrFail();

        $this->seedTopics();
        $this->seedProductCategories();
    }

    // -------------------------------------------------------------------------

    private function seedTopics(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['slug' => 'topics'],
            ['name' => 'Topics', 'sort_order' => 1]
        );

        // --- Fields ----------------------------------------------------------

        $description = Field::firstOrCreate(
            ['slug' => 'topic_description'],
            [
                'field_type_id' => $this->textarea->id,
                'name'          => 'Topic Description',
                'label'         => 'Description',
                'instructions'  => 'A short description of this topic.',
            ]
        );

        $featuredLabel = Field::firstOrCreate(
            ['slug' => 'topic_featured_label'],
            [
                'field_type_id' => $this->text->id,
                'name'          => 'Topic Featured Label',
                'label'         => 'Featured Label',
                'instructions'  => 'Optional label shown in featured topic listings.',
            ]
        );

        // --- Field Group -----------------------------------------------------

        $fieldGroup = FieldGroup::firstOrCreate(
            ['slug' => 'topic-fields'],
            ['name' => 'Topic Fields', 'description' => 'Fields for topic categories.']
        );

        $fieldGroup->fields()->syncWithoutDetaching([$description->id, $featuredLabel->id]);
        $group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

        // --- Field Layout ----------------------------------------------------

        if (! $group->field_layout_id) {
            $layout = FieldLayout::create(['name' => 'Topics Layout']);

            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => 'Details',
                'sort_order'      => 1,
            ]);

            foreach ([$description, $featuredLabel] as $order => $field) {
                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id'            => $field->id,
                    'required'            => false,
                    'sort_order'          => $order + 1,
                ]);
            }

            $group->update(['field_layout_id' => $layout->id]);
        }

        // --- Categories + field values ---------------------------------------

        $categories = [
            [
                'attrs'  => ['name' => 'Technology', 'slug' => 'technology', 'sort_order' => 1],
                'fields' => [
                    'topic_description'    => 'Articles, tutorials, and news about software and hardware.',
                    'topic_featured_label' => 'Tech',
                ],
            ],
            [
                'attrs'  => ['name' => 'Design', 'slug' => 'design', 'sort_order' => 2],
                'fields' => [
                    'topic_description'    => 'Visual design, UX, and creative thinking.',
                    'topic_featured_label' => '',
                ],
            ],
            [
                'attrs'  => ['name' => 'Business', 'slug' => 'business', 'sort_order' => 3],
                'fields' => [
                    'topic_description'    => 'Strategy, entrepreneurship, and industry insights.',
                    'topic_featured_label' => '',
                ],
            ],
        ];

        foreach ($categories as $data) {
            $category = Category::firstOrCreate(
                ['group_id' => $group->id, 'slug' => $data['attrs']['slug']],
                array_merge($data['attrs'], ['group_id' => $group->id])
            );

            $this->writeFieldValues($category, $data['fields']);
        }
    }

    // -------------------------------------------------------------------------

    private function seedProductCategories(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['slug' => 'product-categories'],
            ['name' => 'Product Categories', 'sort_order' => 2]
        );

        // --- Fields ----------------------------------------------------------

        $description = Field::firstOrCreate(
            ['slug' => 'product_cat_description'],
            [
                'field_type_id' => $this->textarea->id,
                'name'          => 'Product Category Description',
                'label'         => 'Description',
                'instructions'  => 'Describe what products belong in this category.',
            ]
        );

        $displayName = Field::firstOrCreate(
            ['slug' => 'product_cat_display_name'],
            [
                'field_type_id' => $this->text->id,
                'name'          => 'Product Category Display Name',
                'label'         => 'Display Name',
                'instructions'  => 'Alternative display name used in navigation and filters.',
            ]
        );

        // --- Field Group -----------------------------------------------------

        $fieldGroup = FieldGroup::firstOrCreate(
            ['slug' => 'product-category-fields'],
            ['name' => 'Product Category Fields', 'description' => 'Fields for product category groups.']
        );

        $fieldGroup->fields()->syncWithoutDetaching([$description->id, $displayName->id]);
        $group->fieldGroups()->syncWithoutDetaching([$fieldGroup->id]);

        // --- Field Layout ----------------------------------------------------

        if (! $group->field_layout_id) {
            $layout = FieldLayout::create(['name' => 'Product Categories Layout']);

            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => 'Category Info',
                'sort_order'      => 1,
            ]);

            foreach ([$description, $displayName] as $order => $field) {
                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id'            => $field->id,
                    'required'            => false,
                    'sort_order'          => $order + 1,
                ]);
            }

            $group->update(['field_layout_id' => $layout->id]);
        }

        // --- Root categories + field values ----------------------------------

        $electronics = $this->seedCategory($group, [
            'name' => 'Electronics', 'slug' => 'electronics', 'sort_order' => 1,
        ], [
            'product_cat_description' => 'Consumer electronics, gadgets, and accessories.',
            'product_cat_display_name' => 'Electronics & Gadgets',
        ]);

        $clothing = $this->seedCategory($group, [
            'name' => 'Clothing', 'slug' => 'clothing', 'sort_order' => 2,
        ], [
            'product_cat_description' => 'Apparel, footwear, and fashion accessories.',
            'product_cat_display_name' => 'Clothing & Apparel',
        ]);

        $books = $this->seedCategory($group, [
            'name' => 'Books', 'slug' => 'books', 'sort_order' => 3,
        ], [
            'product_cat_description' => 'Physical and digital books across all genres.',
            'product_cat_display_name' => 'Books & Media',
        ]);

        // --- Child categories ------------------------------------------------

        $this->seedCategory($group, [
            'name' => 'Phones',   'slug' => 'phones',   'sort_order' => 1, 'parent_id' => $electronics->id,
        ], ['product_cat_description' => 'Smartphones and mobile devices.', 'product_cat_display_name' => 'Phones']);

        $this->seedCategory($group, [
            'name' => 'Laptops',  'slug' => 'laptops',  'sort_order' => 2, 'parent_id' => $electronics->id,
        ], ['product_cat_description' => 'Laptops and portable computers.', 'product_cat_display_name' => 'Laptops']);

        $this->seedCategory($group, [
            'name' => 'Men\'s',   'slug' => 'mens',     'sort_order' => 1, 'parent_id' => $clothing->id,
        ], ['product_cat_description' => 'Men\'s clothing and accessories.', 'product_cat_display_name' => 'Men\'s']);

        $this->seedCategory($group, [
            'name' => 'Women\'s', 'slug' => 'womens',   'sort_order' => 2, 'parent_id' => $clothing->id,
        ], ['product_cat_description' => 'Women\'s clothing and accessories.', 'product_cat_display_name' => 'Women\'s']);

        $this->seedCategory($group, [
            'name' => 'Fiction',     'slug' => 'fiction',     'sort_order' => 1, 'parent_id' => $books->id,
        ], ['product_cat_description' => 'Novels and fiction titles.', 'product_cat_display_name' => 'Fiction']);

        $this->seedCategory($group, [
            'name' => 'Non-Fiction', 'slug' => 'non-fiction', 'sort_order' => 2, 'parent_id' => $books->id,
        ], ['product_cat_description' => 'Non-fiction, reference, and educational books.', 'product_cat_display_name' => 'Non-Fiction']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedCategory(CategoryGroup $group, array $attrs, array $fields): Category
    {
        $category = Category::firstOrCreate(
            ['group_id' => $group->id, 'slug' => $attrs['slug']],
            array_merge($attrs, ['group_id' => $group->id])
        );

        $this->writeFieldValues($category, $fields);

        return $category;
    }

    /**
     * Write field values for a category, pre-loading all field models in one query.
     *
     * @param array<string, mixed> $fields  ['field_slug' => value]
     */
    private function writeFieldValues(Category $category, array $fields): void
    {
        $fieldModels = Field::whereIn('slug', array_keys($fields))
            ->with('fieldType')
            ->get()
            ->keyBy('slug');

        foreach ($fields as $slug => $value) {
            $field = $fieldModels->get($slug);

            if (! $field || ! $field->fieldType) {
                continue;
            }

            $column = $field->fieldType->instance()->storageColumn();

            FieldValue::updateOrCreate(
                [
                    'field_id'       => $field->id,
                    'fieldable_id'   => $category->id,
                    'fieldable_type' => Category::class,
                ],
                [$column => $value]
            );
        }
    }
}
