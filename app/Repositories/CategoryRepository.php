<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Category\Group;
use App\Models\FieldValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategoryRepository
{
    public function create(Group $group, array $data): Category
    {
        $category = new Category;
        $category->group_id = $group->getKey();

        $this->applyCoreAttributes($category, $data);
        $category->save();

        $this->applyFieldValues($category, $data['fields'] ?? []);

        return $category->refresh();
    }

    public function applyData(Category $category, array $data): Category
    {
        $this->applyCoreAttributes($category, $data);
        $category->save();

        if (array_key_exists('fields', $data)) {
            $this->applyFieldValues($category, $data['fields']);
        }

        return $category->refresh();
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }

    private function applyCoreAttributes(Category $category, array $data): void
    {
        if (isset($data['name'])) {
            $category->name = $data['name'];
        }

        $category->handle = $data['handle'] ?? Str::slug($category->name ?? '');

        if (array_key_exists('sort_order', $data)) {
            $category->sort_order = (int) $data['sort_order'];
        }

        if (array_key_exists('parent_id', $data)) {
            $category->parent_id = $data['parent_id'] ?: null;
        }
    }

    private function applyFieldValues(Category $category, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $layoutFields = $this->resolveLayoutFields($category);

        foreach ($fields as $handle => $value) {
            $field = $layoutFields->firstWhere('handle', $handle);

            if (! $field || ! $field->fieldType) {
                continue;
            }

            $instance = $field->fieldType->instance();

            FieldValue::updateOrCreate(
                [
                    'field_id' => $field->getKey(),
                    'fieldable_id' => $category->getKey(),
                    'fieldable_type' => $category->getMorphClass(),
                ],
                [$instance->storageColumn() => $value]
            );
        }
    }

    public function resolveLayoutFields(Category $category): Collection
    {
        $category->loadMissing([
            'group.fieldLayout.tabs.elements.field.fieldType',
        ]);

        return $category->group->fieldLayout?->fields() ?? collect();
    }
}
