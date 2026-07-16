<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Account\Token controller (the authenticated
 * user's own API tokens).
 */
class AccountTokenAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function tokenId(): int
    {
        return $this->admin->createToken('Test Token')->accessToken->id;
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('account.tokens.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.tokens.index'))->assertOk();
    }

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.tokens.create'))->assertOk();
    }

    public function test_confirm_renders(): void
    {
        $this->actingAs($this->admin)->get(route('account.tokens.confirm', $this->tokenId()))->assertOk();
    }

    public function test_edit_renders(): void
    {
        $id = $this->tokenId();

        $this->actingAs($this->admin)->get(route('account.tokens.edit', $id))->assertOk();
    }

    public function test_edit_returns_404_for_missing_token(): void
    {
        $this->actingAs($this->admin)->get(route('account.tokens.edit', 999999))->assertNotFound();
    }

    public function test_confirm_returns_404_for_missing_token(): void
    {
        $this->actingAs($this->admin)->get(route('account.tokens.confirm', 999999))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_token_and_renders_created_view(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('account.tokens.store'), ['name' => 'CI Token']);

        $response->assertOk();
        $response->assertViewIs('admin::account.tokens.created');
        $response->assertSessionMissing('success');
        $response->assertSee($response->viewData('new_token')->plainTextToken, false);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->admin->id,
            'name' => 'CI Token',
        ]);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('account.tokens.store'), [])
            ->assertSessionHasErrors('name');
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_renames_token_and_redirects(): void
    {
        $id = $this->tokenId();

        $this->actingAs($this->admin)
            ->put(route('account.tokens.update', $id), ['name' => 'Renamed Token'])
            ->assertRedirectContains('/admin/account/tokens')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id, 'name' => 'Renamed Token']);
    }

    public function test_update_returns_404_for_missing_token(): void
    {
        $this->actingAs($this->admin)
            ->put(route('account.tokens.update', 999999), ['name' => 'Nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_token_and_redirects(): void
    {
        $id = $this->tokenId();

        $this->actingAs($this->admin)
            ->delete(route('account.tokens.destroy', $id), ['confirm_removal' => 1])
            ->assertRedirectContains('/admin/account/tokens')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $id = $this->tokenId();

        $this->actingAs($this->admin)
            ->delete(route('account.tokens.destroy', $id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id]);
    }
}
