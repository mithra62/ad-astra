<?php

namespace AdAstra\Doctor\Checks\Storage;

use AdAstra\Doctor\AbstractDoctorCheck;

class PublicSymlinkCheck extends AbstractDoctorCheck
{
    protected string $id = 'storage.public-symlink';
    protected string $name = 'Public storage symlink';

    public function run(): iterable
    {
        $links = (array) config('filesystems.links', []);

        if ($links === []) {
            yield $this->skip('No storage links configured in filesystems.php');

            return;
        }

        $broken = 0;

        foreach ($links as $link => $target) {
            if (!file_exists($link)) {
                $broken++;
                yield $this->fail(
                    'Missing storage link [' . basename(dirname($link)) . '/' . basename($link) . ']',
                    fixCommand: 'php artisan storage:link',
                );
                continue;
            }

            if (realpath($link) !== realpath($target)) {
                $broken++;
                yield $this->fail(
                    'Storage link [' . basename(dirname($link)) . '/' . basename($link) . '] points at the wrong target',
                    fixCommand: 'remove the link and re-run php artisan storage:link',
                );
            }
        }

        if ($broken === 0) {
            yield $this->pass(count($links) === 1 ? 'Public symlink exists' : 'All ' . count($links) . ' storage links exist');
        }
    }
}
