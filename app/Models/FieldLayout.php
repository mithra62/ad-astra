<?php

namespace App\Models;

use App\Models\FieldLayout\Tab;
use App\Traits\Field\HasFieldGroups;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class FieldLayout extends Model
{
    use HasFactory;
    use HasFieldGroups;

    protected $fillable = [
        'name',
        'handle',
    ];

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

    public function availableFields(): Collection
    {
        $this->loadMissing('fieldGroups.fields');

        if ($this->fieldGroups->isEmpty()) {
            return Field::orderBy('name')->get();
        }

        return $this->fieldGroups->flatMap(fn ($g) => $g->fields)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
