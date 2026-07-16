<?php

namespace Tests\Feature;

use AdAstra\Models\GateBypassLog;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use AdAstra\Services\GateBypassRecorder;
use AdAstra\Settings;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GateBypassLogTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Recording
    // -------------------------------------------------------------------------

    public function test_super_admin_gate_check_is_recorded_with_subject(): void
    {
        $admin = $this->makeSuperAdmin();
        $subject = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $subject));

        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseHas('gate_bypass_logs', [
            'user_id' => $admin->id,
            'ability' => 'update',
            'subject_type' => 'user',
            'subject_id' => (string) $subject->id,
            'occurrences' => 1,
        ]);
    }

    public function test_regular_user_gate_checks_are_not_recorded(): void
    {
        $user = User::factory()->create();

        Gate::forUser($user)->allows('update', $user);
        Gate::forUser($user)->allows('manage-anything');

        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseCount('gate_bypass_logs', 0);
    }

    public function test_identical_checks_are_deduped_with_occurrence_count(): void
    {
        $admin = $this->makeSuperAdmin();
        $subjectA = User::factory()->create();
        $subjectB = User::factory()->create();

        Gate::forUser($admin)->allows('update', $subjectA);
        Gate::forUser($admin)->allows('update', $subjectA);
        Gate::forUser($admin)->allows('update', $subjectA);
        Gate::forUser($admin)->allows('update', $subjectB);

        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseCount('gate_bypass_logs', 2);
        $this->assertDatabaseHas('gate_bypass_logs', [
            'subject_id' => (string) $subjectA->id,
            'occurrences' => 3,
        ]);
        $this->assertDatabaseHas('gate_bypass_logs', [
            'subject_id' => (string) $subjectB->id,
            'occurrences' => 1,
        ]);
    }

    public function test_subject_derivation_for_no_arg_and_class_string_checks(): void
    {
        $admin = $this->makeSuperAdmin();

        Gate::forUser($admin)->allows('manage-settings');
        Gate::forUser($admin)->allows('create', User::class);

        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseHas('gate_bypass_logs', [
            'ability' => 'manage-settings',
            'subject_type' => null,
            'subject_id' => null,
        ]);
        // Class-string subjects map to their morph alias with no id.
        $this->assertDatabaseHas('gate_bypass_logs', [
            'ability' => 'create',
            'subject_type' => 'user',
            'subject_id' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // HTTP end-to-end (terminating flush + request context)
    // -------------------------------------------------------------------------

    public function test_http_request_flushes_on_termination_with_request_context(): void
    {
        Route::post('_test/gate-bypass', function () {
            Gate::allows('update', request()->user());

            return response()->noContent();
        })->middleware(['web', 'auth']);

        $admin = $this->makeSuperAdmin();

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($admin)
            ->post('/_test/gate-bypass')
            ->assertNoContent();

        $log = GateBypassLog::query()->where('ability', 'update')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('POST', $log->method);
        $this->assertStringContainsString('_test/gate-bypass', $log->url);
        $this->assertNotNull($log->ip);
    }

    // -------------------------------------------------------------------------
    // Read-request filtering
    // -------------------------------------------------------------------------

    public function test_read_requests_are_skipped_by_default_and_logged_when_enabled(): void
    {
        $admin = $this->makeSuperAdmin();
        $subject = User::factory()->create();

        $this->bindRoutedRequest('GET');

        Gate::forUser($admin)->allows('update', $subject);
        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseCount('gate_bypass_logs', 0);

        app(Settings::class)->set('security', 'gate_bypass_log_include_reads', true);
        app(GateBypassRecorder::class)->reset(); // clear the memoized settings

        Gate::forUser($admin)->allows('update', $subject);
        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseCount('gate_bypass_logs', 1);
        $this->assertDatabaseHas('gate_bypass_logs', ['method' => 'GET']);
    }

    public function test_write_requests_are_logged_regardless_of_read_setting(): void
    {
        $admin = $this->makeSuperAdmin();
        $subject = User::factory()->create();

        $this->bindRoutedRequest('POST');

        Gate::forUser($admin)->allows('update', $subject);
        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseCount('gate_bypass_logs', 1);
        $this->assertDatabaseHas('gate_bypass_logs', ['method' => 'POST']);
    }

    // -------------------------------------------------------------------------
    // Settings toggle
    // -------------------------------------------------------------------------

    public function test_disabled_setting_suppresses_logging_but_not_the_bypass(): void
    {
        app(Settings::class)->set('security', 'gate_bypass_log_enabled', false);

        $admin = $this->makeSuperAdmin();
        $subject = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $subject));

        app(GateBypassRecorder::class)->flush();

        $this->assertDatabaseCount('gate_bypass_logs', 0);
    }

    // -------------------------------------------------------------------------
    // Console context
    // -------------------------------------------------------------------------

    public function test_console_checks_record_null_request_columns_and_command_context(): void
    {
        $admin = $this->makeSuperAdmin();

        Gate::forUser($admin)->allows('manage-settings');

        app(GateBypassRecorder::class)->flush();

        $log = GateBypassLog::query()->first();

        $this->assertNotNull($log);
        $this->assertNull($log->method);
        $this->assertNull($log->url);
        $this->assertNull($log->ip);
        $this->assertArrayHasKey('command', $log->context);
    }

    // -------------------------------------------------------------------------
    // Failure isolation
    // -------------------------------------------------------------------------

    public function test_flush_failure_is_reported_but_never_thrown(): void
    {
        $handler = $this->spy(ExceptionHandler::class);

        $admin = $this->makeSuperAdmin();
        Gate::forUser($admin)->allows('manage-settings');

        Schema::drop('gate_bypass_logs');

        app(GateBypassRecorder::class)->flush();

        $handler->shouldHaveReceived('report');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'super admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Bind a routed request into the container so the recorder sees an HTTP
     * request with the given method (tests otherwise run in console mode,
     * where the recorder always records).
     */
    protected function bindRoutedRequest(string $method): void
    {
        $request = Request::create('/admin/anything', $method);
        $route = new RoutingRoute([$method], '/admin/anything', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $this->app->instance('request', $request);
    }
}
