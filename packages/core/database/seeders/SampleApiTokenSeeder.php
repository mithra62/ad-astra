<?php

namespace Database\Seeders;

use AdAstra\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Creates (or re-creates) a Sanctum personal access token for the seeded
 * super-admin and prints the plain-text value so developers can immediately
 * probe the API without logging in or visiting the admin UI.
 *
 * Always revokes the previous dev token before issuing a new one so re-running
 * `db:seed --class=SampleApiTokenSeeder` stays idempotent.
 *
 * LOCAL / TESTING ONLY — never run in production.
 */
class SampleApiTokenSeeder extends Seeder
{
    use WithoutModelEvents;

    private const TOKEN_NAME = 'dev-api-token';

    public function run(): void
    {
        $admin = User::where('email', config('app.default_dev_email'))->first();

        if (! $admin) {
            $this->command->warn('SampleApiTokenSeeder: admin user not found — run UsersSeeder first.');
            return;
        }

        // Revoke any existing dev token so we always print a fresh value.
        $admin->tokens()->where('name', self::TOKEN_NAME)->delete();

        $newToken = $admin->createToken(self::TOKEN_NAME, ['*']);

        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Dev API token for: ' . $admin->email);
        $this->command->info('  ' . $newToken->plainTextToken);
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->newLine();
    }
}
