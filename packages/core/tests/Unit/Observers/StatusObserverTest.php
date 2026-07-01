<?php

namespace Tests\Unit\Observers;

use AdAstra\Models\Entry;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusObserverTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{0: Status, 1: Status, 2: StatusGroup}
     */
    private function makeTwoStatuses(): array
    {
        $group = StatusGroup::factory()->create();
        $draft = Status::factory()->create([
            'status_group_id' => $group->id,
            'handle' => 'draft',
            'is_default' => true,
            'is_public' => false,
        ]);
        $published = Status::factory()->create([
            'status_group_id' => $group->id,
            'handle' => 'published',
            'is_default' => false,
            'is_public' => true,
        ]);

        return [$draft, $published, $group];
    }

    private function makeEntry(Status $status): Entry
    {
        return Entry::factory()->create([
            'status_id' => $status->id,
            'status_handle' => $status->handle,
            'status_is_public' => $status->is_public,
        ]);
    }

    private function makeMedia(Status $status, array $overrides = []): Media
    {
        $library = Library::create([
            'name' => 'Photos '.fake()->unique()->numberBetween(1, 999999),
            'handle' => 'photos-'.fake()->unique()->regexify('[a-z]{6}'),
            'adapter' => 'local',
        ]);

        return Media::factory()->create(array_merge([
            'library_id' => $library->id,
            'status_id' => $status->id,
            'status_handle' => $status->handle,
            'status_is_public' => $status->is_public,
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // is_public sync
    // -------------------------------------------------------------------------

    public function test_updating_is_public_syncs_entries(): void
    {
        [$draft] = $this->makeTwoStatuses();
        $entry = $this->makeEntry($draft);

        $draft->is_public = true;
        $draft->save();

        $this->assertTrue($entry->fresh()->status_is_public);
    }

    public function test_updating_is_public_syncs_media(): void
    {
        [$draft] = $this->makeTwoStatuses();
        $media = $this->makeMedia($draft);

        $draft->is_public = true;
        $draft->save();

        $this->assertTrue($media->fresh()->status_is_public);
    }

    // -------------------------------------------------------------------------
    // handle sync
    // -------------------------------------------------------------------------

    public function test_updating_handle_syncs_status_handle_on_both_consumers(): void
    {
        [$draft] = $this->makeTwoStatuses();
        $entry = $this->makeEntry($draft);
        $media = $this->makeMedia($draft);

        $draft->handle = 'pending-review';
        $draft->save();

        $this->assertSame('pending-review', $entry->fresh()->status_handle);
        $this->assertSame('pending-review', $media->fresh()->status_handle);
    }

    // -------------------------------------------------------------------------
    // Combined sync
    // -------------------------------------------------------------------------

    public function test_updating_both_fields_syncs_both_columns_in_one_update(): void
    {
        [$draft] = $this->makeTwoStatuses();
        $entry = $this->makeEntry($draft);
        $media = $this->makeMedia($draft);

        $draft->handle = 'live';
        $draft->is_public = true;
        $draft->save();

        $freshEntry = $entry->fresh();
        $freshMedia = $media->fresh();

        $this->assertSame('live', $freshEntry->status_handle);
        $this->assertTrue($freshEntry->status_is_public);
        $this->assertSame('live', $freshMedia->status_handle);
        $this->assertTrue($freshMedia->status_is_public);
    }

    // -------------------------------------------------------------------------
    // Dirty-check no-op
    // -------------------------------------------------------------------------

    public function test_no_op_when_neither_is_public_nor_handle_dirty(): void
    {
        [$draft] = $this->makeTwoStatuses();
        $entry = $this->makeEntry($draft);

        DB::enableQueryLog();
        $draft->color = '#ff0000';
        $draft->save();
        $writeCount = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_starts_with(strtolower(trim($q['query'])), 'update'))
            ->count();
        DB::disableQueryLog();

        // Exactly one write — the Status row itself. No consumer updates.
        $this->assertSame(1, $writeCount);
        $this->assertFalse($entry->fresh()->status_is_public);
    }

    // -------------------------------------------------------------------------
    // Soft-delete handling
    // -------------------------------------------------------------------------

    public function test_includes_soft_deleted_media_via_with_trashed(): void
    {
        [$draft] = $this->makeTwoStatuses();
        $media = $this->makeMedia($draft);
        $media->delete(); // soft-delete

        $draft->is_public = true;
        $draft->save();

        $trashed = Media::withTrashed()->find($media->id);
        $this->assertTrue($trashed->status_is_public);
    }

    // -------------------------------------------------------------------------
    // Isolation
    // -------------------------------------------------------------------------

    public function test_consumers_pointing_at_other_statuses_are_untouched(): void
    {
        [$draft, $published] = $this->makeTwoStatuses();
        $entryA = $this->makeEntry($draft);
        $entryB = $this->makeEntry($published);

        $draft->is_public = true;
        $draft->save();

        $this->assertTrue($entryA->fresh()->status_is_public);
        // entryB pointed at $published (already is_public=true); no change to it
        $this->assertTrue($entryB->fresh()->status_is_public);
        $this->assertSame('published', $entryB->fresh()->status_handle);
    }
}
