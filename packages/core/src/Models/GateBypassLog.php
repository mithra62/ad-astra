<?php

namespace AdAstra\Models;

use AdAstra\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GateBypassLog extends Model
{
    use HasFactory;
    use Prunable;

    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'gate_bypass_logs';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'ability',
        'subject_type',
        'subject_id',
        'method',
        'url',
        'route_name',
        'ip',
        'occurrences',
        'context',
        'created_at',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'context' => 'array',
        'occurrences' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * Retention is settings-driven (security.gate_bypass_log_retention_days).
     * Read via system() — never get() — so the audited user's own overrides
     * can't influence an audit control. A retention of 0 means keep forever.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function prunable()
    {
        $days = (int) (app(Settings::class)->system('security')['gate_bypass_log_retention_days'] ?? 365);

        return $days > 0
            ? static::where('created_at', '<', now()->subDays($days))
            : static::whereRaw('1 = 0');
    }
}
