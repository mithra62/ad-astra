<?php

namespace Database\Seeders;

use App\Models\User;
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
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('super admin');

        $user = User::factory()->create([
            'name' => 'Ben Fjare',
            'email' => 'ben@checkoffpro.com',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('super admin');

        $user = User::factory()->create([
            'name' => 'Easton Kuboushek',
            'email' => 'easton@checkoffpro.com',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('super admin');
    }
}
