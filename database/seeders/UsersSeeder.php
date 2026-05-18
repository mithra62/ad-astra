<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\EntryAuthorService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('UsersSeeder: skipped in production. Create the admin user manually.');
            return;
        }

        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => config('app.default_dev_name'),
            'email' => config('app.default_dev_email'),
            'status' => 'active',
            'password' => Hash::make(config('app.default_dev_password')),
        ]);

        $user->assignRole('super admin');

        // Promote to active author so this account appears in entry author pickers
        // and can be used as the default seeded author in EntrySeeder.
        app(EntryAuthorService::class)->promote($user);
    }
}
