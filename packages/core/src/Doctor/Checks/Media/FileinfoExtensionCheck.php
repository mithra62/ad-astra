<?php

namespace AdAstra\Doctor\Checks\Media;

use AdAstra\Doctor\AbstractDoctorCheck;

class FileinfoExtensionCheck extends AbstractDoctorCheck
{
    protected string $id = 'media.fileinfo-extension';
    protected string $name = 'fileinfo extension';

    public function run(): iterable
    {
        if (!extension_loaded('fileinfo')) {
            yield $this->warn(
                'The fileinfo PHP extension is not loaded — upload MIME-type validation will fail',
                fixCommand: 'enable the fileinfo extension in php.ini',
            );

            return;
        }

        yield $this->pass('fileinfo extension loaded');
    }
}
