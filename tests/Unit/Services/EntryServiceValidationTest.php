<?php

namespace Tests\Unit\Services;

use App\EntryTypes\JobListingEntryType;
use App\EntryTypes\ProductEntryType;
use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Status;
use App\Models\StatusGroup;
use App\Models\User;
use App\Services\EntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verifies that EntryService::create() and EntryService::update() wire
 * the EntryType validate() contract into the call stack and surface errors
 * as ValidationException before any database writes occur.
 */
class EntryServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    private EntryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntryService::class);
        $this->actingAs(User::factory()->create());
    }

    // -------------------------------------------------------------------------
    // create() — validation fires before repository
    // -------------------------------------------------------------------------

    public function test_create_throws_validation_exception_when_type_validate_returns_errors(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeJobListingSetup();

        $this->expectException(ValidationException::class);

        // Publishing a job listing without application_url or application_email
        // triggers JobListingEntryType::validate() to return an error.
        $this->service->create($type->handle, [
            'title'  => 'Software Engineer',
            'handle' => 'software-engineer',
            'status' => 'published',
            'fields' => [],
        ]);
    }

    public function test_create_validation_exception_contains_correct_field_key(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeJobListingSetup();

        try {
            $this->service->create($type->handle, [
                'title'  => 'Software Engineer',
                'handle' => 'software-engineer',
                'status' => 'published',
                'fields' => [],
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('application_url', $e->errors());
        }
    }

    public function test_create_does_not_throw_when_validate_passes(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeJobListingSetup();

        // Draft status bypasses the publish-gate in JobListingEntryType::validate().
        $entry = $this->service->create($type->handle, [
            'title'  => 'Software Engineer',
            'handle' => 'software-engineer',
            'status' => 'draft',
            'fields' => [],
        ]);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertTrue($entry->exists);
    }

    public function test_create_does_not_persist_entry_when_validation_fails(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeJobListingSetup();

        try {
            $this->service->create($type->handle, [
                'title'  => 'Software Engineer',
                'handle' => 'software-engineer',
                'status' => 'published',
                'fields' => [],
            ]);
        } catch (ValidationException) {
            // swallow
        }

        $this->assertDatabaseCount('entries', 0);
    }

    // -------------------------------------------------------------------------
    // update() — validation fires before repository
    // -------------------------------------------------------------------------

    public function test_update_throws_validation_exception_when_type_validate_returns_errors(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeProductSetup();

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
        ]);

        $this->expectException(ValidationException::class);

        // Publishing a product without a SKU triggers ProductEntryType::validate().
        $this->service->update($entry, [
            'status' => 'published',
            'fields' => [],
        ]);
    }

    public function test_update_validation_exception_contains_correct_field_key(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeProductSetup();

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
        ]);

        try {
            $this->service->update($entry, [
                'status' => 'published',
                'fields' => [],
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sku', $e->errors());
        }
    }

    public function test_update_does_not_throw_when_validate_passes(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeProductSetup();

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
            'title'          => 'Original',
        ]);

        // Draft status bypasses the publish-gate in ProductEntryType::validate().
        $updated = $this->service->update($entry, [
            'title'  => 'Updated',
            'status' => 'draft',
            'fields' => [],
        ]);

        $this->assertSame('Updated', $updated->title);
    }

    public function test_update_does_not_persist_changes_when_validation_fails(): void
    {
        ['group' => $group, 'type' => $type] = $this->makeProductSetup();

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id'  => $type->id,
            'title'          => 'Original Title',
        ]);

        try {
            $this->service->update($entry, [
                'title'  => 'Changed Title',
                'status' => 'published',
                'fields' => [],
            ]);
        } catch (ValidationException) {
            // swallow
        }

        $this->assertDatabaseHas('entries', [
            'id'    => $entry->id,
            'title' => 'Original Title',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the minimum DB scaffolding required to resolve a JobListingEntryType
     * through the EntryTypeRegistry (requires a real entry_types row).
     *
     * @return array{group: EntryGroup, type: EntryType}
     */
    private function makeJobListingSetup(): array
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'draft',     'is_default' => true,  'is_public' => false]);
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'published', 'is_default' => false, 'is_public' => true]);

        $group = EntryGroup::factory()->create(['status_group_id' => $statusGroup->id]);

        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle'         => 'job_listing_' . uniqid(),
            'class'          => JobListingEntryType::class,
        ]);

        return compact('group', 'type');
    }

    /**
     * Build the minimum DB scaffolding for a ProductEntryType.
     *
     * @return array{group: EntryGroup, type: EntryType}
     */
    private function makeProductSetup(): array
    {
        $statusGroup = StatusGroup::factory()->create();
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'draft',     'is_default' => true,  'is_public' => false]);
        Status::factory()->create(['status_group_id' => $statusGroup->id, 'handle' => 'published', 'is_default' => false, 'is_public' => true]);

        $group = EntryGroup::factory()->create(['status_group_id' => $statusGroup->id]);

        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'handle'         => 'product_' . uniqid(),
            'class'          => ProductEntryType::class,
        ]);

        return compact('group', 'type');
    }
}
