<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Assets\ViteManifestCheck;
use AdAstra\Doctor\Checks\Templates\EntryTemplatesCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\EntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatesAndAssetsChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_templates_pass_on_fresh_database(): void
    {
        config(['doctor.required_templates' => []]);

        $results = iterator_to_array((new EntryTemplatesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_entry_templates_fail_on_broken_stored_reference(): void
    {
        config(['doctor.required_templates' => []]);
        EntryType::create([
            'name' => 'Broken',
            'handle' => 'doctor_broken_tpl',
            'default_template' => 'doctor.nonexistent-template',
        ]);

        $results = iterator_to_array((new EntryTemplatesCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('doctor.nonexistent-template', $results[0]->message);
        $this->assertStringContainsString('doctor_broken_tpl', $results[0]->message);
    }

    public function test_entry_templates_warn_on_missing_fallback_template(): void
    {
        config(['doctor.required_templates' => ['doctor.no-such-fallback']]);

        $results = iterator_to_array((new EntryTemplatesCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
        $this->assertStringContainsString('doctor.no-such-fallback', $results[0]->message);
    }

    public function test_vite_manifest_passes_outside_production(): void
    {
        $results = iterator_to_array((new ViteManifestCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_vite_manifest_fails_in_production_when_missing(): void
    {
        $this->app['env'] = 'production';
        config(['doctor.vite_manifest_path' => storage_path('framework/doctor-no-such-manifest.json')]);

        $results = iterator_to_array((new ViteManifestCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('npm run build', $results[0]->fixCommand);
    }

    public function test_vite_manifest_passes_in_production_when_present(): void
    {
        $this->app['env'] = 'production';
        $manifest = storage_path('framework/doctor-manifest-' . uniqid() . '.json');
        file_put_contents($manifest, '{}');
        config(['doctor.vite_manifest_path' => $manifest]);

        try {
            $results = iterator_to_array((new ViteManifestCheck())->run(), false);
        } finally {
            @unlink($manifest);
        }

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }
}
