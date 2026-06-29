<?php

namespace Tests\Unit\Rules;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\Media\Library;
use App\Rules\CategoryAttachedToGroupable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAttachedToGroupableTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_category_in_attached_group_on_entry_group(): void
    {
        $groupable = EntryGroup::factory()->create();
        $category = $this->makeAttachedCategory($groupable);

        $this->assertRulePasses(new CategoryAttachedToGroupable($groupable), $category->id);
    }

    public function test_fails_for_category_in_unattached_group_on_entry_group(): void
    {
        $groupable = EntryGroup::factory()->create();
        $category = $this->makeUnattachedCategory();

        $this->assertRuleFails(new CategoryAttachedToGroupable($groupable), $category->id);
    }

    public function test_passes_for_category_in_attached_group_on_media_library(): void
    {
        $groupable = Library::factory()->create();
        $category = $this->makeAttachedCategory($groupable);

        $this->assertRulePasses(new CategoryAttachedToGroupable($groupable), $category->id);
    }

    public function test_fails_for_category_in_unattached_group_on_media_library(): void
    {
        $groupable = Library::factory()->create();
        $category = $this->makeUnattachedCategory();

        $this->assertRuleFails(new CategoryAttachedToGroupable($groupable), $category->id);
    }

    protected function makeAttachedCategory(EntryGroup|Library $groupable): Category
    {
        $categoryGroup = CategoryGroup::factory()->create();
        $groupable->categoryGroups()->syncWithoutDetaching([$categoryGroup->id]);

        return Category::factory()->for($categoryGroup, 'group')->create();
    }

    protected function makeUnattachedCategory(): Category
    {
        $categoryGroup = CategoryGroup::factory()->create();

        return Category::factory()->for($categoryGroup, 'group')->create();
    }

    protected function assertRulePasses(CategoryAttachedToGroupable $rule, mixed $value): void
    {
        $failed = false;

        $rule->validate('categories.0', $value, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected the rule to pass, but it failed.');
    }

    protected function assertRuleFails(CategoryAttachedToGroupable $rule, mixed $value): void
    {
        $failed = false;

        $rule->validate('categories.0', $value, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Expected the rule to fail, but it passed.');
    }
}
