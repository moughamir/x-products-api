#!/usr/bin/env php
<?php
/**
 * Admin Database Migration - API Keys and Settings Tables
 *
 * Adds tables for:
 * - API key management (if not exists in admin.sqlite)
 * - Application settings
 *
 * Usage:
 *   php migrations/003_add_api_keys_and_settings.php
 *   php migrations/003_add_api_keys_and_settings.php --force  # Drop existing tables
 */

require __DIR__ . '/../vendor/autoload.php';

// Parse command-line arguments
$force = in_array('--force', $argv);

// Load configuration
$adminConfig = require __DIR__ . '/../config/admin.php';
$dbPath = $adminConfig['admin_db_file'];

echo "\n========================================\n";
echo "Admin Database Migration - API Keys & Settings\n";
echo "========================================\n";
echo "Database: {$dbPath}\n";
echo "Force mode: " . ($force ? 'YES' : 'NO') . "\n";
echo "========================================\n\n";

// Check if database exists
if (!file_exists($dbPath)) {
    echo "✗ ERROR: Admin database not found!\n";
    echo "Please run 'php migrations/001_create_admin_database.php' first.\n\n";
    exit(1);
}

try {
    // Connect to database
    echo "→ Connecting to database...\n";
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if tables already exist
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='api_keys'");
    $apiKeysExists = $result->fetch() !== false;

    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
    $settingsExists = $result->fetch() !== false;

    if (($apiKeysExists || $settingsExists) && !$force) {
        echo "⚠️  Tables already exist!\n";
        echo "Use --force flag to drop and recreate tables.\n";
        echo "WARNING: This will delete all API keys and settings!\n\n";
        exit(1);
    }

    // Drop existing tables if force mode
    if ($force) {
        echo "→ Dropping existing tables (--force mode)...\n";
        $db->exec("DROP TABLE IF EXISTS api_keys");
        $db->exec("DROP TABLE IF EXISTS settings");
    }

    // Create api_keys table
    echo "→ Creating api_keys table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            key_hash VARCHAR(255) UNIQUE NOT NULL,
            key_prefix VARCHAR(10) NOT NULL,
            permissions TEXT,
            rate_limit INTEGER DEFAULT 60,
            expires_at DATETIME,
            last_used_at DATETIME,
            total_requests INTEGER DEFAULT 0,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");

    // Create settings table
    echo "→ Creating settings table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(100) PRIMARY KEY,
            value TEXT,
            type VARCHAR(20) DEFAULT 'string',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create indexes
    echo "→ Creating indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_hash ON api_keys(key_hash)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_prefix ON api_keys(key_prefix)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_created_by ON api_keys(created_by)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_expires ON api_keys(expires_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key)");

    // Insert default settings
    echo "→ Inserting default settings...\n";
    $defaultSettings = [
        ['app_name', 'Cosmos Admin', 'string'],
        ['app_description', 'Product Management System', 'string'],
        ['timezone', 'UTC', 'string'],
        ['date_format', 'Y-m-d H:i:s', 'string'],
        ['default_currency', 'USD', 'string'],
        ['items_per_page', '50', 'int'],
        ['max_image_size', '10485760', 'int'],
        ['allowed_image_types', 'jpg,jpeg,png,gif,webp', 'string'],
        ['smtp_host', '', 'string'],
        ['smtp_port', '587', 'int'],
        ['smtp_username', '', 'string'],
        ['smtp_password', '', 'string'],
        ['smtp_encryption', 'tls', 'string'],
        ['from_email', '', 'string'],
        ['from_name', 'Cosmos Admin', 'string'],
    ];

    $stmt = $db->prepare("
        INSERT OR IGNORE INTO settings (key, value, type, updated_at)
        VALUES (:key, :value, :type, CURRENT_TIMESTAMP)
    ");

    $settingsCount = 0;
    foreach ($defaultSettings as $setting) {
        $stmt->execute([
            'key' => $setting[0],
            'value' => $setting[1],
            'type' => $setting[2],
        ]);
        if ($stmt->rowCount() > 0) {
            $settingsCount++;
        }
    }

    echo "  ✓ Inserted {$settingsCount} default settings\n";

    // Verify tables were created
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $expectedTables = ['api_keys', 'settings'];
    $missingTables = array_diff($expectedTables, $tables);

    if (!empty($missingTables)) {
        throw new PDOException("Migration incomplete: Missing tables: " . implode(', ', $missingTables));
    }

    echo "\n========================================\n";
    echo "✓ MIGRATION SUCCESSFUL!\n";
    echo "========================================\n";
    echo "Migration: 003_add_api_keys_and_settings\n";
    echo "Status: COMPLETE\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n\n";

    echo "Tables Created:\n";
    echo "  - api_keys (for API key management)\n";
    echo "  - settings ({$settingsCount} default settings)\n\n";

    echo "Indexes Created:\n";
    echo "  - 5 indexes for performance optimization\n\n";

    echo "Next Steps:\n";
    echo "  1. Access admin dashboard to manage API keys\n";
    echo "  2. Configure application settings\n";
    echo "  3. Generate API keys for external access\n\n";

    echo "========================================\n";
    echo "✓ Migration 003: COMPLETE\n";
    echo "========================================\n\n";

} catch (PDOException $e) {
    echo "\n========================================\n";
    echo "✗ MIGRATION FAILED!\n";
    echo "========================================\n";
    echo "Migration: 003_add_api_keys_and_settings\n";
    echo "Status: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "========================================\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

exit(0);

