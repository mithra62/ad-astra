<?php

namespace Tests\Unit\Services;

use App\Models\EntryAuthor;
use App\Models\User;
use App\Services\EntryAuthorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryAuthorServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntryAuthorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntryAuthorService::class);
    }

    // -------------------------------------------------------------------------
    // getEligible()
    // -------------------------------------------------------------------------

    public function test_get_eligible_returns_a_collection(): void
    {
        $result = $this->service->getEligible();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_eligible_returns_only_active_records(): void
    {
        $active = EntryAuthor::factory()->create(['status' => 'active']);

        $result = $this->service->getEligible();

        $this->assertTrue($result->contains('id', $active->id));
    }

    public function test_get_eligible_excludes_pending_records(): void
    {
        $pending = EntryAuthor::factory()->pending()->create();

        $result = $this->service->getEligible();

        $this->assertFalse($result->contains('id', $pending->id));
    }

    public function test_get_eligible_excludes_disabled_records(): void
    {
        $disabled = EntryAuthor::factory()->disabled()->create();

        $result = $this->service->getEligible();

        $this->assertFalse($result->contains('id', $disabled->id));
    }

    public function test_get_eligible_eager_loads_user_relation(): void
    {
        EntryAuthor::factory()->create(['status' => 'active']);

        $result = $this->service->getEligible();

        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_get_eligible_returns_empty_collection_when_no_active_records_exist(): void
    {
        EntryAuthor::factory()->pending()->create();
        EntryAuthor::factory()->disabled()->create();

        $result = $this->service->getEligible();

        $this->assertEmpty($result);
    }

    public function test_get_eligible_orders_records_by_display_name(): void
    {
        // Use explicit display names to guarantee sort order regardless of user names
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        EntryAuthor::factory()->create(['user_id' => $user1->id, 'display_name' => 'Zara', 'status' => 'active']);
        EntryAuthor::factory()->create(['user_id' => $user2->id, 'display_name' => 'Alice', 'status' => 'active']);
        EntryAuthor::factory()->create(['user_id' => $user3->id, 'display_name' => 'Mike', 'status' => 'active']);

        $result = $this->service->getEligible();

        $this->assertEquals('Alice', $result->first()->display_name);
        $this->assertEquals('Zara', $result->last()->display_name);
    }

    // -------------------------------------------------------------------------
    // findByUser()
    // -------------------------------------------------------------------------

    public function test_find_by_user_returns_entry_author_when_record_exists(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id]);

        $result = $this->service->findByUser($user);

        $this->assertInstanceOf(EntryAuthor::class, $result);
        $this->assertEquals($ea->id, $result->id);
    }

    public function test_find_by_user_returns_null_when_no_record_exists(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->findByUser($user));
    }

    public function test_find_by_user_returns_record_regardless_of_status(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->disabled()->create(['user_id' => $user->id]);

        $result = $this->service->findByUser($user);

        $this->assertNotNull($result);
        $this->assertEquals($ea->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // promote()
    // -------------------------------------------------------------------------

    public function test_promote_returns_entry_author_instance(): void
    {
        $user = User::factory()->create();

        $result = $this->service->promote($user);

        $this->assertInstanceOf(EntryAuthor::class, $result);
    }

    public function test_promote_creates_new_record_when_none_exists(): void
    {
        $user = User::factory()->create();

        $this->service->promote($user);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id]);
    }

    public function test_promote_sets_status_to_active(): void
    {
        $user = User::factory()->create();

        $result = $this->service->promote($user);

        $this->assertEquals('active', $result->status);
        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_promote_reactivates_a_pending_record(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->pending()->create(['user_id' => $user->id]);

        $result = $this->service->promote($user);

        $this->assertEquals('active', $result->status);
        $this->assertDatabaseCount('entry_authors', 1);
    }

    public function test_promote_reactivates_a_disabled_record(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->disabled()->create(['user_id' => $user->id]);

        $result = $this->service->promote($user);

        $this->assertEquals('active', $result->status);
        $this->assertDatabaseCount('entry_authors', 1);
    }

    public function test_promote_does_not_create_a_duplicate_record(): void
    {
        $user = User::factory()->create();

        $this->service->promote($user);
        $this->service->promote($user);

        $this->assertDatabaseCount('entry_authors', 1);
    }

    public function test_promote_sets_display_name_when_provided(): void
    {
        $user = User::factory()->create();

        $result = $this->service->promote($user, 'Pen Name');

        $this->assertEquals('Pen Name', $result->getRawOriginal('display_name'));
        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'display_name' => 'Pen Name']);
    }

    public function test_promote_clears_display_name_when_empty_string_provided(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'display_name' => 'Old Name']);

        $result = $this->service->promote($user, '');

        $this->assertNull($result->getRawOriginal('display_name'));
        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'display_name' => null]);
    }

    public function test_promote_preserves_existing_display_name_when_null_passed(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->disabled()->create([
            'user_id'      => $user->id,
            'display_name' => 'Preserved Name',
        ]);

        $result = $this->service->promote($user, null);

        $this->assertEquals('Preserved Name', $result->getRawOriginal('display_name'));
    }

    public function test_promote_returns_refreshed_model_with_persisted_values(): void
    {
        $user = User::factory()->create();

        $result = $this->service->promote($user, 'Fresh Name');

        // The returned model should reflect the DB value, not just in-memory state
        $this->assertTrue($result->exists);
        $this->assertNotNull($result->id);
    }

    // -------------------------------------------------------------------------
    // demote()
    // -------------------------------------------------------------------------

    public function test_demote_sets_status_to_disabled(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->service->demote($user);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'disabled']);
    }

    public function test_demote_does_not_delete_the_record(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id]);

        $this->service->demote($user);

        $this->assertDatabaseHas('entry_authors', ['id' => $ea->id]);
    }

    public function test_demote_does_nothing_when_no_record_exists(): void
    {
        $user = User::factory()->create();

        // Should not throw
        $this->service->demote($user);

        $this->assertDatabaseEmpty('entry_authors');
    }

    public function test_demote_does_not_affect_records_for_other_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ea2 = EntryAuthor::factory()->create(['user_id' => $user2->id, 'status' => 'active']);

        $this->service->demote($user1);

        $this->assertDatabaseHas('entry_authors', ['id' => $ea2->id, 'status' => 'active']);
    }

    public function test_demote_touches_updated_at(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $originalUpdatedAt = $ea->updated_at;

        $this->travel(2)->seconds();

        $this->service->demote($user);

        $this->assertGreaterThan(
            $originalUpdatedAt,
            $ea->fresh()->updated_at,
            'demote() must update updated_at so cache/change-detection sees the demotion',
        );
    }

    public function test_demote_fires_eloquent_updated_event(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $fired = false;
        EntryAuthor::updated(function () use (&$fired) {
            $fired = true;
        });

        $this->service->demote($user);

        $this->assertTrue($fired, 'demote() must fire the Eloquent updated event so observers can react');
    }

    // -------------------------------------------------------------------------
    // sync()
    // -------------------------------------------------------------------------

    public function test_sync_promotes_user_when_eligible_is_true(): void
    {
        $user = User::factory()->create();

        $this->service->sync($user, true);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_sync_returns_entry_author_when_eligible_is_true(): void
    {
        $user = User::factory()->create();

        $result = $this->service->sync($user, true);

        $this->assertInstanceOf(EntryAuthor::class, $result);
        $this->assertEquals('active', $result->status);
    }

    public function test_sync_demotes_user_when_eligible_is_false(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->service->sync($user, false);

        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'disabled']);
    }

    public function test_sync_returns_null_when_eligible_is_false_and_no_record_exists(): void
    {
        $user = User::factory()->create();

        $result = $this->service->sync($user, false);

        $this->assertNull($result);
    }

    public function test_sync_returns_disabled_record_when_eligible_is_false_and_record_exists(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->service->sync($user, false);

        $this->assertInstanceOf(EntryAuthor::class, $result);
        $this->assertEquals('disabled', $result->status);
    }

    public function test_sync_passes_display_name_to_promote(): void
    {
        $user = User::factory()->create();

        $this->service->sync($user, true, 'Alias');

        $this->assertDatabaseHas('entry_authors', [
            'user_id'      => $user->id,
            'display_name' => 'Alias',
        ]);
    }

    public function test_sync_is_idempotent_for_promotion(): void
    {
        $user = User::factory()->create();

        $this->service->sync($user, true);
        $this->service->sync($user, true);

        $this->assertDatabaseCount('entry_authors', 1);
        $this->assertDatabaseHas('entry_authors', ['user_id' => $user->id, 'status' => 'active']);
    }
}
