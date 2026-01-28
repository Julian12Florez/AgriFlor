<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://agriflor.pages.dev',
        'http://localhost:5173',
        'http://localhost:3000',
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.agriflor\.pages\.dev$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
