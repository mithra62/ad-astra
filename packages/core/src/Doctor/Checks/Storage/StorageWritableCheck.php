<?php

namespace AdAstra\Doctor\Checks\Storage;

use AdAstra\Doctor\AbstractDoctorCheck;
use Throwable;

class StorageWritableCheck extends AbstractDoctorCheck
{
    protected string $id = 'storage.writable';
    protected string $name = 'Storage writable';

    public function run(): iterable
    {
        // A real write proves more than is_writable(), which lies on some
        // mounts. This is the one write doctor is allowed to make.
        $probe = storage_path('framework/doctor-probe-' . uniqid() . '.tmp');

        try {
            $written = @file_put_contents($probe, 'adastra-doctor');

            if ($written === false) {
                yield $this->fail(
                    'Cannot write to storage/framework',
                    fixCommand: 'fix filesystem ownership/permissions on the storage directory',
                );

                return;
            }
        } catch (Throwable) {
            yield $this->fail(
                'Cannot write to storage/framework',
                fixCommand: 'fix filesystem ownership/permissions on the storage directory',
            );

            return;
        } finally {
            @unlink($probe);
        }

        yield $this->pass('Storage writable');
    }
}
