<?php

namespace Tests\Unit\Traits;

use App\Models\Media;
use App\Models\Media\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the HasMedia trait using User as the concrete host model.
 */
class HasMediaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLibrary(string $handle = 'general'): Library
    {
        return Library::create([
            'name'    => ucfirst($handle) . ' Library',
            'handle'  => $handle,
            'adapter' => 'local',
        ]);
    }

    private function makeMedia(Library $library, string $path = 'uploads/test.jpg'): Media
    {
        return Media::factory()->image()->create([
            'library_id' => $library->id,
            'disk'       => 'local',
            'path'       => $path,
        ]);
    }

    // -------------------------------------------------------------------------
    // media() relation
    // -------------------------------------------------------------------------

    public function test_media_relation_is_morph_to_many(): void
    {
        $this->assertInstanceOf(MorphToMany::class, User::factory()->create()->media());
    }

    // -------------------------------------------------------------------------
    // attachMedia / directMedia
    // -------------------------------------------------------------------------

    public function test_attach_media_creates_pivot_row(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary());

        $user->attachMedia($media);

        $this->assertTrue($user->directMedia()->where('media.id', $media->id)->exists());
    }

    public function test_attach_media_is_idempotent(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary());

        $user->attachMedia($media);
        $user->attachMedia($media); // second call must not throw

        $this->assertCount(1, $user->directMedia);
    }

    public function test_attach_media_uses_sentinel_field_id_zero(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary());

        $user->attachMedia($media);

        $this->assertDatabaseHas('mediables', [
            'media_id'      => $media->id,
            'mediable_type' => 'user',         // morphMap alias
            'mediable_id'   => $user->id,
            'field_id'      => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // detachMedia
    // -------------------------------------------------------------------------

    public function test_detach_media_removes_pivot_row(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary());

        $user->attachMedia($media);
        $user->detachMedia($media);

        $this->assertFalse($user->directMedia()->where('media.id', $media->id)->exists());
    }

    public function test_detach_media_removes_all_pivot_rows_for_item(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $lib   = $this->makeLibrary();
        $media = $this->makeMedia($lib);

        // Insert a field-driven row as well (field_id = 5) via raw insert.
        \Illuminate\Support\Facades\DB::table('mediables')->insert([
            'media_id'      => $media->id,
            'mediable_type' => 'user',
            'mediable_id'   => $user->id,
            'field_id'      => 5,
            'sort_order'    => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $user->detachMedia($media);

        $this->assertDatabaseMissing('mediables', ['media_id' => $media->id, 'mediable_id' => $user->id]);
    }

    // -------------------------------------------------------------------------
    // syncMedia
    // -------------------------------------------------------------------------

    public function test_sync_media_replaces_direct_attachments(): void
    {
        Storage::fake('local');
        $user   = User::factory()->create();
        $lib    = $this->makeLibrary();
        $mediaA = $this->makeMedia($lib, 'uploads/a.jpg');
        $mediaB = $this->makeMedia($lib, 'uploads/b.jpg');

        $user->attachMedia($mediaA);
        $user->syncMedia([$mediaB->id]);

        $this->assertFalse($user->directMedia()->where('media.id', $mediaA->id)->exists(), 'A should be detached');
        $this->assertTrue($user->directMedia()->where('media.id', $mediaB->id)->exists(), 'B should be attached');
    }

    // -------------------------------------------------------------------------
    // firstMedia
    // -------------------------------------------------------------------------

    public function test_first_media_returns_null_when_nothing_attached(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->firstMedia());
    }

    public function test_first_media_returns_first_direct_attachment(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary());

        $user->attachMedia($media);

        $this->assertNotNull($user->firstMedia());
        $this->assertEquals($media->id, $user->firstMedia()->id);
    }

    public function test_first_media_scoped_to_library_handle(): void
    {
        Storage::fake('local');
        $user    = User::factory()->create();
        $avatars = $this->makeLibrary('avatars');
        $docs    = $this->makeLibrary('docs');

        $avatar = $this->makeMedia($avatars, 'uploads/avatar.jpg');
        $doc    = $this->makeMedia($docs,    'uploads/doc.jpg');

        $user->attachMedia($avatar);
        $user->attachMedia($doc);

        $result = $user->firstMedia('avatars');

        $this->assertNotNull($result);
        $this->assertEquals($avatar->id, $result->id);
    }

    public function test_first_media_returns_null_when_library_handle_does_not_match(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary('general'), 'uploads/g.jpg');

        $user->attachMedia($media);

        $this->assertNull($user->firstMedia('avatars'));
    }

    // -------------------------------------------------------------------------
    // mediaForField
    // -------------------------------------------------------------------------

    public function test_media_for_field_scopes_by_field_id(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $lib   = $this->makeLibrary();
        $media = $this->makeMedia($lib);

        // Simulate a field-driven pivot (field_id = 7).
        \Illuminate\Support\Facades\DB::table('mediables')->insert([
            'media_id'      => $media->id,
            'mediable_type' => 'user',
            'mediable_id'   => $user->id,
            'field_id'      => 7,
            'sort_order'    => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $result = $user->mediaForField(7)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($media->id, $result->first()->id);
    }

    public function test_media_for_field_does_not_return_direct_attachments(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $media = $this->makeMedia($this->makeLibrary());

        $user->attachMedia($media); // field_id = 0 sentinel

        $this->assertCount(0, $user->mediaForField(7)->get());
    }
}
