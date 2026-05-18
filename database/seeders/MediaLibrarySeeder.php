<?php

namespace Database\Seeders;

use App\Models\Media\Library as MediaLibrary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MediaLibrarySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed the default avatars library used by the User model's avatar() helper.
        MediaLibrary::firstOrCreate(
            ['handle' => 'avatars'],
            [
                'name'          => 'User Avatars',
                'adapter'       => config('filesystems.default', 'local'),
                'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                'max_size'      => 2,
                'sort_order'    => 0,
            ]
        );
    }
}
