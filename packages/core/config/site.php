<?php
return [
    'routing' => [
        'priority' => [
            'entry_tree',
            'template',
        ],
    ],
    'templates' => [
        'base_path' => 'site',
        'default_template' => 'templates::site.index',
        'not_found_template' => 'errors.404',
    ],
];
