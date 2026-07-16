<?php

namespace AdAstra\Doctor\Checks\EntrySystem;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\EntryTypes\AbstractEntryType;
use AdAstra\Models\EntryBehavior;
use Illuminate\Database\Eloquent\Relations\Relation;

class BehaviorClassReferencesCheck extends AbstractDoctorCheck
{
    protected string $id = 'entry-system.behavior-classes';
    protected string $name = 'Entry behavior class references';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        $broken = 0;
        $behaviors = EntryBehavior::all();

        foreach ($behaviors as $behavior) {
            $class = Relation::getMorphedModel($behavior->class);

            if ($class === null) {
                $broken++;
                yield $this->fail(
                    "EntryBehavior [{$behavior->handle}] → morph key [{$behavior->class}] is not registered in the morphMap",
                );
                continue;
            }

            if (!class_exists($class)) {
                $broken++;
                yield $this->fail("EntryBehavior [{$behavior->handle}] → class [{$class}] does not exist");
            } elseif (!is_subclass_of($class, AbstractEntryType::class)) {
                $broken++;
                yield $this->fail("EntryBehavior [{$behavior->handle}] → class [{$class}] does not extend AbstractEntryType");
            }
        }

        if ($broken === 0) {
            yield $this->pass('All ' . $behaviors->count() . ' entry behaviors resolve to valid classes');
        }
    }
}
