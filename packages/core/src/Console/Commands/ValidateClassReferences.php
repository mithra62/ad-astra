<?php

namespace AdAstra\Console\Commands;

use AdAstra\Doctor\Checks\EntrySystem\BehaviorClassReferencesCheck;
use AdAstra\Doctor\Checks\FieldSystem\FieldTypeClassReferencesCheck;
use AdAstra\Doctor\DoctorStatus;
use Illuminate\Console\Command;

/**
 * Thin wrapper kept for muscle memory and existing docs — the actual
 * validation lives in the two doctor checks, which `adastra:doctor`
 * runs as part of the full health report.
 */
class ValidateClassReferences extends Command
{
    protected $signature = 'adastra:validate-class-references';
    protected $description = 'Verify that all class-name strings stored in the database still resolve to valid classes.';

    // Pre-rename signature, kept as a hidden alias through alpha.
    protected $aliases = ['app:validate-class-references'];

    public function handle(): int
    {
        $errors = 0;

        $this->info('Checking entry_behaviors.class (morphMap keys) …');
        $errors += $this->runCheck(new BehaviorClassReferencesCheck());

        $this->newLine();
        $this->info('Checking field_types.object …');
        $errors += $this->runCheck(new FieldTypeClassReferencesCheck());

        $this->newLine();

        if ($errors > 0) {
            $this->error("Found {$errors} broken class reference(s). Fix them before deploying.");
            return self::FAILURE;
        }

        $this->info('All class references are valid.');
        return self::SUCCESS;
    }

    private function runCheck($check): int
    {
        $errors = 0;

        foreach ($check->run() as $result) {
            if ($result->status === DoctorStatus::Fail) {
                $errors++;
                $this->error('  ' . $result->message);
            } else {
                $this->line('  <fg=green>✓</> ' . $result->message);
            }
        }

        return $errors;
    }
}
