<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingDomain extends Model
{
    use HasFactory;

    protected $table = 'setting_domains';

    protected $fillable = [
        'name',
        'handle',
        'description',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Return only the user-overridable field definitions for this domain.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overridableConfigFields(): array
    {
        return array_values(
            array_filter(
                $this->configFields(),
                fn(array $f) => ($f['user_overridable'] ?? false) && !($f['hidden'] ?? false)
            )
        );
    }

    /**
     * Return the raw field definitions for this domain from config/settings.php.
     *
     * @return array<int, array<string, mixed>>
     */
    public function configFields(): array
    {
        return config("settings.{$this->handle}.fields", []);
    }
}
