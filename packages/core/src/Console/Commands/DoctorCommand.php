<?php

namespace AdAstra\Console\Commands;

use AdAstra\Doctor\DoctorRunner;
use AdAstra\Doctor\Formatters\JsonFormatter;
use AdAstra\Doctor\Formatters\TableFormatter;
use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'adastra:doctor
        {--format=table : Output format (table, json)}
        {--strict : Exit non-zero when warnings are present}
        {--only= : Comma-separated check or category ids to run}
        {--except= : Comma-separated check or category ids to skip}';

    protected $description = 'Diagnose the health of this AdAstra installation. Read-only: reports and recommends, never repairs.';

    public function handle(DoctorRunner $runner): int
    {
        $report = $runner->run(
            only: $this->idList('only'),
            except: $this->idList('except'),
        );

        $strict = (bool) $this->option('strict');

        if ($this->option('format') === 'json') {
            (new JsonFormatter())->render($report, $this->output, $strict);
        } else {
            (new TableFormatter())->render($report, $this->output);
        }

        return $report->exitCode($strict);
    }

    /**
     * @return list<string>
     */
    private function idList(string $option): array
    {
        $raw = (string) $this->option($option);

        return $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
