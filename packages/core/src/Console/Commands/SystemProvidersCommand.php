<?php

namespace AdAstra\Console\Commands;

use AdAstra\Support\SystemInspector;
use Illuminate\Console\Command;

class SystemProvidersCommand extends Command
{
    protected $signature = 'adastra:providers
                            {--all : Include application and Laravel providers}
                            {--json : Output JSON}';

    protected $description = 'List registered Laravel service providers';

    public function handle(SystemInspector $inspector): int
    {
        $providers = $this->option('all')
            ? $inspector->providers()
            : $inspector->thirdPartyProviders();

        if ($this->option('json')) {
            $this->line(
                json_encode(
                    $providers,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );

            return self::SUCCESS;
        }

        if (empty($providers)) {
            $this->info('No providers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Package', 'Provider', 'Source'],
            collect($providers)
                ->map(fn (array $provider) => [
                    $provider['package'] ?? 'Application / Framework',
                    $provider['class'],
                    $provider['file'] ?? 'Unknown',
                ])
                ->all()
        );

        return self::SUCCESS;
    }
}
