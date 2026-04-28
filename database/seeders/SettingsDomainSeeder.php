<?php

namespace Database\Seeders;

use App\Models\SettingDomain;
use App\Settings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the setting_domains rows and their system-level default values.
 *
 * Field *definitions* live in config/settings.php — this seeder only creates
 * the domain registry rows and pre-populates system values so the app works
 * out of the box without an admin saving each settings form manually.
 *
 * Safe to re-run: firstOrCreate on domains, set() is updateOrCreate under the hood.
 */
class SettingsDomainSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $settings = app(Settings::class);

        foreach (config('settings', []) as $handle => $domainConfig) {
            SettingDomain::firstOrCreate(
                ['handle' => $handle],
                [
                    'name'        => $domainConfig['name'],
                    'description' => $domainConfig['description'] ?? null,
                    'icon'        => $domainConfig['icon'] ?? null,
                    'sort_order'  => $domainConfig['sort_order'] ?? 0,
                ]
            );

            // Seed a system default value for every field that declares one.
            // Uses Settings::set() so the correct typed column is written.
            foreach ($domainConfig['fields'] ?? [] as $field) {
                if (array_key_exists('default', $field) && $field['default'] !== null) {
                    $settings->set($handle, $field['handle'], $field['default'], user: null);
                }
            }
        }
    }
}
