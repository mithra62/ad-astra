<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EntryTree extends Model
{
    protected $table = 'entry_trees';

    protected $fillable = [
        'entry_id',
        'parent_id',
        'handle',
        'uri',
        'depth',
        'sort_order',
        'template',
        'is_home',
    ];

    protected $casts = [
        'is_home' => 'boolean',
    ];

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

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByUri($query, string $uri)
    {
        return $query->where('uri', self::normalizeUri($uri));
    }

    public function getUrlAttribute(): string
    {
        return $this->uri === '/' ? '/' : '/' . $this->uri;
    }

    public static function normalizeHandle(string $handle): string
    {
        return Str::slug($handle);
    }

    public static function normalizeUri(?string $uri): string
    {
        $uri = trim($uri ?: '/', '/');

        return $uri === '' ? '/' : $uri;
    }
}
