<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Media\Library;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryControllerTest extends TestCase
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

    private function makeLibrary(string $handle = 'photos'): Library
    {
        return Library::create(['name' => 'Photos', 'handle' => $handle, 'adapter' => 'local']);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests(): void
    {
        $this->get(route('media.libraries'))->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_ok(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries'))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function test_create_returns_ok(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.create'))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_library_and_redirects_to_show(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.libraries.store'), [
                'name'    => 'Documents',
                'handle'  => 'documents',
                'adapter' => 'local',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media_libraries', ['handle' => 'documents']);
    }

    public function test_store_fails_validation_when_handle_missing(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.libraries.store'), ['name' => 'No Handle'])
            ->assertSessionHasErrors('handle');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_ok_for_existing_library(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.show', $library->id))
            ->assertOk();
    }

    public function test_show_returns_404_for_missing_library(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.show', 99999))
            ->assertNotFound();
    }

    public function test_show_lists_media_in_sort_order(): void
    {
        $library = $this->makeLibrary();
        $first   = Media::factory()->create(['library_id' => $library->id, 'sort_order' => 1, 'name' => 'First']);
        $second  = Media::factory()->create(['library_id' => $library->id, 'sort_order' => 2, 'name' => 'Second']);

        $response = $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.show', $library->id))
            ->assertOk();

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Second'),
            strpos($content, 'First'),
            'Media should appear in sort_order sequence.'
        );
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function test_edit_returns_ok_for_existing_library(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.edit', $library->id))
            ->assertOk();
    }

    public function test_edit_returns_404_for_missing_library(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.edit', 99999))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_persists_name_and_redirects(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->put(route('media.libraries.update', $library->id), [
                'name'    => 'Updated Name',
                'handle'  => $library->handle,
                'adapter' => $library->adapter,
            ])
            ->assertRedirect(route('media.libraries'));

        $this->assertDatabaseHas('media_libraries', ['id' => $library->id, 'name' => 'Updated Name']);
    }

    // -------------------------------------------------------------------------
    // confirm
    // -------------------------------------------------------------------------

    public function test_confirm_returns_ok_for_existing_library(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.confirm', $library->id))
            ->assertOk();
    }

    public function test_confirm_redirects_when_library_not_found(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.libraries.confirm', 99999))
            ->assertRedirect(route('media.libraries'));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_library_and_redirects(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->delete(route('media.libraries.destroy', $library->id), ['confirm_removal' => '1'])
            ->assertRedirect(route('media.libraries'));

        $this->assertDatabaseMissing('media_libraries', ['id' => $library->id]);
    }

    // -------------------------------------------------------------------------
    // upload
    // -------------------------------------------------------------------------

    public function test_upload_stores_file_and_redirects_to_media_show(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.libraries.upload', $library->id), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('media', 1);
    }

    public function test_upload_returns_404_for_missing_library(): void
    {
        Storage::fake('local');

        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.libraries.upload', 99999), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertNotFound();
    }
}
