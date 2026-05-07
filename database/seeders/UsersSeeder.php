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
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Eric Lamb',
            'email' => 'eric@mithra62.com',
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('super admin');

        // Promote to active author so this account appears in entry author pickers
        // and can be used as the default seeded author in EntrySeeder.
        app(EntryAuthorService::class)->promote($user);
    }
}
