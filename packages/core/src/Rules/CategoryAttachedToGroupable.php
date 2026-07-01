<?php

namespace AdAstra\Rules;

use AdAstra\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

readonly class CategoryAttachedToGroupable implements ValidationRule
{
    /**
     * @param Model $groupable Must use the HasCategoryGroups trait (e.g. EntryGroup, Media\Library).
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
