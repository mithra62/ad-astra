<?php

/**
 * Tunables for the `adastra:doctor` health-report command. Check *logic*
 * never lives here — publish this file (php artisan vendor:publish
 * --tag=adastra-config) to adjust what a healthy install looks like, e.g.
 * a stripped-down site can drop tables from the required list instead of
 * living with a permanent failure.
 */

return [

    // Tables whose absence means the install is broken. Core application
    // tables only — framework infrastructure (cache, jobs, sessions) is
    // deliberately excluded.
    'required_tables' => [
        'users',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'setting_domains',
        'setting_values',
        'entry_behaviors',
        'entry_groups',
        'entry_types',
        'entries',
        'entry_trees',
        'fields',
        'field_types',
        'field_values',
        'field_groups',
        'field_layouts',
        'field_layout_tabs',
        'field_layout_tab_elements',
        'status_groups',
        'statuses',
        'media',
        'media_libraries',
        'mediables',
        'media_transformations',
        'categories',
        'category_groups',
    ],

    // Check ids to exclude from every run (for slow or opt-in checks).
    'disabled' => [],

];
