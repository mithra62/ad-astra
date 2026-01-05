<?php
namespace App\Services;

class FilesService
{
    protected array $mime_map = [
        'all' => '*/*',
        'image' => 'image/*',
        'video' => 'video/*',
        'audio' => 'audio/*',
        'document' => 'application/pdf',
        'archive' => 'application/zip',
    ];

    public function getAllowedMimeTypes(): array
    {
        return $this->mime_map;
    }
}
