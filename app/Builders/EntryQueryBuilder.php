<?php

namespace App\Builders;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Repositories\EntryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EntryQueryBuilder
{
    private Builder $query;

    public function __construct(private readonly EntryRepository $repository)
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
        $this->query->whereHas('authors', fn($q) => $q->where('users.id', $userId));

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
        $this->query->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId));

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
