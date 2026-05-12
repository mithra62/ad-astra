<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $ext      = fake()->randomElement(['jpg', 'png', 'pdf', 'mp4']);
        $fileName = Str::uuid() . '.' . $ext;

        return [
            'library_id'    => Library::factory(),
            'name'          => fake()->words(2, true),
            'file_name'     => $fileName,
            'original_name' => fake()->word() . '.' . $ext,
            'mime_type'     => $this->mimeForExt($ext),
            'disk'          => 'local',
            'path'          => 'uploads/' . $fileName,
            'size'          => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'sort_order'    => 0,
        ];
    }

    public function image(): static
    {
        return $this->state(function () {
            $ext      = fake()->randomElement(['jpg', 'png', 'webp']);
            $fileName = Str::uuid() . '.' . $ext;
            return [
                'file_name'     => $fileName,
                'original_name' => 'photo.' . $ext,
                'mime_type'     => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                'path'          => 'uploads/' . $fileName,
            ];
        });
    }

    public function pdf(): static
    {
        return $this->state(function () {
            $fileName = Str::uuid() . '.pdf';
            return [
                'file_name'     => $fileName,
                'original_name' => 'document.pdf',
                'mime_type'     => 'application/pdf',
                'path'          => 'uploads/' . $fileName,
            ];
        });
    }

    private function mimeForExt(string $ext): string
    {
        return match ($ext) {
            'jpg'  => 'image/jpeg',
            'png'  => 'image/png',
            'pdf'  => 'application/pdf',
            'mp4'  => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
