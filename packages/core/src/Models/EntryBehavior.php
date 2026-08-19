<?php

namespace AdAstra\Models;

use AdAstra\EntryTypes\AbstractEntryType;
use AdAstra\EntryTypes\EntryBehaviorRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class EntryBehavior extends Model
{
    use HasFactory;

    protected $table = 'entry_behaviors';

    protected $fillable = ['name', 'handle', 'class', 'description'];

    /**
     * @return HasMany<EntryType, $this>
     */
    public function entryTypes(): HasMany
    {
        return $this->hasMany(EntryType::class);
    }

    public function instance(EntryType $record): AbstractEntryType
    {
        $class = EntryBehaviorRegistry::resolve($this->class);

        if ($class === null) {
            throw new RuntimeException("EntryBehavior morph key [{$this->class}] is not registered in the behavior registry.");
        }

        if (!class_exists($class)) {
            throw new RuntimeException("EntryBehavior class [{$class}] does not exist.");
        }

        if (!is_subclass_of($class, AbstractEntryType::class)) {
            throw new RuntimeException("EntryBehavior class [{$class}] must extend AbstractEntryType.");
        }

        return new $class($record);
    }
}
