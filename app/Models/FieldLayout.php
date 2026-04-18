<?php

namespace App\Models;

use App\Models\FieldLayout\Tab;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldLayout extends Model
{
    protected $fillable = ['name'];

    public function tabs(): HasMany
    {
        return $this->hasMany(Tab::class)->orderBy('sort_order');
    }

    public function fields(): Collection
    {
        return $this->tabs->flatMap(
            fn($tab) => $tab->elements->map(fn($el) => $el->field)
        );
    }
}
