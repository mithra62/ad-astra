<?php

namespace AdAstra\Doctor\Checks\Security;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Models\User;

class DevAccountCheck extends AbstractDoctorCheck
{
    protected string $id = 'security.dev-account-in-production';
    protected string $name = 'Development account in production';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        if (!app()->environment('production')) {
            yield $this->pass('Dev account check not applicable outside production');

            return;
        }

        // UsersSeeder skips in production, but a DB seeded locally and then
        // promoted keeps the DEV_USER_* super admin. When the env var is
        // unset the config default is a random string, which matches nobody.
        $email = (string) config('app.default_dev_email');

        // Presence only — never print the address itself.
        if ($email !== '' && User::where('email', $email)->exists()) {
            yield $this->warn(
                'A development seeder account exists in this production database',
                details: 'A user matching DEV_USER_EMAIL is present',
                fixCommand: 'remove the account or rotate its credentials, then unset DEV_USER_* in .env',
            );

            return;
        }

        yield $this->pass('No development seeder account present');
    }
}
