<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Category\Group;
use App\Models\FieldValue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

    private function applyCoreAttributes(Category $category, array $data): void
    {
        if (isset($data['name'])) {
            $category->name = $data['name'];
        }

        $category->handle = $data['handle'] ?? Str::slug($category->name ?? '');

        if (array_key_exists('sort_order', $data)) {
            $category->sort_order = (int)$data['sort_order'];
        }

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'] ?: null;

            if ($parentId !== null && $category->exists && $this->wouldCreateCycle($category, (int)$parentId)) {
                throw new InvalidArgumentException(
                    "Setting parent_id [{$parentId}] on category [{$category->id}] would create a circular reference."
                );
            }

            $category->parent_id = $parentId;
        }
    }

    private function wouldCreateCycle(Category $category, int $targetParentId): bool
    {
        if ($targetParentId === $category->id) {
            return true;
        }

        $candidate = Category::find($targetParentId);

        while ($candidate?->parent_id !== null) {
            if ($candidate->parent_id === $category->id) {
                return true;
            }

            $candidate = Category::find($candidate->parent_id);
        }

        return false;
    }

    private function applyFieldValues(Category $category, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $layoutFields = $this->resolveLayoutFields($category);

        foreach ($fields as $handle => $value) {
            $field = $layoutFields->firstWhere('handle', $handle);

            if (!$field || !$field->fieldType) {
                continue;
            }

            $instance = $field->fieldType->instance();

            $this->upsertFieldValue(
                $field->getKey(),
                $category->getKey(),
                $category->getMorphClass(),
                $instance->storageColumn(),
                $value
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

    private function upsertFieldValue(
        int    $fieldId,
        int    $fieldableId,
        string $fieldableType,
        string $column,
        mixed  $value,
    ): void
    {
        $key = ['field_id' => $fieldId, 'fieldable_id' => $fieldableId, 'fieldable_type' => $fieldableType];

        try {
            FieldValue::updateOrCreate($key, [$column => $value]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            FieldValue::updateOrCreate($key, [$column => $value]);
        }
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
        return (bool)$category->delete();
    }
}
