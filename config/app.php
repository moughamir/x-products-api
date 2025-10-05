<?php
// config/app.php

return [
    // Your actual API key used by the ApiKeyMiddleware
    'api_key' => '0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539',

    // ImageProxy configuration
    'image_proxy' => [
        'base_url' => 'https://cdn.shopify.com',
        'cache_enabled' => false,  // Set to true to enable caching if implemented
        'cache_ttl' => 86400      // Cache time-to-live in seconds (24 hours)
    ],

    // Allowed domains for image proxy (security measure)
    'allowed_domains' => ['cdn.shopify.com'],

    // API configuration
    'pagination' => [
        'default_limit' => 50,
        'max_limit' => 100
    ],

    // Response format options
    'response_formats' => [
        'default' => 'json',
        'available' => ['json', 'msgpack']
    ]
];
