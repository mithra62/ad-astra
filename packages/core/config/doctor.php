<?php

/**
 * Tunables for the `adastra:doctor` health-report command. Check *logic*
 * never lives here — publish this file (php artisan vendor:publish
 * --tag=adastra-config) to adjust what a healthy install looks like, e.g.
 * a stripped-down site can drop tables from the required list instead of
 * living with a permanent failure.
 */

return [

    // Tables whose absence means the install is broken. The inclusion test
    // is "does an AdAstra feature break without it", not who ships the
    // migration — hence personal_access_tokens (API auth) and sessions
    // (web login with the database session driver). Pure framework
    // plumbing (cache, jobs) stays excluded.
    'required_tables' => [
        'users',
        'personal_access_tokens',
        'sessions',
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
    'disabled' => [
        // The template layer is in flux; re-enable once template
        // names/locations settle.
        'templates.entry-templates',
    ],

    // Override where assets.vite-manifest looks for the build manifest.
    // Defaults to public_path('build/manifest.json') when null.
    'vite_manifest_path' => null,

    // Framework fallback templates that should exist even when nothing in
    // the DB references them (templates.entry-templates warns when missing).
    // entries.show is EntryTreeRouteDriver's hard-coded last resort.
    'required_templates' => [
        'entries.show',
    ],

];
