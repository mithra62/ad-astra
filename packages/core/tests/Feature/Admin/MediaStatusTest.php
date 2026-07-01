<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Models\Role;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    /**
     * @return array{0: Library, 1: StatusGroup, 2: Status, 3: Status}
     */
    private function makeLibraryWithStatuses(string $handle = 'photos'): array
    {
        $statusGroup = StatusGroup::factory()->create();
        $draft = Status::factory()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'name' => 'Draft',
            'is_default' => true,
            'is_public' => false,
        ]);
        $published = Status::factory()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'published',
            'name' => 'Published',
            'is_default' => false,
            'is_public' => true,
        ]);
        $library = Library::create([
            'name' => ucfirst($handle) . ' Library',
            'handle' => $handle,
            'adapter' => 'local',
            'status_group_id' => $statusGroup->id,
        ]);

        return [$library, $statusGroup, $draft, $published];
    }

    private function makeMedia(Library $library, array $overrides = []): Media
    {
        return Media::factory()->create(array_merge([
            'library_id' => $library->id,
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Model & relationships
    // -------------------------------------------------------------------------

    public function test_media_belongs_to_status(): void
    {
        [$library, , $draft] = $this->makeLibraryWithStatuses();
        $media = $this->makeMedia($library, ['status_id' => $draft->id]);

        $this->assertInstanceOf(Status::class, $media->status);
        $this->assertSame($draft->id, $media->status->id);
    }

    public function test_library_status_group_relationship(): void
    {
        [$library, $statusGroup] = $this->makeLibraryWithStatuses();

        $this->assertSame($statusGroup->id, $library->statusGroup->id);
    }

    public function test_library_statuses_returns_all_in_group(): void
    {
        [$library] = $this->makeLibraryWithStatuses();

        $handles = $library->statuses->pluck('handle')->all();

        $this->assertContains('draft', $handles);
        $this->assertContains('published', $handles);
    }

    public function test_library_default_status_returns_default(): void
    {
        [$library, , $draft] = $this->makeLibraryWithStatuses();

        $this->assertSame($draft->handle, $library->defaultStatus()->handle);
    }

    public function test_library_default_status_returns_null_when_no_status_group(): void
    {
        $library = Library::create([
            'name' => 'Ungoverned',
            'handle' => 'ungoverned',
            'adapter' => 'local',
        ]);

        $this->assertNull($library->defaultStatus());
    }

    public function test_status_group_has_media_libraries_inverse(): void
    {
        [$library, $statusGroup] = $this->makeLibraryWithStatuses();

        $this->assertSame($library->id, $statusGroup->mediaLibraries->first()->id);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_published_scope_returns_only_public_media(): void
    {
        [$library, , $draft, $published] = $this->makeLibraryWithStatuses();
        $this->makeMedia($library, [
            'status_id' => $draft->id,
            'status_handle' => $draft->handle,
            'status_is_public' => false,
            'file_name' => 'draft.jpg',
            'path' => 'uploads/draft.jpg',
        ]);
        $public = $this->makeMedia($library, [
            'status_id' => $published->id,
            'status_handle' => $published->handle,
            'status_is_public' => true,
            'file_name' => 'public.jpg',
            'path' => 'uploads/public.jpg',
        ]);

        $results = Media::published()->get();

        $this->assertSame(1, $results->count());
        $this->assertSame($public->id, $results->first()->id);
    }

    public function test_with_status_scope_filters_by_handle(): void
    {
        [$library, , $draft, $published] = $this->makeLibraryWithStatuses();
        $this->makeMedia($library, [
            'status_handle' => $draft->handle,
            'file_name' => 'a.jpg',
            'path' => 'uploads/a.jpg',
        ]);
        $this->makeMedia($library, [
            'status_handle' => $published->handle,
            'file_name' => 'b.jpg',
            'path' => 'uploads/b.jpg',
        ]);

        $this->assertSame(1, Media::withStatus('draft')->count());
        $this->assertSame(1, Media::withStatus('published')->count());
    }

    public function test_published_scope_excludes_soft_deleted_media(): void
    {
        [$library, , , $published] = $this->makeLibraryWithStatuses();
        $alive = $this->makeMedia($library, [
            'status_handle' => $published->handle,
            'status_is_public' => true,
        ]);
        $deleted = $this->makeMedia($library, [
            'status_handle' => $published->handle,
            'status_is_public' => true,
            'file_name' => 'deleted.jpg',
            'path' => 'uploads/deleted.jpg',
        ]);
        $deleted->delete();

        $results = Media::published()->pluck('id')->all();

        $this->assertSame([$alive->id], $results);
    }

    // -------------------------------------------------------------------------
    // Upload auto-assignment
    // -------------------------------------------------------------------------

    public function test_upload_assigns_default_status_when_library_has_status_group(): void
    {
        Storage::fake('local');
        [$library, , $draft] = $this->makeLibraryWithStatuses();

        $media = $library->addMediaFromUpload(UploadedFile::fake()->image('x.jpg'));

        $this->assertSame($draft->id, $media->status_id);
        $this->assertSame('draft', $media->status_handle);
        $this->assertFalse($media->status_is_public);
    }

    public function test_upload_leaves_status_null_when_library_has_no_status_group(): void
    {
        Storage::fake('local');
        $library = Library::create([
            'name' => 'Ungoverned',
            'handle' => 'ungoverned',
            'adapter' => 'local',
        ]);

        $media = $library->addMediaFromUpload(UploadedFile::fake()->image('x.jpg'));

        $this->assertNull($media->status_id);
        $this->assertNull($media->status_handle);
    }

    public function test_upload_leaves_status_null_when_governed_library_has_no_default(): void
    {
        Storage::fake('local');
        $statusGroup = StatusGroup::factory()->create();
        // No status has is_default = true.
        Status::factory()->create([
            'status_group_id' => $statusGroup->id,
            'handle' => 'draft',
            'is_default' => false,
            'is_public' => false,
        ]);
        $library = Library::create([
            'name' => 'No Default',
            'handle' => 'no-default',
            'adapter' => 'local',
            'status_group_id' => $statusGroup->id,
        ]);

        $media = $library->addMediaFromUpload(UploadedFile::fake()->image('x.jpg'));

        $this->assertNull($media->status_id);
        $this->assertNull($media->status_handle);
    }

    public function test_upload_caller_attributes_override_default_status(): void
    {
        Storage::fake('local');
        [$library, , , $published] = $this->makeLibraryWithStatuses();

        $media = $library->addMediaFromUpload(UploadedFile::fake()->image('x.jpg'), [
            'status_id' => $published->id,
            'status_handle' => $published->handle,
            'status_is_public' => $published->is_public,
        ]);

        $this->assertSame($published->id, $media->status_id);
        $this->assertSame('published', $media->status_handle);
        $this->assertTrue($media->status_is_public);
    }

    // -------------------------------------------------------------------------
    // HTTP validation (the most important set)
    // -------------------------------------------------------------------------

    public function test_update_accepts_status_from_librarys_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$library] = $this->makeLibraryWithStatuses();
        $media = $this->makeMedia($library, ['status_handle' => 'draft', 'status_is_public' => false]);

        $response = $this->actingAs($user)->put(route('media.update', $media->id), [
            'name' => 'My Photo',
            'status' => 'published',
        ]);

        $response->assertRedirect(route('media.show', $media->id));
        $fresh = $media->fresh();
        $this->assertSame('published', $fresh->status_handle);
        $this->assertTrue($fresh->status_is_public);
    }

    public function test_update_rejects_status_from_another_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        [$library] = $this->makeLibraryWithStatuses();
        $media = $this->makeMedia($library, ['status_handle' => 'draft']);

        $otherGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $otherGroup->id,
            'handle' => 'other-status',
            'name' => 'Other',
        ]);

        $response = $this->actingAs($user)
            ->from(route('media.edit', $media->id))
            ->put(route('media.update', $media->id), [
                'name' => 'My Photo',
                'status' => 'other-status',
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('draft', $media->fresh()->status_handle);
    }

    public function test_update_accepts_null_status(): void
    {
        $user = $this->makeSuperAdmin();
        [$library] = $this->makeLibraryWithStatuses();
        $media = $this->makeMedia($library, ['status_handle' => 'draft']);

        $response = $this->actingAs($user)->put(route('media.update', $media->id), [
            'name' => 'My Photo',
            'status' => null,
        ]);

        $response->assertSessionDoesntHaveErrors('status');
    }

    public function test_update_rejects_status_when_library_has_no_status_group(): void
    {
        $user = $this->makeSuperAdmin();
        $library = Library::create([
            'name' => 'Ungoverned',
            'handle' => 'ungoverned',
            'adapter' => 'local',
        ]);
        $media = $this->makeMedia($library);

        $response = $this->actingAs($user)
            ->from(route('media.edit', $media->id))
            ->put(route('media.update', $media->id), [
                'name' => 'My Photo',
                'status' => 'anything-at-all',
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertNull($media->fresh()->status_handle);
    }

    public function test_update_syncs_status_is_public_when_status_changes(): void
    {
        $user = $this->makeSuperAdmin();
        [$library, , $draft, $published] = $this->makeLibraryWithStatuses();
        $media = $this->makeMedia($library, [
            'status_id' => $draft->id,
            'status_handle' => 'draft',
            'status_is_public' => false,
        ]);

        $this->actingAs($user)->put(route('media.update', $media->id), [
            'name' => 'My Photo',
            'status' => 'published',
        ]);

        $fresh = $media->fresh();
        $this->assertSame($published->id, $fresh->status_id);
        $this->assertSame('published', $fresh->status_handle);
        $this->assertTrue($fresh->status_is_public);
    }

    public function test_stale_status_handle_after_library_status_group_reassignment(): void
    {
        // Documents that existing media is NOT auto-migrated when a library's
        // status_group_id changes. The denormalized handle/is_public on the
        // media row remains pinned to the original status.
        [$library, , $draft] = $this->makeLibraryWithStatuses();
        $media = $this->makeMedia($library, [
            'status_id' => $draft->id,
            'status_handle' => 'draft',
            'status_is_public' => false,
        ]);

        $newGroup = StatusGroup::factory()->create();
        Status::factory()->create([
            'status_group_id' => $newGroup->id,
            'handle' => 'archived',
            'is_default' => true,
        ]);
        $library->update(['status_group_id' => $newGroup->id]);

        $fresh = $media->fresh();
        $this->assertSame('draft', $fresh->status_handle);
        $this->assertSame($draft->id, $fresh->status_id);
    }
}
