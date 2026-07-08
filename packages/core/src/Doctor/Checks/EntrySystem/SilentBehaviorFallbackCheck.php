<?php

namespace AdAstra\Doctor\Checks\EntrySystem;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Models\EntryType;

class SilentBehaviorFallbackCheck extends AbstractDoctorCheck
{
    protected string $id = 'entry-system.silent-behavior-fallback';
    protected string $name = 'Entry types without a behavior';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        // A null entry_behavior_id resolves to GeneralEntryType with only a
        // log warning; surface the fallback so it stops being silent.
        // (Broken behavior *aliases* are covered by behavior-classes.)
        $orphaned = EntryType::whereNull('entry_behavior_id')->get();

        foreach ($orphaned as $type) {
            yield $this->warn(
                "Entry type [{$type->handle}] has no behavior — it falls back to GeneralEntryType at runtime",
                fixCommand: 'assign an entry behavior to the type in the admin',
            );
        }

        if ($orphaned->isEmpty()) {
            yield $this->pass('All entry types have an explicit behavior');
        }
    }
}
