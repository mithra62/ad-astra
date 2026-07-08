<?php

namespace AdAstra\Doctor\Checks\Media;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Models\Media\Library;

class UploadLimitsCheck extends AbstractDoctorCheck
{
    protected string $id = 'media.upload-limits';
    protected string $name = 'PHP upload limits vs media libraries';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        // Library max_size is stored in MB (0/null = unlimited); PHP caps
        // every upload at min(upload_max_filesize, post_max_size) regardless.
        $phpLimit = min(
            $this->bytes((string) ini_get('upload_max_filesize')),
            $this->bytes((string) ini_get('post_max_size')),
        );

        if ($phpLimit === INF) {
            yield $this->pass('PHP upload limits are unlimited');

            return;
        }

        $phpLimitMb = round($phpLimit / 1048576, 1);
        $mismatched = 0;

        foreach (Library::where('max_size', '>', 0)->get() as $library) {
            if ($library->max_size * 1048576 > $phpLimit) {
                $mismatched++;
                yield $this->warn(
                    "Library [{$library->handle}] allows {$library->max_size} MB uploads but PHP caps requests at {$phpLimitMb} MB",
                    details: 'Checked against the CLI php.ini — confirm the web SAPI (FPM/Apache) uses the same upload_max_filesize/post_max_size',
                    fixCommand: 'raise upload_max_filesize and post_max_size in php.ini, or lower the library max size',
                );
            }
        }

        if ($mismatched === 0) {
            yield $this->pass("PHP upload limits accommodate all media libraries (effective limit: {$phpLimitMb} MB)");
        }
    }

    private function bytes(string $value): float
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return INF;
        }

        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
