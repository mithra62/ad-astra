<?php

namespace AdAstra\Doctor\Checks\FieldSystem;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Field\AbstractField;
use AdAstra\Models\Field\Type as FieldType;

class FieldTypeClassReferencesCheck extends AbstractDoctorCheck
{
    protected string $id = 'field-system.type-classes';
    protected string $name = 'Field type class references';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        $broken = 0;
        $types = FieldType::all();

        foreach ($types as $type) {
            if (!class_exists($type->object)) {
                $broken++;
                yield $this->fail("FieldType [{$type->name}] → class [{$type->object}] does not exist");
            } elseif (!is_subclass_of($type->object, AbstractField::class)) {
                $broken++;
                yield $this->fail("FieldType [{$type->name}] → class [{$type->object}] does not extend AbstractField");
            }
        }

        if ($broken === 0) {
            yield $this->pass('All ' . $types->count() . ' field types resolve to valid classes');
        }
    }
}
