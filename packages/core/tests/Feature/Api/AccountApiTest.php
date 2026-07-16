<?php

namespace Tests\Feature\Api;

use AdAstra\Field\Types\Text;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\User;
use AdAstra\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature coverage for the Account API controller: the authenticated user's own
 * account. All endpoints sit behind auth:sanctum with no further permission gate.
 *
 *   GET  /api/v1/account           -> show
 *   PUT  /api/v1/account           -> update           (name + custom fields)
 *   PUT  /api/v1/account/password  -> updatePassword   (current_password gated)
 *   PUT  /api/v1/account/email     -> updateEmail      (current_password gated)
 */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create and authenticate a user with a known password.
     */
    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'password' => Hash::make('password'),
        ], $attributes));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * Configure a user field layout with two text fields and point the users
     * setting at it, mirroring how the User model resolves its schema.
     */
    private function configureUserLayout(): void
    {
        $type = FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []]
        );

        $layout = FieldLayout::factory()->create();
        $tab = Tab::factory()->create(['field_layout_id' => $layout->id]);

        foreach (['first_name' => 0, 'last_name' => 1] as $handle => $sort) {
            $field = Field::factory()->create(['handle' => $handle, 'field_type_id' => $type->id]);
            TabElement::factory()->create([
                'field_layout_tab_id' => $tab->id,
                'field_id' => $field->id,
                'sort_order' => $sort,
            ]);
        }

        app(Settings::class)->set('users', 'user_field_layout_id', $layout->id);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/account')->assertUnauthorized();
    }

    public function test_show_returns_the_authenticated_user(): void
    {
        $user = $this->actingUser(['name' => 'Ada Lovelace']);

        $this->getJson('/api/v1/account')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles']]);
    }

    public function test_show_fields_node_reflects_the_full_user_schema(): void
    {
        $this->configureUserLayout();
        $this->actingUser();

        $this->getJson('/api/v1/account')
            ->assertOk()
            ->assertJsonPath('data.fields.first_name', null)
            ->assertJsonPath('data.fields.last_name', null)
            ->assertJsonStructure(['data' => ['fields' => ['first_name', 'last_name']]]);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_name_without_touching_email(): void
    {
        $user = $this->actingUser(['name' => 'Old Name']);
        $originalEmail = $user->email;

        $this->putJson('/api/v1/account', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', $originalEmail);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => $originalEmail,
        ]);
    }

    public function test_update_requires_a_name(): void
    {
        $this->actingUser();

        $this->putJson('/api/v1/account', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_rejects_guests_with_401(): void
    {
        $this->putJson('/api/v1/account', ['name' => 'Nope'])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // updatePassword
    // -------------------------------------------------------------------------

    public function test_update_password_changes_password_with_correct_current(): void
    {
        $user = $this->actingUser();

        $this->putJson('/api/v1/account/password', [
            'current_password' => 'password',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newsecret123', $user->fresh()->password));
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $user = $this->actingUser();

        $this->putJson('/api/v1/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_update_password_rejects_guests_with_401(): void
    {
        $this->putJson('/api/v1/account/password', [])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // updateEmail
    // -------------------------------------------------------------------------

    public function test_update_email_changes_email_with_correct_current_password(): void
    {
        $user = $this->actingUser();

        $this->putJson('/api/v1/account/email', [
            'email' => 'moved@example.com',
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.email', 'moved@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'moved@example.com']);
    }

    public function test_update_email_rejects_wrong_current_password(): void
    {
        $user = $this->actingUser();
        $originalEmail = $user->email;

        $this->putJson('/api/v1/account/email', [
            'email' => 'moved@example.com',
            'current_password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $originalEmail]);
    }

    public function test_update_email_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $this->actingUser();

        $this->putJson('/api/v1/account/email', [
            'email' => 'taken@example.com',
            'current_password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_update_email_rejects_guests_with_401(): void
    {
        $this->putJson('/api/v1/account/email', [])->assertUnauthorized();
    }
}
