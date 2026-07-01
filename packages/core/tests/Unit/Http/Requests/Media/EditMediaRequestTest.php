<?php

namespace Tests\Unit\Http\Requests\Media;

use AdAstra\Http\Requests\Media\EditMediaRequest;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library as MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EditMediaRequestTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRequest(mixed $mediaItemParam): EditMediaRequest
    {
        // Route::setParameter() requires the route to be bound to a real request
        // first — it internally calls parameters() which throws LogicException if
        // the route has never been bound. Binding to an HTTP request with a
        // matching URI lets the router compile the pattern and extract parameters
        // from the path, making parameter() work correctly afterwards.
        $httpRequest = Request::create(
            '/admin/media/' . $mediaItemParam . '/edit',
            'PUT'
        );

        $route = new Route(['PUT'], 'admin/media/{media_item}/edit', ['uses' => fn() => null]);
        $route->bind($httpRequest);

        $request = EditMediaRequest::createFrom($httpRequest);
        $request->setRouteResolver(fn() => $route);

        return $request;
    }

    private function makeLibrary(string $handle = 'test-lib'): MediaLibrary
    {
        return MediaLibrary::create([
            'name' => 'Test Library',
            'handle' => $handle,
            'adapter' => 'local',
        ]);
    }

    // -------------------------------------------------------------------------
    // C4 — does not crash when media or library_id is missing
    // -------------------------------------------------------------------------

    public function test_rules_does_not_crash_when_media_record_not_found(): void
    {
        $request = $this->makeRequest(999999);

        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_rules_does_not_crash_when_library_id_is_null(): void
    {
        $media = Media::factory()->create(['library_id' => null]);
        $request = $this->makeRequest($media->id);

        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_messages_does_not_crash_when_library_id_is_null(): void
    {
        $media = Media::factory()->create(['library_id' => null]);
        $request = $this->makeRequest($media->id);

        $this->assertIsArray($request->messages());
    }

    public function test_attributes_does_not_crash_when_library_id_is_null(): void
    {
        $media = Media::factory()->create(['library_id' => null]);
        $request = $this->makeRequest($media->id);

        $this->assertIsArray($request->attributes());
    }

    // -------------------------------------------------------------------------
    // H2 — schema resolved only once regardless of how many methods are called
    // -------------------------------------------------------------------------

    public function test_messages_and_attributes_incur_no_queries_after_rules_is_called(): void
    {
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $request = $this->makeRequest($media->id);

        $request->rules(); // warms the cache

        DB::enableQueryLog();
        $request->messages();
        $request->attributes();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            0,
            $count,
            'messages() and attributes() should read from the cached schema with zero DB queries.'
        );
    }

    public function test_repeated_calls_to_rules_incur_no_extra_queries(): void
    {
        $library = $this->makeLibrary('test-lib-b');
        $media = Media::factory()->create(['library_id' => $library->id]);
        $request = $this->makeRequest($media->id);

        $request->rules(); // warms the cache

        DB::enableQueryLog();
        $request->rules();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            0,
            $count,
            'A second call to rules() should use the cached schema with zero DB queries.'
        );
    }

    // -------------------------------------------------------------------------
    // Correct behaviour when a library with no field layout is present
    // -------------------------------------------------------------------------

    public function test_rules_returns_name_rule_when_library_has_no_field_layout(): void
    {
        $library = $this->makeLibrary('no-layout');
        $media = Media::factory()->create(['library_id' => $library->id]);
        $request = $this->makeRequest($media->id);

        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_rules_returns_only_name_and_status_rules_when_no_dynamic_fields_exist(): void
    {
        $library = $this->makeLibrary('no-fields');
        $media = Media::factory()->create(['library_id' => $library->id]);
        $request = $this->makeRequest($media->id);

        $rules = $request->rules();

        $this->assertSame(['name', 'status'], array_keys($rules));
    }
}
