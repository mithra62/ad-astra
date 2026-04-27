<?php

namespace App\Models;

use App\Models\FieldLayout\Tab;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class FieldLayout extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function tabs(): HasMany
    {
        return $this->hasMany(Tab::class)->orderBy('sort_order');
    }

    public function entryGroups(): HasMany
    {
        return $this->hasMany(EntryGroup::class);
    }

    public function entryTypes(): HasMany
    {
        return $this->hasMany(EntryType::class);
    }

    public function fields(): Collection
    {
        $this->loadMissing('tabs.elements.field');

        return $this->tabs->flatMap(
            fn ($tab) => $tab->elements->map(fn ($el) => $el->field)
        );
    }
}
