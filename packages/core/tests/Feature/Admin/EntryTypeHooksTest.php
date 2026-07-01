<?php

namespace Tests\Feature\Admin;

use AdAstra\EntryTypes\BlogPostEntryType;
use AdAstra\EntryTypes\EventEntryType;
use AdAstra\EntryTypes\JobListingEntryType;
use AdAstra\EntryTypes\ProductEntryType;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\FieldValue;
use AdAstra\Models\Role;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integration tests confirming the full HTTP → EntryType hook chain.
 *
 * Each test drives a real HTTP request through the controller → action →
 * service → repository stack and asserts the side-effects that only the
 * EntryType lifecycle layer (beforeCreate, beforeUpdate, validate) can produce.
 */
class EntryTypeHooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    /**
     * Build an EntryGroup + EntryType pair backed by the given class.
     * The status group always contains 'draft' (default) and 'published'.
     * Pass additional ['handle' => 'Name'] pairs in $extraStatuses.
     *
     * @return array{0: EntryGroup, 1: EntryType}
     */
    private function makeGroupAndType(string $class, array $extraStatuses = []): array
    {
        $statusGroup = StatusGroup::factory()->create();

        Status::factory()->default()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'name' => 'Draft',
        ]);

        Status::factory()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'published',
            'name' => 'Published',
            'is_default' => false,
            'is_public' => true,
        ]);

        foreach ($extraStatuses as $handle => $name) {
            Status::factory()->create([
                'status_group_id' => $statusGroup->id,
                'handle' => $handle,
                'name' => $name,
                'is_default' => false,
            ]);
        }

        $group = EntryGroup::factory()->create(['status_group_id' => $statusGroup->id]);

        $morphKey = 'behavior.test-' . uniqid();
        Relation::morphMap([$morphKey => $class]);

        $behavior = EntryBehavior::create([
            'name' => 'Test',
            'handle' => Str::slug('test-' . uniqid()),
            'class' => $morphKey,
        ]);

        $type = EntryType::factory()->create([
            'entry_group_id' => $group->id,
            'entry_behavior_id' => $behavior->id,
        ]);

        return [$group, $type];
    }

    /**
     * Create a FieldLayout with a single tab, attach one or more Fields with
     * the given handle(s), and point the EntryType to that layout.
     *
     * Pass a string for a single field (returns that Field model).
     * Pass an array of handles to attach multiple fields in one layout
     * (returns a keyed array of [handle => Field]).
     *
     * This is the minimum wiring required for the repository to persist a
     * field value that a lifecycle hook injects into the data array, AND for
     * StoreEntryRequest::schemaFieldRules() to include each field's handle in
     * the validated() output (necessary for hook-injected values to survive
     * the request validation step).
     */
    private function attachFieldToType(EntryType $type, string|array $handles): Field|array
    {
        $handles = (array)$handles;
        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->create(['field_layout_id' => $layout->id]);
        // Create one shared FieldType so all fields in this layout reuse the same
        // type row — TypeFactory always emits Text / AdAstra\Field\Types\Text, and
        // field_types.object has a unique constraint.
        $fieldType = FieldType::factory()->create();

        $fields = [];
        foreach ($handles as $handle) {
            $field = Field::factory()->create([
                'handle' => $handle,
                'field_type_id' => $fieldType->id,
            ]);
            TabElement::factory()->create([
                'field_layout_tab_id' => $tab->id,
                'field_id' => $field->id,
            ]);
            $fields[$handle] = $field;
        }

        $type->update(['field_layout_id' => $layout->id]);

        return count($fields) === 1 ? array_values($fields)[0] : $fields;
    }

    // =========================================================================
    // 1. Blog post create — beforeCreate() computes reading_time from body
    // =========================================================================

    /**
     * BlogPostEntryType::beforeCreate() injects `fields.reading_time` into the
     * data array. The repository must persist that value against the entry.
     * 400 words at 200 wpm = exactly 2 minutes.
     */
    public function test_create_blog_post_persists_reading_time_derived_from_body(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(BlogPostEntryType::class);
        // Attach BOTH 'body' and 'reading_time' so schemaFieldRules() adds an
        // explicit validation rule for fields.body.  Without that rule, Laravel's
        // validated() strips fields.body from the data before it reaches
        // computeReadingTime(), and the hook never fires.
        $this->attachFieldToType($type, ['body', 'reading_time']);

        $body = implode(' ', array_fill(0, 400, 'word')); // 400 words → ceil(400/200) = 2

        $response = $this->actingAs($user)
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Test Blog Post',
                'handle' => 'test-blog-post',
                'status' => 'draft',
                'fields' => ['body' => $body],
            ]);

        $response->assertRedirect(route('entries.groups.show', $group->id));

        $entry = Entry::query()->where('title', 'Test Blog Post')->firstOrFail();
        $readingTimeField = Field::where('handle', 'reading_time')->firstOrFail();

        // Assert the field value row was written to the database by the hook.
        $fv = FieldValue::where('field_id', $readingTimeField->id)
            ->where('fieldable_id', $entry->id)
            ->first();

        $this->assertNotNull($fv, 'No field_values row found for reading_time — hook did not persist the value.');
        $this->assertSame(2, (int)$fv->value_text);
    }

    /**
     * When a blog post is created with status='published' and no explicit
     * published_at, beforeCreate() should stamp published_at automatically.
     */
    public function test_create_blog_post_stamps_published_at_when_status_is_published(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(BlogPostEntryType::class);

        $this->actingAs($user)
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Published Post',
                'handle' => 'published-post',
                'status' => 'published',
            ]);

        $entry = Entry::query()->where('title', 'Published Post')->firstOrFail();

        $this->assertNotNull($entry->published_at);
    }

    // =========================================================================
    // 2. Product create — validate() blocks publish without a SKU
    // =========================================================================

    /**
     * ProductEntryType::validate() must return an error when a product is
     * created with status='published' but no SKU value. The controller should
     * redirect back with a session error on the 'sku' key and no entry row
     * should be written to the database.
     */
    public function test_create_product_without_sku_is_rejected_when_publishing(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(ProductEntryType::class);

        $response = $this->actingAs($user)
            ->from(route('entries.create', ['group_id' => $group->id]))
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Headphones Without SKU',
                'handle' => 'headphones-without-sku',
                'status' => 'published',
            ]);

        $response->assertRedirect(route('entries.create', ['group_id' => $group->id]));
        $response->assertSessionHasErrors('sku');
        $this->assertDatabaseMissing('entries', ['title' => 'Headphones Without SKU']);
    }

    /**
     * A product published with a valid SKU must be accepted and stored.
     */
    public function test_create_product_with_sku_succeeds_when_publishing(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(ProductEntryType::class);
        $this->attachFieldToType($type, 'sku');

        $response = $this->actingAs($user)
            ->post(route('entries.store', ['group_id' => $group->id]), [
                'type_handle' => $type->handle,
                'title' => 'Headphones With SKU',
                'handle' => 'headphones-with-sku',
                'status' => 'published',
                'fields' => ['sku' => 'ELEC-001'],
            ]);

        $response->assertRedirect(route('entries.groups.show', $group->id));
        $this->assertDatabaseHas('entries', ['title' => 'Headphones With SKU']);
    }

    // =========================================================================
    // 3. Job listing update — beforeUpdate() auto-expires on past closing_date
    // =========================================================================

    /**
     * JobListingEntryType::beforeUpdate() overrides the entry status to
     * 'expired' when the closing_date field is in the past. The status written
     * to the database must be 'expired' even though 'published' was sent in
     * the request.
     */
    public function test_update_job_listing_with_past_closing_date_transitions_to_expired(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(JobListingEntryType::class, ['expired' => 'Expired']);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'status_handle' => 'published',
        ]);

        $response = $this->actingAs($user)
            ->put(route('entries.update', $entry), [
                'title' => $entry->title,
                'handle' => $entry->handle,
                'status' => 'published',
                'fields' => [
                    'application_url' => 'https://jobs.example.com/apply',
                    'closing_date' => Carbon::yesterday()->toDateString(),
                ],
            ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $this->assertSame('expired', $entry->fresh()->status_handle);
    }

    /**
     * A job listing whose closing_date is in the future must not be auto-expired.
     */
    public function test_update_job_listing_with_future_closing_date_remains_published(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(JobListingEntryType::class, ['expired' => 'Expired']);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'status_handle' => 'published',
        ]);

        $response = $this->actingAs($user)
            ->put(route('entries.update', $entry), [
                'title' => $entry->title,
                'handle' => $entry->handle,
                'status' => 'published',
                'fields' => [
                    'application_url' => 'https://jobs.example.com/apply',
                    'closing_date' => Carbon::today()->addWeek()->toDateString(),
                ],
            ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $this->assertSame('published', $entry->fresh()->status_handle);
    }

    // =========================================================================
    // 4. Event update — validate() blocks end_date before start_date
    // =========================================================================

    /**
     * EventEntryType::validate() must return an error when the payload
     * contains an end_date that is earlier than start_date. The controller
     * should redirect back with a session error on the 'end_date' key and no
     * attribute changes should be committed.
     */
    public function test_update_event_with_end_date_before_start_date_returns_validation_error(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(EventEntryType::class);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => 'Annual Conference',
        ]);

        $response = $this->actingAs($user)
            ->from(route('entries.edit', $entry))
            ->put(route('entries.update', $entry), [
                'title' => 'Annual Conference',
                'handle' => $entry->handle,
                'status' => 'draft',
                'fields' => [
                    'start_date' => '2026-07-10',
                    'end_date' => '2026-07-01', // before start_date
                ],
            ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $response->assertSessionHasErrors('end_date');
    }

    /**
     * An event update with end_date on the same day as start_date is valid.
     */
    public function test_update_event_with_end_date_equal_to_start_date_succeeds(): void
    {
        $user = $this->makeSuperAdmin();
        [$group, $type] = $this->makeGroupAndType(EventEntryType::class);

        $entry = Entry::factory()->create([
            'entry_group_id' => $group->id,
            'entry_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => 'One Day Event',
        ]);

        $response = $this->actingAs($user)
            ->put(route('entries.update', $entry), [
                'title' => 'One Day Event',
                'handle' => $entry->handle,
                'status' => 'draft',
                'fields' => [
                    'start_date' => '2026-07-10',
                    'end_date' => '2026-07-10', // same day — valid
                ],
            ]);

        $response->assertRedirect(route('entries.edit', $entry));
        $response->assertSessionDoesntHaveErrors('end_date');
    }
}
