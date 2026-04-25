<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Core app data — always runs
        $this->call([
            RolesPermissionsSeeder::class,
            UsersSeeder::class,

            // Field type registry — must run before any fields are created
            FieldTypeSeeder::class,

            // Independent schema primitives
            StatusGroupSeeder::class,
            CategoryGroupSeeder::class,

            // Fields and field groups — depends on FieldTypeSeeder
            FieldGroupSeeder::class,

            // Entry groups, layouts, and entry types — depends on all of the above
            EntryGroupSeeder::class,
            ExtendedEntryGroupSeeder::class,

            // User extended profile schema
            UserSchemaSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                EntrySeeder::class,
                FakeDataSeeder::class,
            ]);
        }
    }
}
