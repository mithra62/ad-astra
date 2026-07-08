<?php

namespace AdAstra\Doctor\Formatters;

use AdAstra\Doctor\DoctorReport;
use AdAstra\Doctor\Support\Version;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;

/**
 * Machine-readable report envelope. Keys are snake_case and stable within a
 * schema version: new keys may be added, none removed or renamed. Bump
 * SCHEMA only on breaking shape changes.
 */
final class JsonFormatter
{
    public const SCHEMA = 1;

    public function render(DoctorReport $report, OutputStyle $output, bool $strict = false): void
    {
        $results = [];
        foreach ($report->entries() as $entry) {
            foreach ($entry['results'] as $result) {
                $results[] = array_merge(
                    ['id' => $entry['id'], 'category' => $entry['category']],
                    $result->toArray(),
                );
            }
        }

        $envelope = [
            'schema' => self::SCHEMA,
            'generated_at' => Carbon::now('UTC')->toIso8601ZuluString(),
            'versions' => [
                'adastra' => Version::current(),
                'laravel' => Application::VERSION,
                'php' => PHP_VERSION,
            ],
            'summary' => [
                'passed' => $report->passed(),
                'warnings' => $report->warnings(),
                'failures' => $report->failures(),
                'skipped' => $report->skipped(),
                'exit_code' => $report->exitCode($strict),
            ],
            'results' => $results,
        ];

        $output->writeln(json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
