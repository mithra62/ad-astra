<?php

namespace AdAstra\Console\Commands;

use AdAstra\Support\SystemInspector;
use Illuminate\Console\Command;

class SystemConfigCommand extends Command
{
    protected $signature = 'adastra:config
                            {--published : Only show package config already published to /config}
                            {--vendor : Show config files found inside /vendor}
                            {--app : Show all application config files}
                            {--json : Output JSON}';

    protected $description = 'Inspect Laravel package and application configuration files';

    public function handle(SystemInspector $inspector): int
    {
        if ($this->option('vendor')) {
            return $this->vendorFiles($inspector);
        }

        if ($this->option('app')) {
            return $this->applicationFiles($inspector);
        }

        $config = $this->option('published')
            ? $inspector->publishedPackageConfig()
            : $inspector->publishableConfig();

        if ($this->option('json')) {
            $this->json($config);

            return self::SUCCESS;
        }

        if (empty($config)) {
            $this->info('No package configuration files found.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'Package / Provider',
                'Source',
                'Destination',
                'Published',
                'Modified',
            ],
            collect($config)
                ->map(fn (array $item) => [
                    $item['provider'],
                    $item['source'],
                    $item['destination'],
                    $item['published'] ? 'Yes' : 'No',
                    match ($item['modified']) {
                        true => 'Yes',
                        false => 'No',
                        null => '-',
                    },
                ])
                ->all()
        );

        return self::SUCCESS;
    }

    protected function vendorFiles(SystemInspector $inspector): int
    {
        $files = $inspector->vendorConfigFiles();

        if ($this->option('json')) {
            $this->json($files);

            return self::SUCCESS;
        }

        $this->table(
            ['Package', 'Config', 'Path'],
            collect($files)
                ->map(fn (array $file) => [
                    $file['package'] ?? 'Unknown',
                    $file['filename'],
                    $file['path'],
                ])
                ->all()
        );

        return self::SUCCESS;
    }

    protected function applicationFiles(SystemInspector $inspector): int
    {
        $files = $inspector->applicationConfigFiles();

        if ($this->option('json')) {
            $this->json($files);

            return self::SUCCESS;
        }

        $this->table(
            ['Config', 'Path'],
            collect($files)
                ->map(fn (array $file) => [
                    $file['name'],
                    $file['path'],
                ])
                ->all()
        );

        return self::SUCCESS;
    }

    protected function json(array $data): void
    {
        $this->line(
            json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
