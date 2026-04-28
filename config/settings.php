<?php

/**
 * Settings domain and field definitions.
 *
 * Each top-level key is a domain handle matching a row in the setting_domains
 * table. Fields within each domain describe the typed settings that belong to
 * that domain.
 *
 * Field keys:
 *   handle          – unique within the domain; used as the DB field_handle
 *   label           – human-readable label shown in the admin UI
 *   type            – storage type: text | integer | float | boolean | json
 *                     determines which value_* column is read/written
 *   default         – returned when no DB value exists for the field
 *   instructions    – optional helper text shown beneath the label
 *   group           – optional section heading for grouping fields visually
 *   hidden          – when true the field is excluded from the admin UI
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
                'instructions' => 'The public name of this site.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => false,
            ],
            [
                'handle' => 'timezone',
                'label' => 'Timezone',
                'type' => 'text',
                'default' => 'UTC',
                'instructions' => 'Default timezone for date display (e.g. UTC, America/New_York).',
                'group' => null,
                'hidden' => false,
                'user_overridable' => true,
            ],
            [
                'handle' => 'date_format',
                'label' => 'Date Format',
                'type' => 'text',
                'default' => 'Y-m-d',
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
                'instructions' => 'Status handle applied to new entries when none is specified.',
                'group' => null,
                'hidden' => false,
                'user_overridable' => false,
            ],
        ],
    ],
];
