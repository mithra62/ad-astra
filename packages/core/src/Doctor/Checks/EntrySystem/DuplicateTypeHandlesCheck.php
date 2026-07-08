<?php

namespace AdAstra\Doctor\Checks\EntrySystem;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Models\EntryType;

class DuplicateTypeHandlesCheck extends AbstractDoctorCheck
{
    protected string $id = 'entry-system.duplicate-type-handles';
    protected string $name = 'Entry type handle uniqueness';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        // Content::create() and EntryTypeRegistry::resolveByHandle() resolve
        // by handle alone, but entry_types.handle has no unique index — a
        // duplicate silently routes all writes to whichever row wins.
        $duplicates = EntryType::query()
            ->select('handle')
            ->groupBy('handle')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('handle');

        foreach ($duplicates as $handle) {
            yield $this->fail(
                "Entry type handle [{$handle}] is used by more than one type — handle resolution is ambiguous",
                fixCommand: 'rename one of the duplicate entry types in the admin',
            );
        }

        if ($duplicates->isEmpty()) {
            yield $this->pass('All entry type handles are unique');
        }
    }
}
