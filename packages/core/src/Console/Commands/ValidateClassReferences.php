<?php

namespace AdAstra\Console\Commands;

use AdAstra\Doctor\DoctorRunner;
use AdAstra\Doctor\DoctorStatus;
use Illuminate\Console\Command;

/**
 * Thin wrapper kept for muscle memory and existing docs — the actual
 * validation lives in the two doctor checks, which `adastra:doctor` runs
 * as part of the full health report. Routing through DoctorRunner (rather
 * than executing the checks directly) buys the runner's guarantees: a
 * crashing check becomes a contained failure, and an unmigrated or
 * unreachable database cascades to SKIP with the real cause reported
 * first instead of an uncaught QueryException.
 */
class ValidateClassReferences extends Command
{
    protected $signature = 'adastra:validate-class-references';
    protected $description = 'Verify that all class-name strings stored in the database still resolve to valid classes.';

    // Pre-rename signature, kept as a hidden alias through alpha.
    protected $aliases = ['app:validate-class-references'];

    public function handle(DoctorRunner $runner): int
    {
        // Exact check ids — per --only semantics these run even if a host
        // has disabled them in config/doctor.php.
        $report = $runner->run(only: [
            'entry-system.behavior-classes',
            'field-system.type-classes',
        ]);

        foreach ($report->entries() as $entry) {
            $this->info($entry['name'] . ' …');

            foreach ($entry['results'] as $result) {
                match ($result->status) {
                    DoctorStatus::Fail => $this->error('  ✗ ' . $result->message),
                    DoctorStatus::Warn => $this->line('  <fg=yellow>!</> ' . $result->message),
                    DoctorStatus::Skip => $this->line('  <fg=gray>–</> ' . $result->message),
                    DoctorStatus::Pass => $this->line('  <fg=green>✓</> ' . $result->message),
                };
            }

            $this->newLine();
        }

        if ($report->failures() > 0) {
            $this->error("Found {$report->failures()} problem(s). Fix them before deploying.");

            return self::FAILURE;
        }

        $this->info('All class references are valid.');

        return self::SUCCESS;
    }
}
