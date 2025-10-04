<?php
// config/app.php

return [
    // --- Security Configuration ---
    // !! IMPORTANT: REPLACE THIS WITH YOUR ACTUAL, PRIVATE API KEY
    'api_key' => '0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539',

    // --- Image Proxy Configuration ---
    'image_proxy' => [
        // This is the external source URL, which the proxy fetches from
        'base_url' => 'https://cdn.shopify.com',
    ],
    // Configuration for the Image Proxy Service (Used by ImageProxy.php)
    'image_cache_dir' => __DIR__ . '/../data/cache/images',
    'cache_hours' => 24, // 24 hours cache TTL
    'max_file_size' => 10485760, // 10MB
    'allowed_domains' => ['cdn.shopify.com'],
];
