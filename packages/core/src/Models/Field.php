<?php

namespace AdAstra\Models;

use AdAstra\Field\AbstractField;
use AdAstra\Models\Field\Group;
use AdAstra\Models\Field\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_type_id',
        'name',
        'handle',
        'label',
        'instructions',
        'settings',
        'hidden',
    ];

    protected $casts = [
        'settings' => 'array',
        'hidden' => 'boolean',
    ];

    // fieldType is needed on virtually every Field access; always load it.
    protected $with = ['fieldType'];

    public function fieldType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'field_type_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(FieldValue::class);
    }

    public function groups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'fieldable')
            ->withTimestamps();
    }

    public function typeInstance(): AbstractField
    {
        return $this->fieldType->instance($this->settings ?? [], $this);
    }

    public function render(array $params = []): string
    {
        $params['field'] = $this;
        return $this->typeInstance()->render($params);
    }
}
