<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
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

    private function makeMedia(Library $library): Media
    {
        return Media::factory()->create([
            'library_id' => $library->id,
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests(): void
    {
        $this->get(route('media.index'))->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_ok_for_authenticated_user(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.index'))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function test_create_returns_ok_for_valid_library(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.create', $library->id))
            ->assertOk();
    }

    public function test_create_redirects_when_library_not_found(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.create', 99999))
            ->assertRedirect(route('media.libraries'));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_redirects_to_media_libraries(): void
    {
        $library = $this->makeLibrary();

        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.store', $library->id))
            ->assertRedirect(route('media.libraries'));
    }

    // -------------------------------------------------------------------------
    // upload — category-group scoping
    // -------------------------------------------------------------------------

    public function test_upload_rejects_category_from_unattached_category_group(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $categoryGroup = CategoryGroup::factory()->create();
        $category = Category::factory()->for($categoryGroup, 'group')->create();

        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.libraries.upload', $library->id), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
                'categories' => [$category->id],
            ])
            ->assertSessionHasErrors('categories.0');
    }

    public function test_upload_accepts_category_from_attached_category_group(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $categoryGroup = CategoryGroup::factory()->create();
        $library->categoryGroups()->syncWithoutDetaching([$categoryGroup->id]);
        $category = Category::factory()->for($categoryGroup, 'group')->create();

        $this->actingAs($this->makeSuperAdmin())
            ->post(route('media.libraries.upload', $library->id), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
                'categories' => [$category->id],
            ])
            ->assertSessionHasNoErrors();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_ok_for_existing_media(): void
    {
        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.show', $media->id))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function test_edit_returns_ok_for_existing_media(): void
    {
        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.edit', $media->id))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_persists_name_and_redirects_to_show(): void
    {
        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->put(route('media.update', $media->id), ['name' => 'Updated Name'])
            ->assertRedirect(route('media.show', $media->id));

        $this->assertDatabaseHas('media', ['id' => $media->id, 'name' => 'Updated Name']);
    }

    // -------------------------------------------------------------------------
    // confirm
    // -------------------------------------------------------------------------

    public function test_confirm_returns_ok_for_existing_media(): void
    {
        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.confirm', $media->id))
            ->assertOk();
    }

    public function test_confirm_redirects_when_media_not_found(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.confirm', 99999))
            ->assertRedirect(route('media.index'));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_media_and_redirects(): void
    {
        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->delete(route('media.destroy', $media->id))
            ->assertRedirect(route('media.index'));

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    // -------------------------------------------------------------------------
    // download
    // -------------------------------------------------------------------------

    public function test_download_returns_file_when_it_exists_on_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/photo.jpg', 'fake-image-data');

        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.download', $media->id))
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    public function test_download_returns_404_when_file_missing_from_disk(): void
    {
        Storage::fake('local');
        // No file placed on disk.
        $media = $this->makeMedia($this->makeLibrary());

        $this->actingAs($this->makeSuperAdmin())
            ->get(route('media.download', $media->id))
            ->assertNotFound();
    }
}
