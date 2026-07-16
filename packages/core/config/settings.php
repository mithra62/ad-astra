<?php

use AdAstra\Enums\UserStatus;
use AdAstra\Models\FieldLayout;
use Illuminate\Validation\Rule;

/**
 * Settings domain and field definitions.
 *
 * Each top-level key is a domain handle matching a row in the setting_domains
 * table. Fields within each domain describe the typed settings that belong to
 * that domain.
 *
 * Field keys:
 *   handle           – unique within the domain; used as the DB field_handle
 *   label            – human-readable label shown in the admin UI
 *   type             – storage type: text | integer | float | boolean | json
 *                      determines which value_* column is read/written
 *   default          – returned when no DB value exists for the field
 *   rules            – Laravel validation rules array, applied before save.
 *                      Do not include 'nullable' — it is prepended automatically
 *                      for any field that does not declare 'required'.
 *                      Boolean fields are normalised before save and skip validation.
 *                      Example: ['string', 'max:255'] or ['integer', 'min:1', 'max:500']
 *   instructions     – optional helper text shown beneath the label
 *   group            – optional section heading for grouping fields visually
 *   hidden           – when true the field is excluded from the admin UI
 *   user_overridable – when true authenticated users may set a personal override
 *
 * Adding a new setting: add an entry to the appropriate domain's 'fields' array.
 * No migration or seeder is required — the Settings service reads from this file.
 * Run the SettingsDomainSeeder if you want a system default value pre-seeded.
 */

return [

    // -------------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------------

    'general' => [
        'name' => 'General',
        'description' => 'Site-wide general configuration.',
        'icon' => 'ti-settings',
        'sort_order' => 0,
        'fields' => [
            [
                'handle' => 'site_name',
                'label' => 'Site Name',
                'type' => 'text',
                'default' => '',
                'rules' => ['required', 'string', 'max:255'],
                'instructions' => 'The public name of this site.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'installed_version',
                'label' => 'Installed Version',
                'type' => 'text',
                'default' => '0.0.1',
                'rules' => ['nullable', 'string', 'max:255'],
                'instructions' => 'The installed system version.',
                'group' => null,
                'hidden' => true,
                'user_overridable' => false,
            ],
            [
                'handle' => 'timezone',
                'label' => 'Timezone',
                'type' => 'text',
                'default' => 'UTC',
                'rules' => ['required', 'string', 'timezone'],
                'instructions' => 'Default timezone for date display.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => true,
                'options_callback' => static function () {
                    $grouped = [];
                    $general = [];

                    foreach (timezone_identifiers_list() as $tz) {
                        $slash = strpos($tz, '/');
                        if ($slash === false) {
                            $general[] = ['value' => $tz, 'label' => $tz];
                        } else {
                            $continent = substr($tz, 0, $slash);
                            $city = str_replace('_', ' ', substr($tz, $slash + 1));
                            $grouped[$continent][] = ['value' => $tz, 'label' => $city];
                        }
                    }

                    ksort($grouped);

                    $result = [];
                    if ($general) {
                        $result[] = ['label' => 'General', 'options' => $general];
                    }
                    foreach ($grouped as $continent => $options) {
                        $result[] = ['label' => $continent, 'options' => $options];
                    }

                    return $result;
                },
            ],
            [
                'handle' => 'date_format',
                'label' => 'Date Format',
                'type' => 'text',
                'default' => 'Y-m-d',
                'rules' => ['required', 'string', 'max:50'],
                'instructions' => 'PHP date format string (e.g. Y-m-d, d/m/Y).',
                'group' => null,
                'hidden' => false,
                'user_overridable' => true,
            ],
            [
                'handle' => 'time_format',
                'label' => 'Time Format',
                'type' => 'text',
                'default' => 'H:i',
                'rules' => ['required', 'string', 'max:50'],
                'instructions' => 'PHP time format string (e.g. H:i, g:i A).',
                'group' => null,
                'hidden' => false,
                'user_overridable' => true,
            ],
            [
                'handle' => 'items_per_page',
                'label' => 'Items Per Page',
                'type' => 'integer',
                'default' => 25,
                'rules' => ['required', 'integer', 'min:1', 'max:500'],
                'instructions' => 'Default number of items shown per page in admin listings.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => true,
            ],
            [
                // Stored as text (not 'select', which routes to value_integer for FK ids).
                // A non-empty 'options' set makes the form render it as a <select>.
                'handle' => 'appearance',
                'label' => 'Appearance',
                'type' => 'text',
                'default' => 'system',
                'rules' => ['string', Rule::in(['light', 'dark', 'system'])],
                'instructions' => 'Interface theme. "System" follows your operating system setting.',
                'group' => 'Appearance',
                'hidden' => false,
                'user_overridable' => true,
                'options_callback' => static fn () => [
                    ['value' => 'light', 'label' => 'Light'],
                    ['value' => 'dark', 'label' => 'Dark'],
                    ['value' => 'system', 'label' => 'System'],
                ],
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // Email
    // -------------------------------------------------------------------------

    'email' => [
        'name' => 'Email',
        'description' => 'Outbound email sender configuration.',
        'icon' => 'ti-mail',
        'sort_order' => 2,
        'fields' => [
            [
                'handle' => 'email_from_name',
                'label' => 'From Name',
                'type' => 'text',
                'default' => '',
                'rules' => ['string', 'max:255'],
                'instructions' => 'The sender name that appears in outbound emails.',
                'group' => 'Sender',
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'email_from_address',
                'label' => 'From Address',
                'type' => 'text',
                'default' => '',
                'rules' => ['email', 'max:255'],
                'instructions' => 'The sender email address for outbound emails.',
                'group' => 'Sender',
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'email_reply_to',
                'label' => 'Reply-To Address',
                'type' => 'text',
                'default' => '',
                'rules' => ['email', 'max:255'],
                'instructions' => 'Optional reply-to address. Leave blank to use the From Address.',
                'group' => 'Sender',
                'hidden' => false,
                'user_overridable' => false,
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // Content
    // -------------------------------------------------------------------------

    'content' => [
        'name' => 'Content',
        'description' => 'Default behaviours for the content publishing system.',
        'icon' => 'ti-file-text',
        'sort_order' => 3,
        'fields' => [
            [
                'handle' => 'entries_per_page',
                'label' => 'Entries Per Page',
                'type' => 'integer',
                'default' => 20,
                'rules' => ['required', 'integer', 'min:1', 'max:500'],
                'instructions' => 'Default number of entries shown per page on public listing pages.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'default_entry_status',
                'label' => 'Default Entry Status',
                'type' => 'text',
                'default' => 'draft',
                'rules' => ['required', 'string', 'max:100'],
                'instructions' => 'Status handle applied to new entries when none is specified.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => false,
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    'users' => [
        'name' => 'Users',
        'description' => 'User account and access configuration.',
        'icon' => 'ti-users',
        'sort_order' => 10,
        'fields' => [
            [
                'handle' => 'default_status',
                'label' => 'Default User Status',
                'type' => 'text',
                'default' => 'active',
                'rules' => ['required', Rule::in(UserStatus::ALL)],
                'instructions' => 'Status assigned to new user accounts created by an admin.',
                'group' => 'Accounts',
                'hidden' => false,
                'user_overridable' => false,
                'options_callback' => static fn () => array_map(
                    fn ($s) => ['value' => $s, 'label' => UserStatus::label($s)],
                    UserStatus::ALL
                ),
            ],
            [
                'handle' => 'social_default_status',
                'label' => 'Social Login Default Status',
                'type' => 'text',
                'default' => 'pending',
                'rules' => ['required', Rule::in(UserStatus::ALL)],
                'instructions' => 'Status assigned to accounts created via OAuth / social login. Set to "active" only if you fully trust your OAuth provider.',
                'group' => 'Accounts',
                'hidden' => false,
                'user_overridable' => false,
                'options_callback' => static fn () => array_map(
                    fn ($s) => ['value' => $s, 'label' => UserStatus::label($s)],
                    UserStatus::ALL
                ),
            ],
            [
                'handle' => 'user_field_layout_id',
                'label' => 'User Field Layout',
                'type' => 'select',
                'default' => null,
                'rules' => ['nullable', 'integer', 'exists:field_layouts,id'],
                'instructions' => 'The field layout applied to all user profiles. Cannot be deleted while assigned here.',
                'group' => 'Schema',
                'hidden' => false,
                'user_overridable' => false,
                'options_callback' => static fn () => FieldLayout::orderBy('name')
                    ->get()
                    ->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])
                    ->toArray(),
            ],
        ],
    ],

    // -------------------------------------------------------------------------
    // Security
    // -------------------------------------------------------------------------

    'security' => [
        'name' => 'Security',
        'description' => 'Security auditing and access-control configuration.',
        'icon' => 'ti-shield-lock',
        'sort_order' => 11,
        'fields' => [
            [
                'handle' => 'gate_bypass_log_enabled',
                'label' => 'Log Super Admin Gate Bypasses',
                'type' => 'boolean',
                'default' => true,
                'rules' => [],
                'instructions' => 'Record an audit entry whenever the super admin role bypasses an authorization check.',
                'group' => 'Audit',
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'gate_bypass_log_include_reads',
                'label' => 'Include Read Requests',
                'type' => 'boolean',
                'default' => false,
                'rules' => [],
                'instructions' => 'Also log bypasses on GET/HEAD/OPTIONS requests. Off by default — read-request bypasses are mostly passive UI checks (navigation visibility, button rendering).',
                'group' => 'Audit',
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'gate_bypass_log_retention_days',
                'label' => 'Gate Bypass Log Retention (Days)',
                'type' => 'integer',
                'default' => 365,
                'rules' => ['required', 'integer', 'min:0', 'max:3650'],
                'instructions' => 'Days to keep gate bypass audit entries. Set to 0 to keep them forever.',
                'group' => 'Audit',
                'hidden' => false,
                'user_overridable' => false,
            ],
        ],
    ],

];
