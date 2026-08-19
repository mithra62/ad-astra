<?php

namespace AdAstra\Support;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use SplFileInfo;

class SystemInspector
{
    /**
     * Return all currently loaded service providers.
     */
    public function providers(): array
    {
        return collect(app()->getLoadedProviders())
            ->keys()
            ->map(fn (string $provider) => $this->describeProvider($provider))
            ->sortBy('class')
            ->values()
            ->all();
    }

    /**
     * Return only providers whose source lives beneath /vendor.
     */
    public function thirdPartyProviders(): array
    {
        return collect($this->providers())
            ->filter(fn (array $provider) => $provider['third_party'])
            ->values()
            ->all();
    }

    /**
     * Return config files packages have explicitly registered
     * with Laravel's publishing system.
     */
    public function publishableConfig(): array
    {
        $results = [];

        foreach (ServiceProvider::publishableProviders() as $provider) {
            foreach (ServiceProvider::pathsToPublish($provider) as $source => $destination) {
                if (! $this->isConfigPath($source, $destination)) {
                    continue;
                }

                $results[] = [
                    'provider' => $provider,
                    'source' => $source,
                    'destination' => $destination,
                    'published' => file_exists($destination),
                    'modified' => $this->isModifiedPublishedFile(
                        $source,
                        $destination
                    ),
                ];
            }
        }

        return collect($results)
            ->sortBy([
                ['provider', 'asc'],
                ['destination', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Return package config files physically located beneath /vendor.
     *
     * This catches packages using mergeConfigFrom() even when they do
     * not expose the file through vendor:publish.
     */
    public function vendorConfigFiles(): array
    {
        $vendor = realpath(base_path('vendor'));

        if (! $vendor) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $vendor,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $pathname = $file->getPathname();

            if (! preg_match(
                '#[/\\\\]config[/\\\\][^/\\\\]+\.php$#i',
                $pathname
            )) {
                continue;
            }

            $files[] = [
                'package' => $this->packageFromVendorPath($pathname),
                'path' => $pathname,
                'filename' => $file->getFilename(),
            ];
        }

        return collect($files)
            ->sortBy([
                ['package', 'asc'],
                ['filename', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Config files currently present in the application's /config directory.
     */
    public function applicationConfigFiles(): array
    {
        $path = config_path();

        if (! is_dir($path)) {
            return [];
        }

        return collect(glob($path.'/*.php') ?: [])
            ->map(function (string $file) {
                return [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'path' => $file,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Application config files that match a registered package
     * publishing destination.
     */
    public function publishedPackageConfig(): array
    {
        return collect($this->publishableConfig())
            ->filter(fn (array $config) => $config['published'])
            ->values()
            ->all();
    }

    protected function describeProvider(string $provider): array
    {
        $file = null;

        try {
            $reflection = new ReflectionClass($provider);
            $file = $reflection->getFileName() ?: null;
        } catch (\ReflectionException) {
            // Provider may no longer be reflectable for some reason.
        }

        return [
            'class' => $provider,
            'file' => $file,
            'third_party' => $file
                ? $this->isVendorPath($file)
                : false,
            'package' => $file && $this->isVendorPath($file)
                ? $this->packageFromVendorPath($file)
                : null,
        ];
    }

    protected function isVendorPath(string $path): bool
    {
        $vendor = realpath(base_path('vendor'));

        if (! $vendor) {
            return false;
        }

        $real = realpath($path);

        return $real !== false
            && str_starts_with(
                $this->normalizePath($real),
                $this->normalizePath($vendor).'/'
            );
    }

    protected function isConfigPath(
        string $source,
        string $destination
    ): bool {
        $destination = $this->normalizePath($destination);
        $config = $this->normalizePath(config_path());

        return str_starts_with($destination, $config.'/')
            && pathinfo($destination, PATHINFO_EXTENSION) === 'php';
    }

    protected function isModifiedPublishedFile(
        string $source,
        string $destination
    ): ?bool {
        if (! file_exists($source) || ! file_exists($destination)) {
            return null;
        }

        return hash_file('sha256', $source)
            !== hash_file('sha256', $destination);
    }

    protected function packageFromVendorPath(string $path): ?string
    {
        $vendor = $this->normalizePath(base_path('vendor'));
        $path = $this->normalizePath($path);

        if (! str_starts_with($path, $vendor.'/')) {
            return null;
        }

        $relative = substr($path, strlen($vendor) + 1);
        $parts = explode('/', $relative);

        if (count($parts) < 2) {
            return null;
        }

        /*
         * Ignore Composer's own internal directories.
         */
        if ($parts[0] === 'composer') {
            return null;
        }

        return $parts[0].'/'.$parts[1];
    }

    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }
}
