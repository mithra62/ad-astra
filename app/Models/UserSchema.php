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

    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
