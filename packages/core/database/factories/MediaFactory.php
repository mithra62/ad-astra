<?php

namespace Database\Factories;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
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

    /**
     * Create a fresh StatusGroup + default Status and pin this media to it.
     * Each call creates a NEW group — to share a group across multiple media
     * factories, build the status manually and pass status_id directly.
     */
    public function withStatus(bool $isPublic = false): static
    {
        return $this->state(function () use ($isPublic) {
            $group = StatusGroup::factory()->create();
            $status = Status::factory()->create([
                'status_group_id' => $group->id,
                'is_default'      => true,
                'is_public'       => $isPublic,
            ]);

            return [
                'status_id'        => $status->id,
                'status_handle'    => $status->handle,
                'status_is_public' => $status->is_public,
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
