<?php

namespace Tests\Unit\Traits;

use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the HasMedia trait using User as the concrete host model.
 */
class HasMediaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Setup
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        // Reset static handle cache so each test starts with a clean slate.
        $this->resetFieldHandleCache();
    }

    private function resetFieldHandleCache(): void
    {
        $ref = new \ReflectionProperty(\AdAstra\Traits\HasMedia::class, 'fieldHandleCache');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

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

    // -------------------------------------------------------------------------
    // H3 — handle→ID lookup is cached (only one query per unique handle)
    // -------------------------------------------------------------------------

    public function test_media_for_field_with_string_handle_queries_db_once(): void
    {
        Storage::fake('local');
        $user  = User::factory()->create();
        $field = Field::factory()->create(['handle' => 'gallery']);
        $lib   = $this->makeLibrary();
        $media = $this->makeMedia($lib);

        DB::table('mediables')->insert([
            'media_id'      => $media->id,
            'mediable_type' => 'user',
            'mediable_id'   => $user->id,
            'field_id'      => $field->id,
            'sort_order'    => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::enableQueryLog();

        $user->mediaForField('gallery')->get(); // first call — should query fields table
        $queryCountAfterFirst = count(DB::getQueryLog());

        $user->mediaForField('gallery')->get(); // second call — cache hit, no fields query
        $queryCountAfterSecond = count(DB::getQueryLog());

        DB::disableQueryLog();

        // Each ->get() runs at least one mediables query; the fields lookup
        // should only appear once total (between the two calls).
        $queriesDuringSecondCall = $queryCountAfterSecond - $queryCountAfterFirst;
        $this->assertSame(1, $queriesDuringSecondCall,
            'Second mediaForField() call should only run the mediables query, not the fields lookup.');
    }

    public function test_media_for_field_caches_different_handles_independently(): void
    {
        Storage::fake('local');
        $user    = User::factory()->create();
        $type    = FieldType::firstOrCreate(['object' => \AdAstra\Field\Types\Text::class], ['name' => 'Text', 'settings' => []]);
        $gallery  = Field::factory()->create(['handle' => 'gallery-a', 'field_type_id' => $type->id]);
        $featured = Field::factory()->create(['handle' => 'featured-a', 'field_type_id' => $type->id]);

        DB::enableQueryLog();

        $user->mediaForField('gallery-a')->get();
        $user->mediaForField('featured-a')->get(); // different handle — must query fields table once more

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $fieldQueries = collect($log)->filter(
            fn ($q) => str_contains($q['query'], 'fields') && str_contains($q['query'], 'handle')
        );

        $this->assertSame(2, $fieldQueries->count(),
            'Each unique handle should produce exactly one fields lookup.');
    }

    public function test_media_for_field_returns_null_field_id_for_unknown_handle(): void
    {
        $user   = User::factory()->create();
        $result = $user->mediaForField('no-such-field')->get();

        $this->assertCount(0, $result);
    }
}
