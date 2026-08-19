<?php

namespace AdAstra\Rules;

use AdAstra\Models\Category;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\Media\Library as MediaLibrary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

readonly class CategoryAttachedToGroupable implements ValidationRule
{
    /**
     * Must use the HasCategoryGroups trait. Traits are not types, so the
     * consumers are listed explicitly — add to the union when a third model
     * adopts the trait.
     *
     * @param EntryGroup|MediaLibrary $groupable
     */
    public function __construct(private Model $groupable)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowedGroupIds = $this->groupable->categoryGroups()->pluck('category_groups.id');

        $belongs = Category::query()
            ->whereKey($value)
            ->whereIn('group_id', $allowedGroupIds)
            ->exists();

        if (!$belongs) {
            $fail('The selected :attribute is not part of an attached category group.');
        }
    }
}
