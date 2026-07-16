<?php

namespace AdAstra\Doctor\Checks\Cache;

use AdAstra\Doctor\AbstractDoctorCheck;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheRoundtripCheck extends AbstractDoctorCheck
{
    protected string $id = 'cache.roundtrip';
    protected string $name = 'Cache round-trip';

    public function run(): iterable
    {
        // The Settings layer and Spatie permissions cache everything through
        // this store; a broken store doesn't error — it silently hammers the
        // DB on every request. Like storage.writable, this probe (write +
        // read + delete of one throwaway key) is an allowed exception to the
        // read-only rule.
        $store = (string) config('cache.default');
        $key = 'adastra-doctor-probe-' . uniqid();

        try {
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable $e) {
            yield $this->fail(
                "Cache store [{$store}] is unusable",
                details: get_class($e),
                fixCommand: 'verify the CACHE_* settings in .env and that the backing service is running',
            );

            return;
        }

        if ($value !== 'ok') {
            yield $this->fail(
                "Cache store [{$store}] did not return the value written to it",
                fixCommand: 'verify the CACHE_* settings in .env',
            );

            return;
        }

        yield $this->pass("Cache round-trip OK ({$store})");
    }
}
