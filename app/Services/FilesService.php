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

    public function compileMimeTypes(array $mime_types): string
    {
        $return = '';
        foreach($mime_types AS $type) {
            $return .= $this->mime_map[$type] . ',';
        }

        return $return;
    }

    public function convertMbToBytes(float $mb_value): float
    {
        return $mb_value * 1024 * 1024;
    }

}
