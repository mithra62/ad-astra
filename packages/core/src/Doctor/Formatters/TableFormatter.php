<?php

namespace AdAstra\Doctor\Formatters;

use AdAstra\Doctor\DoctorReport;
use AdAstra\Doctor\DoctorStatus;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;

/**
 * Human-readable console report grouped by category, in the codebase's
 * plain info()/line() style with inline ANSI tags.
 */
final class TableFormatter
{
    public function render(DoctorReport $report, OutputStyle $output): void
    {
        $output->writeln('<options=bold>AdAstra Doctor</>');

        foreach ($report->byCategory() as $category => $entries) {
            $output->newLine();
            $output->writeln('<options=bold>' . Str::headline($category) . '</>');

            foreach ($entries as $entry) {
                foreach ($entry['results'] as $result) {
                    $output->writeln('  ' . $this->glyph($result->status) . ' ' . $result->message);

                    if ($result->details !== null) {
                        $output->writeln("      <fg=gray>{$result->details}</>");
                    }
                    if ($result->fixCommand !== null) {
                        $output->writeln("      <fg=gray>fix: {$result->fixCommand}</>");
                    }
                }
            }
        }

        $output->newLine();
        $output->writeln('<options=bold>Summary</>');
        $output->writeln("  {$report->passed()} passed, {$report->warnings()} warnings, {$report->failures()} failures, {$report->skipped()} skipped");
    }

    private function glyph(DoctorStatus $status): string
    {
        return match ($status) {
            DoctorStatus::Pass => '<fg=green>✓</>',
            DoctorStatus::Warn => '<fg=yellow>!</>',
            DoctorStatus::Fail => '<fg=red>✗</>',
            DoctorStatus::Skip => '<fg=gray>–</>',
        };
    }
}
