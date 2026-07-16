<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Cache\CacheRoundtripCheck;
use AdAstra\Doctor\DoctorStatus;
use Tests\TestCase;

class CacheRoundtripCheckTest extends TestCase
{
    public function test_passes_on_working_store(): void
    {
        $results = iterator_to_array((new CacheRoundtripCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_fails_on_unresolvable_store(): void
    {
        $original = config('cache.default');
        config(['cache.default' => 'doctor-test-bogus']);

        $results = iterator_to_array((new CacheRoundtripCheck())->run(), false);

        config(['cache.default' => $original]);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertNotNull($results[0]->fixCommand);
    }
}
