<?php

namespace App\Models;

use App\Traits\Field\Fieldable;
use App\Traits\HasTransformations;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Query\Builder;

class Media extends Model
{
    use HasFactory;
    use Fieldable;
    use HasTransformations;
    use SoftDeletes;

    protected $fillable = [
        'library_id',
        'status_id',
        'status_handle',
        'status_is_public',
        'name',
        'file_name',
        'original_name',
        'mime_type',
        'disk',
        'path',
        'size',
        'sort_order',
    ];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
        'status_is_public' => 'boolean',
    ];

    public function library(): BelongsTo
    {
        return $this->belongsTo(Media\Library::class, 'library_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Public media only. Unlike Entry::scopePublished this checks a single
     * column — media has no published_at concept.
     *
     * @see \App\Models\Entry::scopePublished
     */
    public function scopePublished(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('status_is_public', true);
    }

    public function scopeWithStatus(EloquentBuilder $query, string $handle): EloquentBuilder
    {
        return $query->where('status_handle', $handle);
    }

    public function transformations(): HasMany
    {
        return $this->hasMany(Media\Transformation::class);
    }

    /** Preserved — used by UploadMedia action. */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withTimestamps();
    }

    /**
     * Raw pivot rows for all field-driven references to this media item.
     *
     * field_id = 0 is the sentinel for direct attachments. Anything > 0 is a
     * field-driven reference. The column is NOT NULL so whereNotNull would
     * silently include direct attachments — use where('field_id', '>', 0).
     */
    public function fieldUsages(): Builder
    {
        return DB::table('mediables')
            ->where('media_id', $this->id)
            ->where('field_id', '>', 0)
            ->join('fields', 'fields.id', '=', 'mediables.field_id')
            ->select(
                'mediables.*',
                'fields.name as field_name',
                'fields.handle as field_handle'
            );
    }

    public function isReferencedByField(): bool
    {
        return DB::table('mediables')
            ->where('media_id', $this->id)
            ->where('field_id', '>', 0)
            ->exists();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function temporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutes)
        );
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}
