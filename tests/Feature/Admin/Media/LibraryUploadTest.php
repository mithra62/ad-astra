<?php

namespace Tests\Feature\Admin\Media;

use App\Models\Media\Library;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Storage::fake('local');
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

    private function makeLibrary(): Library
    {
        return Library::create(['name' => 'Uploads', 'handle' => 'uploads', 'adapter' => 'local']);
    }

    // -------------------------------------------------------------------------
    // JSON content negotiation
    // -------------------------------------------------------------------------

    public function test_upload_with_json_accept_returns_json_on_success(): void
    {
        $user    = $this->makeSuperAdmin();
        $library = $this->makeLibrary();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('media.libraries.upload', $library->id), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'url']);
    }

    public function test_upload_with_json_accept_returns_422_json_when_no_file(): void
    {
        $user    = $this->makeSuperAdmin();
        $library = $this->makeLibrary();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('media.libraries.upload', $library->id), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_with_json_accept_returns_404_json_for_missing_library(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('media.libraries.upload', 99999), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertNotFound()
            ->assertJson(['error' => trans('media.library.not_found')]);
    }

    public function test_upload_without_json_accept_returns_redirect_on_success(): void
    {
        $user    = $this->makeSuperAdmin();
        $library = $this->makeLibrary();

        $this->actingAs($user)
            ->post(route('media.libraries.upload', $library->id), [
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertRedirect();
    }
}
