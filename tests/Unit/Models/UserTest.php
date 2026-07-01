<?php

namespace Tests\Unit\Models;

use AdAstra\Models\EntryAuthor;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Models\User;
use AdAstra\Models\User\OauthToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new User();

        $this->assertEquals(['name', 'email', 'password'], $model->getFillable());
    }

    public function test_status_columns_are_not_mass_assignable(): void
    {
        $model = new User();

        foreach (['status', 'suspended_until', 'banned_at', 'locked_until'] as $field) {
            $this->assertNotContains($field, $model->getFillable(), "Expected '$field' to NOT be fillable.");
        }
    }

    public function test_hides_password_and_remember_token(): void
    {
        $model = new User();

        $this->assertContains('password', $model->getHidden());
        $this->assertContains('remember_token', $model->getHidden());
    }

    public function test_casts_email_verified_at_to_datetime(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    public function test_email_verified_at_can_be_null(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->email_verified_at);
    }

    public function test_oauth_tokens_relationship_is_has_many(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(HasMany::class, $user->oauthTokens());
    }

    public function test_oauth_tokens_relationship_returns_user_tokens(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->for($user)->create(['provider' => 'github']);
        OauthToken::factory()->for($user)->create(['provider' => 'google']);

        $this->assertCount(2, $user->oauthTokens);
    }

    public function test_oauth_token_for_returns_active_token_for_provider(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->for($user)->create([
            'provider' => 'github',
            'expires_at' => now()->addHour(),
            'revoked_at' => null,
        ]);

        $result = $user->oauthTokenFor('github');

        $this->assertNotNull($result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_oauth_token_for_returns_null_for_nonexistent_provider(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->oauthTokenFor('github'));
    }

    public function test_oauth_token_for_returns_null_when_token_is_revoked(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->revoked()->for($user)->create(['provider' => 'github']);

        $this->assertNull($user->oauthTokenFor('github'));
    }

    public function test_oauth_token_for_returns_expired_token_when_not_revoked(): void
    {
        $user = User::factory()->create();
        $token = OauthToken::factory()->expired()->for($user)->create(['provider' => 'github']);

        $result = $user->oauthTokenFor('github');

        $this->assertNotNull($result);
        $this->assertEquals($token->id, $result->id);
    }

    public function test_oauth_token_for_returns_latest_by_expiry(): void
    {
        $user = User::factory()->create();
        OauthToken::factory()->for($user)->create([
            'provider' => 'github',
            'expires_at' => now()->addHour(),
        ]);
        $newer = OauthToken::factory()->for($user)->create([
            'provider' => 'github',
            'expires_at' => now()->addHours(2),
        ]);

        $result = $user->oauthTokenFor('github');

        $this->assertEquals($newer->id, $result->id);
    }

    public function test_avatar_returns_non_empty_string(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

        $this->assertIsString($user->avatar());
        $this->assertNotEmpty($user->avatar());
    }

    public function test_avatar_returns_generated_avatar_when_no_media_attached(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        // No media attached — falls back to Laravolt generated avatar.
        $avatar = $user->avatar();
        $this->assertIsString($avatar);
        $this->assertNotEmpty($avatar);
    }

    public function test_avatar_returns_media_url_when_avatar_attached(): void
    {
        Storage::fake('local');

        $user    = User::factory()->create(['name' => 'John Smith']);
        $library = Library::create([
            'name'    => 'User Avatars',
            'handle'  => 'avatars',
            'adapter' => 'local',
        ]);
        $media = Media::factory()->image()->create([
            'library_id' => $library->id,
            'disk'       => 'local',
            'path'       => 'avatars/test.jpg',
        ]);

        $user->attachMedia($media);

        $this->assertStringContainsString('test.jpg', $user->avatar());
    }

    public function test_set_avatar_attaches_media_to_user(): void
    {
        Storage::fake('local');

        $user    = User::factory()->create();
        $library = Library::create([
            'name'    => 'User Avatars',
            'handle'  => 'avatars',
            'adapter' => 'local',
        ]);
        $media = Media::factory()->image()->create([
            'library_id' => $library->id,
            'disk'       => 'local',
            'path'       => 'avatars/new.jpg',
        ]);

        $user->setAvatar($media);

        $this->assertTrue(
            $user->directMedia()->where('media.id', $media->id)->exists()
        );
    }

    public function test_set_avatar_replaces_existing_avatar(): void
    {
        Storage::fake('local');

        $user    = User::factory()->create();
        $library = Library::create([
            'name'    => 'User Avatars',
            'handle'  => 'avatars',
            'adapter' => 'local',
        ]);
        $old = Media::factory()->image()->create(['library_id' => $library->id, 'disk' => 'local', 'path' => 'avatars/old.jpg']);
        $new = Media::factory()->image()->create(['library_id' => $library->id, 'disk' => 'local', 'path' => 'avatars/new.jpg']);

        $user->attachMedia($old);
        $user->setAvatar($new);

        $this->assertFalse(
            $user->directMedia()->where('media.id', $old->id)->exists(),
            'Old avatar should be detached'
        );
        $this->assertTrue(
            $user->directMedia()->where('media.id', $new->id)->exists(),
            'New avatar should be attached'
        );
    }

    public function test_media_relationship_is_morph_to_many(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(MorphToMany::class, $user->media());
    }

    public function test_has_field_values_morph_many_from_fieldable_trait(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(MorphMany::class, $user->fieldValues());
    }

    // -------------------------------------------------------------------------
    // entryAuthor() relationship
    // -------------------------------------------------------------------------

    public function test_entry_author_relationship_is_has_one(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(HasOne::class, $user->entryAuthor());
    }

    public function test_entry_author_relationship_returns_related_entry_author(): void
    {
        $user = User::factory()->create();
        $ea = EntryAuthor::factory()->create(['user_id' => $user->id]);

        $this->assertNotNull($user->entryAuthor);
        $this->assertEquals($ea->id, $user->entryAuthor->id);
    }

    public function test_entry_author_relationship_returns_null_when_no_record_exists(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->entryAuthor);
    }

    // -------------------------------------------------------------------------
    // isAuthorEligible()
    // -------------------------------------------------------------------------

    public function test_is_author_eligible_returns_true_when_active_entry_author_exists(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->assertTrue($user->fresh()->isAuthorEligible());
    }

    public function test_is_author_eligible_returns_false_when_no_entry_author_record_exists(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAuthorEligible());
    }

    public function test_is_author_eligible_returns_false_when_status_is_pending(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->pending()->create(['user_id' => $user->id]);

        $this->assertFalse($user->fresh()->isAuthorEligible());
    }

    public function test_is_author_eligible_returns_false_when_status_is_disabled(): void
    {
        $user = User::factory()->create();
        EntryAuthor::factory()->disabled()->create(['user_id' => $user->id]);

        $this->assertFalse($user->fresh()->isAuthorEligible());
    }
}
