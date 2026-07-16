<?php

namespace AdAstra\Doctor\Checks\Assets;

use AdAstra\Doctor\AbstractDoctorCheck;

class ViteManifestCheck extends AbstractDoctorCheck
{
    protected string $id = 'assets.vite-manifest';
    protected string $name = 'Vite build manifest';

    public function run(): iterable
    {
        if (!app()->environment('production')) {
            yield $this->pass('Build manifest not required outside production');

            return;
        }

        $path = config('doctor.vite_manifest_path') ?: public_path('build/manifest.json');

        if (!file_exists($path)) {
            yield $this->fail(
                'Vite build manifest missing — compiled assets were never built, the admin will not render',
                fixCommand: 'run npm run build before deploying',
            );

            return;
        }

        yield $this->pass('Vite build manifest present');
    }
}
