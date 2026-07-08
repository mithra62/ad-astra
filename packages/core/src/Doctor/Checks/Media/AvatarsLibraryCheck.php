<?php

namespace AdAstra\Doctor\Checks\Media;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Models\Media\Library;

class AvatarsLibraryCheck extends AbstractDoctorCheck
{
    protected string $id = 'media.avatars-library';
    protected string $name = 'Avatars media library';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        if (!Library::where('handle', 'avatars')->exists()) {
            yield $this->fail(
                'The [avatars] media library is missing — User::avatar() depends on it',
                fixCommand: 'php artisan db:seed --class=MediaLibrarySeeder',
            );

            return;
        }

        yield $this->pass('Avatars library present');
    }
}
