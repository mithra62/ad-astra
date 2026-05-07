<?php

namespace App\Services;

use App\Models\EntryAuthor;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EntryAuthorService
{
    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    /**
     * Return all active EntryAuthor records with their user relation eager-loaded.
     * This is the only source the author picker should ever read from.
     */
    public function getEligible(): Collection
    {
        return EntryAuthor::active()
            ->with('user')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * Look up the eligibility record for a given user. Returns null if none exists.
     */
    public function findByUser(User $user): ?EntryAuthor
    {
        return EntryAuthor::where('user_id', $user->id)->first();
    }

    // -------------------------------------------------------------------------
    // Promote / Demote
    // -------------------------------------------------------------------------

    /**
     * Promote a user to active author status, creating the registry record if needed.
     * If a record already exists it is re-activated and the display name updated.
     */
    public function promote(User $user, ?string $displayName = null): EntryAuthor
    {
        $author = EntryAuthor::firstOrNew(['user_id' => $user->id]);

        $author->status = 'active';

        if ($displayName !== null) {
            $author->display_name = $displayName ?: null;
        }

        $author->save();

        return $author->refresh();
    }

    /**
     * Demote a user from author status. Sets the record to disabled.
     * Existing entry assignments are preserved — the author simply stops
     * appearing in pickers and fails future eligibility checks.
     */
    public function demote(User $user): void
    {
        EntryAuthor::where('user_id', $user->id)
            ->update(['status' => 'disabled']);
    }

    /**
     * Idempotent upsert driven by the user edit form.
     *
     * When $eligible is true, promotes (or re-activates) the user.
     * When $eligible is false, demotes the user if a record exists.
     */
    public function sync(User $user, bool $eligible, ?string $displayName = null): ?EntryAuthor
    {
        if ($eligible) {
            return $this->promote($user, $displayName);
        }

        $this->demote($user);

        return $this->findByUser($user);
    }
}
