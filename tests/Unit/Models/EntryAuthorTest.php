<?php

namespace Tests\Unit\Models;

use App\Models\Entry;
use App\Models\EntryAuthor;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryAuthorTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Fillable
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new EntryAuthor;

        $this->assertEquals(['user_id', 'display_name', 'status'], $model->getFillable());
    }

    // -------------------------------------------------------------------------
    // scopeActive
    // -------------------------------------------------------------------------

    public function test_scope_active_returns_only_active_records(): void
    {
        $active = EntryAuthor::factory()->create(['status' => 'active']);

        $results = EntryAuthor::active()->get();

        $this->assertTrue($results->contains($active));
    }

    public function test_scope_active_excludes_pending_records(): void
    {
        $pending = EntryAuthor::factory()->pending()->create();

        $results = EntryAuthor::active()->get();

        $this->assertFalse($results->contains($pending));
    }

    public function test_scope_active_excludes_disabled_records(): void
    {
        $disabled = EntryAuthor::factory()->disabled()->create();

        $results = EntryAuthor::active()->get();

        $this->assertFalse($results->contains($disabled));
    }

    // -------------------------------------------------------------------------
    // scopePending
    // -------------------------------------------------------------------------

    public function test_scope_pending_returns_only_pending_records(): void
    {
        $pending = EntryAuthor::factory()->pending()->create();
        EntryAuthor::factory()->create(['status' => 'active']);

        $results = EntryAuthor::pending()->get();

        $this->assertTrue($results->contains($pending));
        $this->assertCount(1, $results);
    }

    public function test_scope_pending_excludes_active_records(): void
    {
        $active = EntryAuthor::factory()->create(['status' => 'active']);

        $results = EntryAuthor::pending()->get();

        $this->assertFalse($results->contains($active));
    }

    // -------------------------------------------------------------------------
    // scopeDisabled
    // -------------------------------------------------------------------------

    public function test_scope_disabled_returns_only_disabled_records(): void
    {
        $disabled = EntryAuthor::factory()->disabled()->create();
        EntryAuthor::factory()->create(['status' => 'active']);

        $results = EntryAuthor::disabled()->get();

        $this->assertTrue($results->contains($disabled));
        $this->assertCount(1, $results);
    }

    public function test_scope_disabled_excludes_active_records(): void
    {
        $active = EntryAuthor::factory()->create(['status' => 'active']);

        $results = EntryAuthor::disabled()->get();

        $this->assertFalse($results->contains($active));
    }

    // -------------------------------------------------------------------------
    // getDisplayNameAttribute
    // -------------------------------------------------------------------------

    public function test_display_name_returns_explicit_value_when_set(): void
    {
        $ea = EntryAuthor::factory()->create(['display_name' => 'Jane Doe']);

        $this->assertEquals('Jane Doe', $ea->display_name);
    }

    public function test_display_name_falls_back_to_user_name_when_null(): void
    {
        $user = User::factory()->create(['name' => 'John Smith']);
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id, 'display_name' => null]);

        // Eager-load the user so the accessor can reach it
        $ea->load('user');

        $this->assertEquals('John Smith', $ea->display_name);
    }

    public function test_display_name_returns_empty_string_when_no_display_name_and_no_user_loaded(): void
    {
        // Build without persisting so there is no user relation
        $ea = new EntryAuthor(['display_name' => null]);

        $this->assertEquals('', $ea->display_name);
    }

    public function test_display_name_is_preferred_over_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Real Name']);
        $ea = EntryAuthor::factory()->create([
            'user_id'      => $user->id,
            'display_name' => 'Pen Name',
        ]);

        $ea->load('user');

        $this->assertEquals('Pen Name', $ea->display_name);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_user_relationship_is_belongs_to(): void
    {
        $ea = EntryAuthor::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $ea->user());
    }

    public function test_user_relationship_returns_the_related_user(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $ea->user->id);
    }

    public function test_entries_relationship_is_belongs_to_many(): void
    {
        $ea = EntryAuthor::factory()->create();

        $this->assertInstanceOf(BelongsToMany::class, $ea->entries());
    }

    public function test_entries_relationship_returns_linked_entries(): void
    {
        $ea = EntryAuthor::factory()->create();
        $entry = Entry::factory()->create();

        $ea->entries()->attach($entry->id, ['sort_order' => 1]);

        $this->assertCount(1, $ea->entries()->get());
        $this->assertEquals($entry->id, $ea->entries()->first()->id);
    }
}
