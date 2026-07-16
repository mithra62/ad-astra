<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Media\AvatarsLibraryCheck;
use AdAstra\Doctor\Checks\Media\FileinfoExtensionCheck;
use AdAstra\Doctor\Checks\Media\UploadLimitsCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\Media\Library;
use Database\Seeders\MediaLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_fileinfo_extension_is_loaded(): void
    {
        // fileinfo ships enabled by default; the test PHP must have it for
        // media MIME validation to be testable at all.
        $results = iterator_to_array((new FileinfoExtensionCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_avatars_library_passes_after_seeding(): void
    {
        $this->seed(MediaLibrarySeeder::class);

        $results = iterator_to_array((new AvatarsLibraryCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_avatars_library_fails_when_missing(): void
    {
        $results = iterator_to_array((new AvatarsLibraryCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('MediaLibrarySeeder', $results[0]->fixCommand);
    }

    public function test_upload_limits_pass_with_small_library_limit(): void
    {
        Library::create([
            'name' => 'Small',
            'handle' => 'doctor-small-' . uniqid(),
            'adapter' => 'local',
            'max_size' => 1,
        ]);

        $results = iterator_to_array((new UploadLimitsCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_upload_limits_warn_when_library_exceeds_php_limit(): void
    {
        if (min(
            (string) ini_get('upload_max_filesize'),
            (string) ini_get('post_max_size'),
        ) === '-1') {
            $this->markTestSkipped('PHP upload limits are unlimited on this runtime.');
        }

        Library::create([
            'name' => 'Huge',
            'handle' => 'doctor-huge-' . uniqid(),
            'adapter' => 'local',
            'max_size' => 999999, // ~1 TB, beyond any sane php.ini
        ]);

        $results = iterator_to_array((new UploadLimitsCheck())->run(), false);

        $this->assertSame(DoctorStatus::Warn, $results[0]->status);
        $this->assertStringContainsString('999999', $results[0]->message);
    }
}
