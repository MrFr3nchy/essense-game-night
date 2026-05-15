<?php

return [
    'essense' => [
        'base_url' => env('ESSENSE_API_URL', 'https://codechallenge.essensedesigns.info'),
        'api_key' => env('ESSENSE_API_KEY'),
    ],
    'steam' => [
        'base_url' => env('STEAM_API_URL', 'https://store.steampowered.com/api'),
    ],
    'app' => [
        'allow_unlimited_actions' => env('ALLOW_UNLIMITED_ACTIONS', false),
    ],
];
