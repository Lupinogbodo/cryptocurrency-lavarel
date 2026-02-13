<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Cryptocurrency Trading API',
            ],
            'routes' => [
                'api' => 'api.php',
            ],
            'paths' => [
                'annotations' => base_path('app/Http/Controllers'),
            ],
        ],
    ],
    'defaults' => [
        'route_prefix' => '/api-docs',
        'use_gate' => false,
        'middleware' => [],
        'group_prefix' => '',
    ],
    'operations' => [
        'trim_slashes' => true,
    ],
];
