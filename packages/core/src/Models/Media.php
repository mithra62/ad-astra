<?php

namespace AdAstra\Models;

use AdAstra\Traits\Category\HasCategories;
use AdAstra\Traits\Field\Fieldable;
use AdAstra\Traits\HasStatus;
use AdAstra\Traits\HasTransformations;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;
    use Fieldable;
    use HasCategories;
    use HasStatus;
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

    /**
     * Intended field schema for a media item: the field layout on its library.
     */
    public function fieldSchema(): Collection
    {
        $this->loadMissing('library.fieldLayout.tabs.elements.field.fieldType');

        return $this->library?->fieldLayout?->fields() ?? collect();
    }

    /**
     * Public media only. Backward-compatible alias for HasStatus::scopePublic —
     * unlike Entry::scopePublished, media has no published_at concept, so
     * "published" here is identity with "public".
     *
     * @see HasStatus::scopePublic
     * @see Entry::scopePublished
     */
    public function scopePublished(EloquentBuilder $query): EloquentBuilder
    {
        return $query->public();
    }

    public function transformations(): HasMany
    {
        return $this->hasMany(Media\Transformation::class);
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
