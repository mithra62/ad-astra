<?php

namespace Database\Seeders;

use AdAstra\Field\Types\Text;
use AdAstra\Field\Types\Textarea;
use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\FieldValue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    private FieldType $text;

    private FieldType $textarea;

    public function run(): void
    {
        $this->text = FieldType::where('object', Text::class)->firstOrFail();
        $this->textarea = FieldType::where('object', Textarea::class)->firstOrFail();

        // Extended content groups — have field layouts and field values on each category
        $this->seedTopics();
        $this->seedProductCategories();

        // Controlled vocabulary groups — simple lists with no additional fields
        $this->seedCuisines();
        $this->seedDietTypes();
        $this->seedEventTypes();
        $this->seedEmploymentTypes();
        $this->seedExperienceLevels();
    }

    // -------------------------------------------------------------------------
    // Extended groups
    // -------------------------------------------------------------------------

    private function seedTopics(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'topics'],
            ['name' => 'Topics', 'sort_order' => 1]
        );

        // --- Fields ----------------------------------------------------------

        $description = Field::firstOrCreate(
            ['handle' => 'topic_description'],
            [
                'field_type_id' => $this->textarea->id,
                'name' => 'Topic Description',
                'label' => 'Description',
                'instructions' => 'A short description of this topic.',
            ]
        );

        $featuredLabel = Field::firstOrCreate(
            ['handle' => 'topic_featured_label'],
            [
                'field_type_id' => $this->text->id,
                'name' => 'Topic Featured Label',
                'label' => 'Featured Label',
                'instructions' => 'Optional label shown in featured topic listings.',
            ]
        );

        // --- Field Group -----------------------------------------------------

        $fieldGroup = FieldGroup::firstOrCreate(
            ['handle' => 'topic-fields'],
            ['name' => 'Topic Fields', 'description' => 'Fields for topic categories.']
        );

        $fieldGroup->fields()->syncWithoutDetaching([$description->id, $featuredLabel->id]);

        // --- Field Layout ----------------------------------------------------

        if (!$group->field_layout_id) {
            $layout = FieldLayout::create(['name' => 'Topics Layout', 'handle' => 'topics-layout']);

            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => 'Details',
                'handle'          => 'details',
                'sort_order'      => 1,
            ]);

            foreach ([$description, $featuredLabel] as $order => $field) {
                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id' => $field->id,
                    'required' => false,
                    'sort_order' => $order + 1,
                ]);
            }

            $group->update(['field_layout_id' => $layout->id]);
        }

        // --- Categories + field values ---------------------------------------

        $categories = [
            [
                'attrs' => ['name' => 'Technology', 'handle' => 'technology', 'sort_order' => 1],
                'fields' => [
                    'topic_description' => 'Articles, tutorials, and news about software and hardware.',
                    'topic_featured_label' => 'Tech',
                ],
            ],
            [
                'attrs' => ['name' => 'Design', 'handle' => 'design', 'sort_order' => 2],
                'fields' => [
                    'topic_description' => 'Visual design, UX, and creative thinking.',
                    'topic_featured_label' => '',
                ],
            ],
            [
                'attrs' => ['name' => 'Business', 'handle' => 'business', 'sort_order' => 3],
                'fields' => [
                    'topic_description' => 'Strategy, entrepreneurship, and industry insights.',
                    'topic_featured_label' => '',
                ],
            ],
        ];

        foreach ($categories as $data) {
            $category = Category::firstOrCreate(
                ['group_id' => $group->id, 'handle' => $data['attrs']['handle']],
                array_merge($data['attrs'], ['group_id' => $group->id])
            );

            $this->writeFieldValues($category, $data['fields']);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Write field values for a category, pre-loading all field models in one query.
     *
     * @param array<string, mixed> $fields ['field_handle' => value]
     */
    private function writeFieldValues(Category $category, array $fields): void
    {
        $fieldModels = Field::whereIn('handle', array_keys($fields))
            ->with('fieldType')
            ->get()
            ->keyBy('handle');

        foreach ($fields as $handle => $value) {
            $field = $fieldModels->get($handle);

            if (!$field || !$field->fieldType) {
                continue;
            }

            $column = $field->fieldType->instance()->storageColumn();

            FieldValue::updateOrCreate(
                [
                    'field_id' => $field->id,
                    'fieldable_id' => $category->id,
                    'fieldable_type' => (new Category)->getMorphClass(),
                ],
                [$column => $value]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Controlled vocabulary groups
    // -------------------------------------------------------------------------

    private function seedProductCategories(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'product-categories'],
            ['name' => 'Product Categories', 'sort_order' => 2]
        );

        // --- Fields ----------------------------------------------------------

        $description = Field::firstOrCreate(
            ['handle' => 'product_cat_description'],
            [
                'field_type_id' => $this->textarea->id,
                'name' => 'Product Category Description',
                'label' => 'Description',
                'instructions' => 'Describe what products belong in this category.',
            ]
        );

        $displayName = Field::firstOrCreate(
            ['handle' => 'product_cat_display_name'],
            [
                'field_type_id' => $this->text->id,
                'name' => 'Product Category Display Name',
                'label' => 'Display Name',
                'instructions' => 'Alternative display name used in navigation and filters.',
            ]
        );

        // --- Field Group -----------------------------------------------------

        $fieldGroup = FieldGroup::firstOrCreate(
            ['handle' => 'product-category-fields'],
            ['name' => 'Product Category Fields', 'description' => 'Fields for product category groups.']
        );

        $fieldGroup->fields()->syncWithoutDetaching([$description->id, $displayName->id]);

        // --- Field Layout ----------------------------------------------------

        if (!$group->field_layout_id) {
            $layout = FieldLayout::create(['name' => 'Product Categories Layout', 'handle' => 'product-categories-layout']);

            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name'            => 'Category Info',
                'handle'          => 'category-info',
                'sort_order'      => 1,
            ]);

            foreach ([$description, $displayName] as $order => $field) {
                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id' => $field->id,
                    'required' => false,
                    'sort_order' => $order + 1,
                ]);
            }

            $group->update(['field_layout_id' => $layout->id]);
        }

        // --- Root categories + field values ----------------------------------

        $electronics = $this->seedCategory($group, [
            'name' => 'Electronics', 'handle' => 'electronics', 'sort_order' => 1,
        ], [
            'product_cat_description' => 'Consumer electronics, gadgets, and accessories.',
            'product_cat_display_name' => 'Electronics & Gadgets',
        ]);

        $clothing = $this->seedCategory($group, [
            'name' => 'Clothing', 'handle' => 'clothing', 'sort_order' => 2,
        ], [
            'product_cat_description' => 'Apparel, footwear, and fashion accessories.',
            'product_cat_display_name' => 'Clothing & Apparel',
        ]);

        $books = $this->seedCategory($group, [
            'name' => 'Books', 'handle' => 'books', 'sort_order' => 3,
        ], [
            'product_cat_description' => 'Physical and digital books across all genres.',
            'product_cat_display_name' => 'Books & Media',
        ]);

        // --- Child categories ------------------------------------------------

        $this->seedCategory($group, [
            'name' => 'Phones', 'handle' => 'phones', 'sort_order' => 1, 'parent_id' => $electronics->id,
        ], ['product_cat_description' => 'Smartphones and mobile devices.', 'product_cat_display_name' => 'Phones']);

        $this->seedCategory($group, [
            'name' => 'Laptops', 'handle' => 'laptops', 'sort_order' => 2, 'parent_id' => $electronics->id,
        ], ['product_cat_description' => 'Laptops and portable computers.', 'product_cat_display_name' => 'Laptops']);

        $this->seedCategory($group, [
            'name' => 'Men\'s', 'handle' => 'mens', 'sort_order' => 1, 'parent_id' => $clothing->id,
        ], ['product_cat_description' => 'Men\'s clothing and accessories.', 'product_cat_display_name' => 'Men\'s']);

        $this->seedCategory($group, [
            'name' => 'Women\'s', 'handle' => 'womens', 'sort_order' => 2, 'parent_id' => $clothing->id,
        ], ['product_cat_description' => 'Women\'s clothing and accessories.', 'product_cat_display_name' => 'Women\'s']);

        $this->seedCategory($group, [
            'name' => 'Fiction', 'handle' => 'fiction', 'sort_order' => 1, 'parent_id' => $books->id,
        ], ['product_cat_description' => 'Novels and fiction titles.', 'product_cat_display_name' => 'Fiction']);

        $this->seedCategory($group, [
            'name' => 'Non-Fiction', 'handle' => 'non-fiction', 'sort_order' => 2, 'parent_id' => $books->id,
        ], ['product_cat_description' => 'Non-fiction, reference, and educational books.', 'product_cat_display_name' => 'Non-Fiction']);
    }

    private function seedCategory(CategoryGroup $group, array $attrs, array $fields): Category
    {
        $category = Category::firstOrCreate(
            ['group_id' => $group->id, 'handle' => $attrs['handle']],
            array_merge($attrs, ['group_id' => $group->id])
        );

        $this->writeFieldValues($category, $fields);

        return $category;
    }

    private function seedCuisines(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'cuisines'],
            ['name' => 'Cuisines', 'sort_order' => 3]
        );

        $cuisines = [
            ['name' => 'American', 'handle' => 'american', 'sort_order' => 1],
            ['name' => 'French', 'handle' => 'french', 'sort_order' => 2],
            ['name' => 'Indian', 'handle' => 'indian', 'sort_order' => 3],
            ['name' => 'Italian', 'handle' => 'italian', 'sort_order' => 4],
            ['name' => 'Japanese', 'handle' => 'japanese', 'sort_order' => 5],
            ['name' => 'Mediterranean', 'handle' => 'mediterranean', 'sort_order' => 6],
            ['name' => 'Mexican', 'handle' => 'mexican', 'sort_order' => 7],
            ['name' => 'Thai', 'handle' => 'thai', 'sort_order' => 8],
        ];

        $this->seedSimpleCategories($group, $cuisines);
    }

    /**
     * Seed a list of plain categories (no fields or field layout) into a group.
     *
     * @param array<array{name: string, handle: string, sort_order: int}> $categories
     */
    private function seedSimpleCategories(CategoryGroup $group, array $categories): void
    {
        foreach ($categories as $attrs) {
            Category::firstOrCreate(
                ['group_id' => $group->id, 'handle' => $attrs['handle']],
                array_merge($attrs, ['group_id' => $group->id])
            );
        }
    }

    private function seedDietTypes(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'diet-types'],
            ['name' => 'Diet Types', 'sort_order' => 4]
        );

        $dietTypes = [
            ['name' => 'Dairy-Free', 'handle' => 'dairy-free', 'sort_order' => 1],
            ['name' => 'Gluten-Free', 'handle' => 'gluten-free', 'sort_order' => 2],
            ['name' => 'Keto', 'handle' => 'keto', 'sort_order' => 3],
            ['name' => 'Paleo', 'handle' => 'paleo', 'sort_order' => 4],
            ['name' => 'Vegan', 'handle' => 'vegan', 'sort_order' => 5],
            ['name' => 'Vegetarian', 'handle' => 'vegetarian', 'sort_order' => 6],
        ];

        $this->seedSimpleCategories($group, $dietTypes);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedEventTypes(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'event-types'],
            ['name' => 'Event Types', 'sort_order' => 5]
        );

        $eventTypes = [
            ['name' => 'Conference', 'handle' => 'conference', 'sort_order' => 1],
            ['name' => 'Course', 'handle' => 'course', 'sort_order' => 2],
            ['name' => 'Meetup', 'handle' => 'meetup', 'sort_order' => 3],
            ['name' => 'Networking', 'handle' => 'networking', 'sort_order' => 4],
            ['name' => 'Webinar', 'handle' => 'webinar', 'sort_order' => 5],
            ['name' => 'Workshop', 'handle' => 'workshop', 'sort_order' => 6],
        ];

        $this->seedSimpleCategories($group, $eventTypes);
    }

    private function seedEmploymentTypes(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'employment-types'],
            ['name' => 'Employment Types', 'sort_order' => 6]
        );

        $employmentTypes = [
            ['name' => 'Contract', 'handle' => 'contract', 'sort_order' => 1],
            ['name' => 'Freelance', 'handle' => 'freelance', 'sort_order' => 2],
            ['name' => 'Full-Time', 'handle' => 'full-time', 'sort_order' => 3],
            ['name' => 'Part-Time', 'handle' => 'part-time', 'sort_order' => 4],
            ['name' => 'Remote', 'handle' => 'remote', 'sort_order' => 5],
        ];

        $this->seedSimpleCategories($group, $employmentTypes);
    }

    private function seedExperienceLevels(): void
    {
        $group = CategoryGroup::firstOrCreate(
            ['handle' => 'experience-levels'],
            ['name' => 'Experience Levels', 'sort_order' => 7]
        );

        $levels = [
            ['name' => 'Entry Level', 'handle' => 'entry-level', 'sort_order' => 1],
            ['name' => 'Mid Level', 'handle' => 'mid-level', 'sort_order' => 2],
            ['name' => 'Senior', 'handle' => 'senior', 'sort_order' => 3],
            ['name' => 'Lead', 'handle' => 'lead', 'sort_order' => 4],
            ['name' => 'Executive', 'handle' => 'executive', 'sort_order' => 5],
        ];

        $this->seedSimpleCategories($group, $levels);
    }
}
