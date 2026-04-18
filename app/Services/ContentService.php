<?php

namespace App\Services;

use App\Builders\EntryQueryBuilder;
use App\EntryTypes\EntryTypeRegistry;
use App\Models\Entry;
use App\Repositories\EntryRepository;

class ContentService
{
    public function __construct(
        private readonly EntryTypeRegistry $registry,
        private readonly EntryRepository $repository,
    ) {}

    public function create(string $typeHandle, array $data = []): Entry
    {
        $entryType = $this->registry->resolveByHandle($typeHandle);

        return $this->repository->create($entryType, $data);
    }

    public function update(Entry $entry, array $data = []): Entry
    {
        return $this->repository->applyData($entry, $data);
    }

    public function get(int $id): Entry
    {
        return $this->repository->findOrFail($id);
    }

    public function find(int $id): ?Entry
    {
        return $this->repository->find($id);
    }

    public function query(): EntryQueryBuilder
    {
        return new EntryQueryBuilder($this->repository);
    }
}
