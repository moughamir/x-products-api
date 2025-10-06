<?php
// config/admin.php

return [
    // Database Configuration
    'admin_db_file' => __DIR__ . '/../data/sqlite/admin.sqlite',
    'products_db_file' => __DIR__ . '/../data/sqlite/products.sqlite',

    // Session Configuration
    'session' => [
        'name' => 'COSMOS_ADMIN_SESSION',
        'lifetime' => 3600 * 8, // 8 hours
        'path' => '/cosmos/admin',
        'domain' => '',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // Authentication Configuration
    'auth' => [
        'password_cost' => 12, // bcrypt cost factor
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes in seconds
        'remember_me_duration' => 3600 * 24 * 30, // 30 days
    ],

    // CSRF Configuration
    'csrf' => [
        'token_name' => 'csrf_token',
        'token_length' => 32,
        'token_lifetime' => 3600, // 1 hour
    ],

    // Upload Configuration
    'uploads' => [
        'max_file_size' => 10 * 1024 * 1024, // 10 MB
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'upload_dir' => __DIR__ . '/../data/uploads',
    ],

    // Pagination Configuration
    'pagination' => [
        'default_limit' => 50,
        'max_limit' => 100,
    ],

    // Activity Log Configuration
    'activity_log' => [
        'enabled' => true,
        'retention_days' => 90, // Keep logs for 90 days
    ],

    // UI Configuration
    'ui' => [
        'app_name' => 'Cosmos Admin',
        'items_per_page' => 50,
        'date_format' => 'Y-m-d H:i:s',
    ],
];

