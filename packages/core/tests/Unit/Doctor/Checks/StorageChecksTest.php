<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Storage\PublicSymlinkCheck;
use AdAstra\Doctor\Checks\Storage\StorageWritableCheck;
use AdAstra\Doctor\DoctorStatus;
use Tests\TestCase;

class StorageChecksTest extends TestCase
{
    public function test_storage_is_writable(): void
    {
        $results = iterator_to_array((new StorageWritableCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_symlink_check_skips_when_no_links_configured(): void
    {
        config(['filesystems.links' => []]);

        $results = iterator_to_array((new PublicSymlinkCheck())->run(), false);

        $this->assertSame(DoctorStatus::Skip, $results[0]->status);
    }

    public function test_symlink_check_fails_on_missing_link(): void
    {
        config(['filesystems.links' => [
            base_path('doctor-missing-link') => storage_path('app/public'),
        ]]);

        $results = iterator_to_array((new PublicSymlinkCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertSame('php artisan storage:link', $results[0]->fixCommand);
    }
}
