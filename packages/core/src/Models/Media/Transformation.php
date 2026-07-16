<?php

namespace AdAstra\Models\Media;

use AdAstra\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Transformation extends Model
{
    use HasFactory;

    protected $table = 'media_transformations';

    protected $fillable = [
        'media_id',
        'key',
        'disk',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'params',
        'driver',
        'status',
        'error',
    ];

    protected $casts = [
        'params' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * @param string $path
     * @param int $size
     * @param int|null $width
     * @param int|null $height
     * @return void
     */
    public function markComplete(string $path, int $size, ?int $width = null, ?int $height = null): void
    {
        $this->update(
            compact('path', 'size', 'width', 'height')
            + ['status' => 'complete', 'error' => null]
        );
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
