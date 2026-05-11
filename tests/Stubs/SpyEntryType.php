<?php

namespace Tests\Stubs;

use App\EntryTypes\AbstractEntryType;
use App\Models\Entry;

/**
 * Concrete EntryType stub that records every lifecycle hook invocation via
 * static counters (required by the AbstractEntryType singleton contract) and
 * appends a detectable suffix to $data['title'] so callers can assert the
 * mutation propagated all the way to the persisted entry.
 */
class SpyEntryType extends AbstractEntryType
{
    public static int $beforeCreateCalls = 0;
    public static int $afterCreateCalls = 0;
    public static int $beforeUpdateCalls = 0;
    public static int $afterUpdateCalls = 0;

    public static function reset(): void
    {
        static::$beforeCreateCalls = 0;
        static::$afterCreateCalls = 0;
        static::$beforeUpdateCalls = 0;
        static::$afterUpdateCalls = 0;
    }

    public function beforeCreate(array $data): array
    {
        static::$beforeCreateCalls++;
        $data['title'] = ($data['title'] ?? '') . ' [bc]';
        return $data;
    }

    public function afterCreate(Entry $entry, array $data): void
    {
        static::$afterCreateCalls++;
    }

    public function beforeUpdate(Entry $entry, array $data): array
    {
        static::$beforeUpdateCalls++;
        if (isset($data['title'])) {
            $data['title'] .= ' [bu]';
        }
        return $data;
    }

    public function afterUpdate(Entry $entry, array $data): void
    {
        static::$afterUpdateCalls++;
    }
}
