<?php

namespace Tests\Feature\Console;

use AdAstra\Services\Media\NullTransformationDriver;
use AdAstra\Services\Media\TransformationDriverInterface;
use AdAstra\Models\User;
use Database\Seeders\EntryBehaviorSeeder;
use Database\Seeders\FieldTypeSeeder;
use Database\Seeders\MediaLibrarySeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedHealthyInstall(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(EntryBehaviorSeeder::class);
        $this->seed(FieldTypeSeeder::class);
        $this->seed(MediaLibrarySeeder::class);
        User::factory()->create()->assignRole('super admin');
    }

    public function test_healthy_install_exits_zero(): void
    {
        $this->seedHealthyInstall();

        $this->artisan('adastra:doctor')->assertExitCode(0);
    }

    public function test_broken_install_exits_two(): void
    {
        $this->seedHealthyInstall();
        Permission::where('name', 'create entry')->delete();

        $this->artisan('adastra:doctor')->assertExitCode(2);
    }

    public function test_strict_promotes_warnings_to_exit_one(): void
    {
        $this->seedHealthyInstall();

        // Force exactly one warning: the null transformation driver.
        $this->app->bind(TransformationDriverInterface::class, fn () => new NullTransformationDriver());

        $this->artisan('adastra:doctor')->assertExitCode(0);
        $this->artisan('adastra:doctor --strict')->assertExitCode(1);
    }

    public function test_json_output_contains_the_envelope(): void
    {
        $this->seedHealthyInstall();

        Artisan::call('adastra:doctor', ['--format' => 'json']);
        $envelope = json_decode(Artisan::output(), true);

        $this->assertIsArray($envelope);
        // Assert on keys, not full snapshots, so adding checks doesn't churn this test.
        foreach (['schema', 'generated_at', 'versions', 'summary', 'results'] as $key) {
            $this->assertArrayHasKey($key, $envelope);
        }
        $this->assertSame(1, $envelope['schema']);
        foreach (['adastra', 'laravel', 'php'] as $key) {
            $this->assertArrayHasKey($key, $envelope['versions']);
        }
        foreach (['passed', 'warnings', 'failures', 'skipped', 'exit_code'] as $key) {
            $this->assertArrayHasKey($key, $envelope['summary']);
        }
        $this->assertNotEmpty($envelope['results']);
        foreach (['id', 'category', 'status', 'message'] as $key) {
            $this->assertArrayHasKey($key, $envelope['results'][0]);
        }
    }

    public function test_only_runs_matching_checks_plus_dependencies(): void
    {
        $this->seedHealthyInstall();

        Artisan::call('adastra:doctor', ['--format' => 'json', '--only' => 'permissions']);
        $envelope = json_decode(Artisan::output(), true);

        $categories = array_unique(array_column($envelope['results'], 'category'));
        sort($categories);

        // Permission checks plus their pulled-in database dependencies — nothing else.
        $this->assertSame(['database', 'permissions'], $categories);
    }
}
