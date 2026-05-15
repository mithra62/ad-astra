<?php

namespace App\Models;

use App\EntryTypes\AbstractEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use RuntimeException;

class EntryBehavior extends Model
{
    use HasFactory;

    protected $table = 'entry_behaviors';

    protected $fillable = ['name', 'handle', 'class', 'description'];

    public function entryTypes(): HasMany
    {
        return $this->hasMany(EntryType::class);
    }

    public function instance(EntryType $record): AbstractEntryType
    {
        $class = Relation::getMorphedModel($this->class);

        if ($class === null) {
            throw new RuntimeException("EntryBehavior morph key [{$this->class}] is not registered in the morphMap.");
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
