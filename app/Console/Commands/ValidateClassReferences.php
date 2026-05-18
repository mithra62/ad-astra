<?php

namespace App\Console\Commands;

use App\EntryTypes\AbstractEntryType;
use App\Field\AbstractField;
use App\Models\EntryBehavior;
use App\Models\Field\Type as FieldType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;

class ValidateClassReferences extends Command
{
    protected $signature = 'app:validate-class-references';
    protected $description = 'Verify that all class-name strings stored in the database still resolve to valid classes.';

    public function handle(): int
    {
        $errors = 0;

        $this->info('Checking entry_behaviors.class (morphMap keys) …');
        EntryBehavior::all()->each(function (EntryBehavior $behavior) use (&$errors) {
            $class = Relation::getMorphedModel($behavior->class);

            if ($class === null) {
                $this->error("  EntryBehavior [{$behavior->handle}] → morph key [{$behavior->class}] is not registered in the morphMap.");
                $errors++;
                return;
            }

            if (!class_exists($class)) {
                $this->error("  EntryBehavior [{$behavior->handle}] → class [{$class}] does not exist.");
                $errors++;
            } elseif (!is_subclass_of($class, AbstractEntryType::class)) {
                $this->error("  EntryBehavior [{$behavior->handle}] → class [{$class}] does not extend AbstractEntryType.");
                $errors++;
            } else {
                $this->line("  <fg=green>✓</> {$behavior->handle} → {$behavior->class} ({$class})");
            }
        });

        $this->newLine();
        $this->info('Checking field_types.object …');
        FieldType::all()->each(function (FieldType $type) use (&$errors) {
            if (!class_exists($type->object)) {
                $this->error("  FieldType [{$type->name}] → class [{$type->object}] does not exist.");
                $errors++;
            } elseif (!is_subclass_of($type->object, AbstractField::class)) {
                $this->error("  FieldType [{$type->name}] → class [{$type->object}] does not extend AbstractField.");
                $errors++;
            } else {
                $this->line("  <fg=green>✓</> {$type->name} → {$type->object}");
            }
        });

        $this->newLine();

        if ($errors > 0) {
            $this->error("Found {$errors} broken class reference(s). Fix them before deploying.");
            return self::FAILURE;
        }

        $this->info('All class references are valid.');
        return self::SUCCESS;
    }
}
