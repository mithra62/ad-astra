<?php

namespace App\Builders;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EntryQueryBuilder
{
    private Builder $query;

    public function __construct()
    {
        $this->query = Entry::query();
    }

    public function inGroup(string|int|EntryGroup $group): static
    {
        $this->query->inGroup($group);

        return $this;
    }

    public function ofType(string|int|EntryType $type): static
    {
        $this->query->ofType($type);

        return $this;
    }

    public function published(): static
    {
        $this->query->published();

        return $this;
    }

    public function withStatus(string $statusHandle): static
    {
        $this->query->withStatus($statusHandle);

        return $this;
    }

    public function withAuthor(int $userId): static
    {
        $this->query->whereHas('authors', fn ($q) => $q->where('entry_authors.user_id', $userId));

        return $this;
    }

    public function where(string $column, mixed $operator, mixed $value = null): static
    {
        $value === null
            ? $this->query->where($column, $operator)
            : $this->query->where($column, $operator, $value);

        return $this;
    }

    public function withCategory(int $categoryId): static
    {
        $this->query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));

        return $this;
    }

    /**
     * Filter entries by a scalar custom field value.
     *
     * Supports the standard two- or three-argument form:
     *   ->whereField('slug', 'my-post')           // implicit =
     *   ->whereField('release_date', '>=', now())  // explicit operator
     *
     * One Field lookup is performed to resolve the handle to its storage column
     * (value_text, value_integer, etc.). If the handle is unknown, or the field
     * is relational (data lives in entry_relationships, not field_values), an
     * InvalidArgumentException is thrown rather than silently returning no results.
     *
     * @throws InvalidArgumentException for unknown or relational field handles.
     */
    public function whereField(string $handle, mixed $operator, mixed $value = null): static
    {
        // Support two-argument shorthand: ->whereField('slug', 'my-post')
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        // Resolve the field's storage column with a single lookup so the WHERE
        // targets the correct typed column rather than scanning all six.
        $field = Field::with('fieldType')->where('handle', $handle)->first();

        if (! $field?->fieldType) {
            throw new InvalidArgumentException(
                "whereField: no field with handle [{$handle}] exists."
            );
        }

        $instance = $field->typeInstance();

        if ($instance->isRelational()) {
            throw new InvalidArgumentException(
                "whereField: [{$handle}] is a relational field and cannot be filtered via field_values. Use a whereHas on entryRelationships instead."
            );
        }

        $column = $instance->storageColumn();
        $value = $instance->prepareForQuery($value);

        // whereHas on the morphMany automatically scopes fieldable_type to Entry —
        // no need to set it explicitly.
        $this->query->whereHas('fieldValues', function ($q) use ($field, $column, $operator, $value) {
            $q->where('field_id', $field->getKey())
              ->where($column, $operator, $value);
        });

        return $this;
    }

    public function latest(): static
    {
        return $this->orderBy('created_at', 'desc');
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function get(): Collection
    {
        return $this->query->with($this->eagerLoad())->get();
    }

    private function eagerLoad(): array
    {
        return [
            'entryGroup',
            'entryType',
            'creator',
            'authors',
            'categories',
            'fieldValues.field.fieldType',
            'entryRelationships.field',
            'entryRelationships.relatedEntry',
        ];
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query->with($this->eagerLoad())->paginate($perPage);
    }

    public function first(): ?Entry
    {
        return $this->query->with($this->eagerLoad())->first();
    }

    public function firstOrFail(): Entry
    {
        return $this->query->with($this->eagerLoad())->firstOrFail();
    }

    public function count(): int
    {
        return $this->query->count();
    }
}
