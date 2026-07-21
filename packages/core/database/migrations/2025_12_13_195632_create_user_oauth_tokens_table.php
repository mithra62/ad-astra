<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_oauth_tokens', function (Blueprint $table) {
            $table->id();

            // Fortify user
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Provider identification (linkedin, github, facebook, google, twitter, stripe, mollie, amazon_pay, etc.)
            $table->string('provider', 64);

            // Optional: provider environment/tenant (useful for stripe connect, multi-tenant OIDC, etc.)
            // Examples: "live", "test", "workspace-123", "tenant-abc"
            $table->string('provider_account', 191)->nullable();

            // Provider's user/account id (OAuth "resource owner" id; for Stripe it might be acct_*)
            $table->string('provider_user_id', 191)->nullable();

            // OpenID Connect
            $table->string('issuer', 255)->nullable();     // OIDC iss
            $table->string('subject', 255)->nullable();    // OIDC sub (often stable per issuer/client)
            $table->text('id_token')->nullable();          // OIDC id_token (JWT)

            // OAuth tokens
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('token_type', 20)->nullable();  // Bearer, etc.

            // Expiry
            $table->timestamp('expires_at')->nullable();

            // Scopes + extra response data
            $table->json('scopes')->nullable();            // store as array: ["openid","profile","email",...]
            $table->json('meta')->nullable();              // raw/normalized provider response, profile bits, etc.

            // Status / housekeeping
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            /**
             * Uniqueness strategy:
             * - Supports multiple identities per provider per user (e.g., multiple Stripe accounts, multiple Google accounts).
             * - OIDC: issuer+subject is the canonical identity when available.
             *
             * Note: NULL handling differs between DBs; this works well in MySQL/MariaDB.
             * If you’re on Postgres, consider partial unique indexes in a follow-up migration.
             */
            $table->unique(
                ['user_id', 'provider', 'provider_account', 'provider_user_id'],
                'uot_user_provider_account_provideruid_unique'
            );

            $table->unique(
                ['user_id', 'provider', 'issuer', 'subject'],
                'uot_user_provider_issuer_subject_unique'
            );

            // Helpful indexes
            $table->index(['provider', 'provider_user_id'], 'uot_provider_provideruid_idx');
            $table->index(['provider', 'provider_account'], 'uot_provider_account_idx');
            $table->index(['issuer', 'subject'], 'uot_issuer_subject_idx');
            $table->index(['user_id', 'provider'], 'uot_user_provider_idx');
            $table->index('expires_at', 'uot_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oauth_tokens');
    }
};
