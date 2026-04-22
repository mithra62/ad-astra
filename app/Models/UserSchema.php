<?php

namespace App\Models;

use App\Traits\HasFieldGroups;
use App\Traits\HasFieldLayout;
use Illuminate\Database\Eloquent\Model;

class UserSchema extends Model
{
    use HasFieldLayout, HasFieldGroups;

    protected $table = 'user_schema';

    protected $fillable = ['field_layout_id'];

    private static ?self $resolved = null;

    public static function instance(): static
    {
        return static::resolved();
    }

    /**
     * Return the singleton with its full layout tree eager-loaded.
     * Result is cached for the lifetime of the request.
     */
    public static function resolved(): static
    {
        if (static::$resolved === null) {
            static::$resolved = static::with(
                'fieldLayout.tabs.elements.field'
            )->firstOrCreate(['id' => 1]);
        }

        return static::$resolved;
    }

    /**
     * Clear the runtime cache (useful in tests between cases).
     */
    public static function flushResolved(): void
    {
        static::$resolved = null;
    }
}
