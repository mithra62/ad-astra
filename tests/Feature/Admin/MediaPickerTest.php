<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Media\Library;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeLibrary(string $handle): Library
    {
        return Library::create(['name' => ucfirst($handle), 'handle' => $handle, 'adapter' => 'local']);
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_redirects_or_rejects(): void
    {
        $library = $this->makeLibrary('photos');

        $response = $this->getJson(route('media.picker.index', ['library_id' => [$library->id]]));

        // Admin routes use the `auth` middleware; an unauthenticated JSON
        // request returns 401, an unauthenticated browser request redirects.
        $this->assertContains($response->status(), [302, 401, 403]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_request_without_library_id_fails_validation(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->getJson(route('media.picker.index'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['library_id']);
    }

    // -------------------------------------------------------------------------
    // Library scoping
    // -------------------------------------------------------------------------

    public function test_returns_only_media_from_requested_libraries(): void
    {
        $user = $this->makeSuperAdmin();
        $a = $this->makeLibrary('a');
        $b = $this->makeLibrary('b');

        $inA = Media::factory()->count(3)->create(['library_id' => $a->id]);
        Media::factory()->count(2)->create(['library_id' => $b->id]);

        $response = $this->actingAs($user)
            ->getJson(route('media.picker.index', ['library_id' => [$a->id]]))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'last_page', 'per_page']]);

        $ids = collect($response->json('data'))->pluck('id')->all();
        sort($ids);
        $expected = $inA->pluck('id')->sort()->values()->all();

        $this->assertEquals($expected, $ids);
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_unknown_library_ids_are_dropped_and_yield_empty_result(): void
    {
        $user = $this->makeSuperAdmin();
        Media::factory()->count(2)->create(); // unrelated

        $response = $this->actingAs($user)
            ->getJson(route('media.picker.index', ['library_id' => [99999]]))
            ->assertOk();

        $this->assertSame(0, $response->json('meta.total'));
        $this->assertSame([], $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Search filter
    // -------------------------------------------------------------------------

    public function test_q_param_filters_by_name(): void
    {
        $user = $this->makeSuperAdmin();
        $lib = $this->makeLibrary('photos');

        Media::factory()->create(['library_id' => $lib->id, 'name' => 'sunset', 'original_name' => 'sunset.jpg']);
        Media::factory()->create(['library_id' => $lib->id, 'name' => 'forest', 'original_name' => 'forest.jpg']);
        Media::factory()->create(['library_id' => $lib->id, 'name' => 'beach',  'original_name' => 'beach.jpg']);

        $response = $this->actingAs($user)
            ->getJson(route('media.picker.index', ['library_id' => [$lib->id], 'q' => 'sun']))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('sunset', $response->json('data.0.name'));
    }

    public function test_q_param_escapes_like_wildcards(): void
    {
        $user = $this->makeSuperAdmin();
        $lib = $this->makeLibrary('photos');

        Media::factory()->create(['library_id' => $lib->id, 'name' => 'image_one', 'original_name' => 'image_one.jpg']);
        Media::factory()->create(['library_id' => $lib->id, 'name' => 'imageXone', 'original_name' => 'imageXone.jpg']);

        // Underscore is a SQL LIKE wildcard; with escaping, only the literal "_one" should match.
        $response = $this->actingAs($user)
            ->getJson(route('media.picker.index', ['library_id' => [$lib->id], 'q' => '_one']))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    public function test_pagination_respects_per_page(): void
    {
        $user = $this->makeSuperAdmin();
        $lib = $this->makeLibrary('photos');
        Media::factory()->count(5)->create(['library_id' => $lib->id]);

        $response = $this->actingAs($user)
            ->getJson(route('media.picker.index', ['library_id' => [$lib->id], 'per_page' => 2]))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    // -------------------------------------------------------------------------
    // Response shape
    // -------------------------------------------------------------------------

    public function test_response_includes_expected_fields_per_item(): void
    {
        $user = $this->makeSuperAdmin();
        $lib = $this->makeLibrary('photos');
        $m = Media::factory()->create([
            'library_id'    => $lib->id,
            'mime_type'     => 'image/jpeg',
            'name'          => 'pic',
            'original_name' => 'pic.jpg',
            'size'          => 1234,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('media.picker.index', ['library_id' => [$lib->id]]))
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertSame($m->id, $item['id']);
        $this->assertSame('pic', $item['name']);
        $this->assertSame('pic.jpg', $item['original_name']);
        $this->assertSame('image/jpeg', $item['mime_type']);
        $this->assertSame(1234, $item['size']);
        $this->assertSame($lib->id, $item['library_id']);
        $this->assertSame('Photos', $item['library_name']);
        $this->assertTrue($item['is_image']);
    }
}
