<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EntryTree extends Model
{
    use HasFactory;

    protected $table = 'entry_trees';

    protected $fillable = [
        'entry_id',
        'parent_id',
        'handle',
        'uri',
        'depth',
        'sort_order',
        'redirect_url',
        'redirect_status',
        'template',
        'is_home',
    ];

    protected $casts = [
        'is_home' => 'boolean',
    ];

    public static function validatedHandle(string $handle): string
    {
        $normalized = self::normalizeHandle($handle);

        if ($normalized === '') {
            throw new InvalidArgumentException('Entry Tree handles must contain at least one URL-safe character.');
        }

        return $normalized;
    }

    public static function normalizeHandle(string $handle): string
    {
        return Str::slug($handle);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByUri(Builder $query, string $uri): Builder
    {
        return $query->where('uri', self::normalizeUri($uri));
    }

    public static function normalizeUri(?string $uri): string
    {
        $uri = trim($uri ?: '/', '/');

        return $uri === '' ? '/' : $uri;
    }

    public function getUrlAttribute(): string
    {
        return $this->uri === '/' ? '/' : '/'.$this->uri;
    }
}
