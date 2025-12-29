<?php
namespace App\Models\Media;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Library extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'adaptor',
        'adaptor_settings',
        'server_path',
        'url',
        'allowed_types',
        'max_size',
        'sort_order',
    ];

    protected $table = 'media_libraries';

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order')->orderBy('name');
    }
}
