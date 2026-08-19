<?php

namespace AdAstra\Console\Commands;

use AdAstra\Support\SystemInspector;
use Illuminate\Console\Command;

class SystemInspectCommand extends Command
{
    protected $signature = 'adastra:inspect {--json : Output JSON}';

    protected $description = 'Inspect third-party Laravel packages and configuration';

    public function handle(SystemInspector $inspector): int
    {
        $data = [
            'providers' => $inspector->thirdPartyProviders(),
            'published_config' => $inspector->publishedPackageConfig(),
            'publishable_config' => $inspector->publishableConfig(),
            'vendor_config' => $inspector->vendorConfigFiles(),
        ];

        if ($this->option('json')) {
            $this->line(
                json_encode(
                    $data,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Third-Party Providers');

        $this->table(
            ['Package', 'Provider'],
            collect($data['providers'])
                ->map(fn ($item) => [
                    $item['package'],
                    $item['class'],
                ])
                ->all()
        );

        $this->newLine();
        $this->components->info('Published Package Configuration');

        $this->table(
            ['Provider', 'Config', 'Modified'],
            collect($data['published_config'])
                ->map(fn ($item) => [
                    $item['provider'],
                    basename($item['destination']),
                    match ($item['modified']) {
                        true => 'Yes',
                        false => 'No',
                        null => '-',
                    },
                ])
                ->all()
        );

        $this->newLine();

        $this->components->info(sprintf(
            '%d third-party providers, %d published configs, %d vendor config files.',
            count($data['providers']),
            count($data['published_config']),
            count($data['vendor_config']),
        ));

        return self::SUCCESS;
    }
}
