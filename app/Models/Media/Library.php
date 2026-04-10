<?php
namespace App\Models\Media;

use App\Models\Category;
use App\Models\Category\Group;
use App\Models\Field\Group AS FieldGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Library extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'adapter',
        'adapter_settings',
        'server_path',
        'url',
        'allowed_types',
        'max_size',
        'sort_order',
    ];

    protected $table = 'media_libraries';

    protected $casts = [
        'sort_order' => 'integer',
        'adapter_settings' => 'array',
        'allowed_types' => 'array',
        'max_size' => 'integer',
    ];

    public function category_groups(): MorphToMany
    {
        return $this->morphToMany(Group::class, 'category_groupable')
            ->withTimestamps();
    }

    public function field_groups(): MorphToMany
    {
        return $this->morphToMany(FieldGroup::class, 'field_groupable')
            ->withTimestamps();
    }

    public function categories()
    {
        $ids = [];
        foreach($this->category_groups As $group) {
            foreach($group->categories AS $cat) {
                $ids[] = $cat->id;
            }
        }

        return Category::whereIn('id', $ids)->get();
    }
}
