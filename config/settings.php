<?php

use App\Enums\UserStatus;
use App\Models\FieldLayout;
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
                'default' => '0.1',
                'rules' => ['required', 'string', 'max:255'],
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
        ],
    ],

    // -------------------------------------------------------------------------
    // Media
    // -------------------------------------------------------------------------

    'media' => [
        'name' => 'Media',
        'description' => 'File upload and media library configuration.',
        'icon' => 'ti-photo',
        'sort_order' => 1,
        'fields' => [
            [
                'handle' => 'max_upload_size',
                'label' => 'Max Upload Size (KB)',
                'type' => 'integer',
                'default' => 10240,
                'rules' => ['required', 'integer', 'min:1'],
                'instructions' => 'Maximum file upload size in kilobytes.',
                'group' => 'Uploads',
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'allowed_extensions',
                'label' => 'Allowed File Extensions',
                'type' => 'text',
                'default' => 'jpg,jpeg,png,gif,webp,pdf,mp3,mp4,mov',
                'rules' => ['required', 'string', 'max:500'],
                'instructions' => 'Comma-separated list of permitted extensions (e.g. jpg,png,pdf).',
                'group' => 'Uploads',
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'image_quality',
                'label' => 'Image Quality (1–100)',
                'type' => 'integer',
                'default' => 85,
                'rules' => ['required', 'integer', 'min:1', 'max:100'],
                'instructions' => 'JPEG/WebP compression quality for processed images.',
                'group' => 'Processing',
                'hidden' => false,
                'user_overridable' => false,
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
                'options_callback' => static fn() => array_map(
                    fn($s) => ['value' => $s, 'label' => UserStatus::label($s)],
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
                'options_callback' => static fn() => array_map(
                    fn($s) => ['value' => $s, 'label' => UserStatus::label($s)],
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
                'options_callback' => static fn() => FieldLayout::orderBy('name')
                    ->get()
                    ->map(fn($l) => ['value' => $l->id, 'label' => $l->name])
                    ->toArray(),
            ],
        ],
    ],

];
