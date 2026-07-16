<?php

namespace AdAstra\Facades;

use AdAstra\Models\EntryAuthor;
use AdAstra\Models\User;
use AdAstra\Services\EntryAuthorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection getEligible()
 * @method static EntryAuthor|null findByUser(User $user)
 * @method static EntryAuthor promote(User $user, ?string $displayName = null)
 * @method static void demote(User $user)
 * @method static EntryAuthor|null sync(User $user, bool $eligible, ?string $displayName = null)
 *
 * @see EntryAuthorService
 */
class EntryAuthors extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EntryAuthorService::class;
    }
}
