<?php

namespace Tests\Feature\Admin;

use App\Models\FieldLayout;
use App\Models\User;
use App\Settings;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeleteFieldLayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Permission::firstOrCreate(['name' => 'delete field layout']);
        Permission::firstOrCreate(['name' => 'access admin']);

        $this->admin = User::factory()->active()->create();
        $this->admin->givePermissionTo(['access admin', 'delete field layout']);
    }

    public function test_destroy_fails_validation_when_layout_is_assigned_to_user_schema(): void
    {
        $layout = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id, null);

        $response = $this->actingAs($this->admin)
            ->delete(route('field-layouts.destroy', $layout->id), [
                'confirm_removal' => '1',
            ]);

        $response->assertSessionHasErrors('confirm_removal');
        $this->assertDatabaseHas('field_layouts', ['id' => $layout->id]);
    }

    public function test_destroy_error_message_identifies_user_schema_assignment(): void
    {
        $layout = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id, null);

        $this->actingAs($this->admin)
            ->delete(route('field-layouts.destroy', $layout->id), [
                'confirm_removal' => '1',
            ]);

        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('User Schema', $errors->first('confirm_removal'));
    }

    public function test_destroy_succeeds_when_layout_is_not_assigned_to_user_schema(): void
    {
        $assigned = FieldLayout::factory()->create();
        $other    = FieldLayout::factory()->create();
        app(Settings::class)->set('users', 'user_field_layout_id', $assigned->id, null);

        $response = $this->actingAs($this->admin)
            ->delete(route('field-layouts.destroy', $other->id), [
                'confirm_removal' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('field_layouts', ['id' => $other->id]);
    }
}
