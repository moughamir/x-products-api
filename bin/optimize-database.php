#!/usr/bin/env php
<?php
/**
 * Database Optimization Script
 *
 * This script optimizes the SQLite databases by:
 * - Creating missing indexes for better query performance
 * - Running VACUUM to reclaim unused space
 * - Running ANALYZE to update query planner statistics
 * - Enabling WAL mode for better concurrency
 * - Optimizing FTS5 tables
 *
 * Usage:
 *   php bin/optimize-database.php              # Optimize all databases
 *   php bin/optimize-database.php --products   # Optimize products database only
 *   php bin/optimize-database.php --admin      # Optimize admin database only
 *   php bin/optimize-database.php --force      # Skip confirmation
 */

require __DIR__ . '/../vendor/autoload.php';

// Parse command-line arguments
$options = [
    'products' => in_array('--products', $argv),
    'admin' => in_array('--admin', $argv),
    'force' => in_array('--force', $argv),
    'help' => in_array('--help', $argv) || in_array('-h', $argv),
];

// Show help
if ($options['help']) {
    echo <<<HELP

Database Optimization Script

Usage:
  php bin/optimize-database.php [OPTIONS]

Options:
  --products         Optimize products database only
  --admin            Optimize admin database only
  --force            Skip confirmation prompts
  --help, -h         Show this help message

Examples:
  php bin/optimize-database.php                # Optimize all databases
  php bin/optimize-database.php --products     # Optimize products database only
  php bin/optimize-database.php --force        # Skip confirmation

HELP;
    exit(0);
}

// Load configuration
$dbConfig = require __DIR__ . '/../config/database.php';
$adminConfig = require __DIR__ . '/../config/admin.php';

// Determine which databases to optimize
$optimizeProducts = !$options['admin'] || $options['products'];
$optimizeAdmin = !$options['products'] || $options['admin'];

echo "\n========================================\n";
echo "Database Optimization Tool\n";
echo "========================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

/**
 * Optimize a database
 */
function optimizeDatabase(string $dbPath, string $dbName, array $indexes = []): void
{
    echo "→ Optimizing {$dbName} database...\n";
    echo "  Database: {$dbPath}\n";
    
    if (!file_exists($dbPath)) {
        echo "  ⚠️  Database not found, skipping.\n\n";
        return;
    }
    
    try {
        $db = new PDO("sqlite:" . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. Enable WAL mode for better concurrency
        echo "  → Enabling WAL mode...\n";
        $result = $db->query("PRAGMA journal_mode=WAL")->fetchColumn();
        echo "    ✓ Journal mode: {$result}\n";
        
        // 2. Create missing indexes
        if (!empty($indexes)) {
            echo "  → Creating/verifying indexes...\n";
            foreach ($indexes as $indexName => $indexSql) {
                try {
                    $db->exec($indexSql);
                    echo "    ✓ {$indexName}\n";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        echo "    ✓ {$indexName} (already exists)\n";
                    } else {
                        echo "    ✗ {$indexName}: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        // 3. Optimize FTS tables if they exist
        echo "  → Optimizing FTS tables...\n";
        $ftsTables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%_fts'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ftsTables as $ftsTable) {
            try {
                $db->exec("INSERT INTO {$ftsTable}({$ftsTable}) VALUES('optimize')");
                echo "    ✓ Optimized {$ftsTable}\n";
            } catch (PDOException $e) {
                echo "    ⚠️  Could not optimize {$ftsTable}: " . $e->getMessage() . "\n";
            }
        }
        
        // 4. Run ANALYZE to update statistics
        echo "  → Running ANALYZE...\n";
        $db->exec("ANALYZE");
        echo "    ✓ Statistics updated\n";
        
        // 5. Run VACUUM to reclaim space
        echo "  → Running VACUUM...\n";
        $sizeBefore = filesize($dbPath);
        $db->exec("VACUUM");
        $sizeAfter = filesize($dbPath);
        $saved = $sizeBefore - $sizeAfter;
        $savedMB = round($saved / 1024 / 1024, 2);
        echo "    ✓ Space reclaimed: {$savedMB} MB\n";
        
        // 6. Show database statistics
        echo "  → Database statistics:\n";
        $pageCount = $db->query("PRAGMA page_count")->fetchColumn();
        $pageSize = $db->query("PRAGMA page_size")->fetchColumn();
        $dbSize = round(($pageCount * $pageSize) / 1024 / 1024, 2);
        echo "    Database size: {$dbSize} MB\n";
        echo "    Page count: {$pageCount}\n";
        echo "    Page size: {$pageSize} bytes\n";
        
        echo "  ✓ {$dbName} optimization complete!\n\n";
        
    } catch (PDOException $e) {
        echo "  ✗ Error optimizing {$dbName}: " . $e->getMessage() . "\n\n";
    }
}

// Products database indexes
$productsIndexes = [
    'idx_products_handle' => 'CREATE INDEX IF NOT EXISTS idx_products_handle ON products(handle)',
    'idx_products_vendor' => 'CREATE INDEX IF NOT EXISTS idx_products_vendor ON products(vendor)',
    'idx_products_product_type' => 'CREATE INDEX IF NOT EXISTS idx_products_product_type ON products(product_type)',
    'idx_products_price' => 'CREATE INDEX IF NOT EXISTS idx_products_price ON products(price)',
    'idx_products_in_stock' => 'CREATE INDEX IF NOT EXISTS idx_products_in_stock ON products(in_stock)',
    'idx_products_rating' => 'CREATE INDEX IF NOT EXISTS idx_products_rating ON products(rating)',
    'idx_products_bestseller' => 'CREATE INDEX IF NOT EXISTS idx_products_bestseller ON products(bestseller_score)',
    'idx_products_created_at' => 'CREATE INDEX IF NOT EXISTS idx_products_created_at ON products(created_at)',
    'idx_product_images_product_id' => 'CREATE INDEX IF NOT EXISTS idx_product_images_product_id ON product_images(product_id)',
    'idx_product_images_position' => 'CREATE INDEX IF NOT EXISTS idx_product_images_position ON product_images(product_id, position)',
    'idx_collections_handle' => 'CREATE INDEX IF NOT EXISTS idx_collections_handle ON collections(handle)',
    'idx_collections_featured' => 'CREATE INDEX IF NOT EXISTS idx_collections_featured ON collections(is_featured)',
    'idx_product_collections_collection' => 'CREATE INDEX IF NOT EXISTS idx_product_collections_collection ON product_collections(collection_id)',
    'idx_product_collections_product' => 'CREATE INDEX IF NOT EXISTS idx_product_collections_product ON product_collections(product_id)',
    'idx_categories_slug' => 'CREATE INDEX IF NOT EXISTS idx_categories_slug ON categories(slug)',
    'idx_categories_parent' => 'CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id)',
    'idx_product_categories_category' => 'CREATE INDEX IF NOT EXISTS idx_product_categories_category ON product_categories(category_id)',
    'idx_product_categories_product' => 'CREATE INDEX IF NOT EXISTS idx_product_categories_product ON product_categories(product_id)',
    'idx_tags_slug' => 'CREATE INDEX IF NOT EXISTS idx_tags_slug ON tags(slug)',
    'idx_tags_name' => 'CREATE INDEX IF NOT EXISTS idx_tags_name ON tags(name)',
    'idx_product_tags_tag' => 'CREATE INDEX IF NOT EXISTS idx_product_tags_tag ON product_tags(tag_id)',
    'idx_product_tags_product' => 'CREATE INDEX IF NOT EXISTS idx_product_tags_product ON product_tags(product_id)',
];

// Admin database indexes
$adminIndexes = [
    'idx_admin_users_email' => 'CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email)',
    'idx_admin_users_username' => 'CREATE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username)',
    'idx_admin_sessions_user_id' => 'CREATE INDEX IF NOT EXISTS idx_admin_sessions_user_id ON admin_sessions(user_id)',
    'idx_admin_sessions_expires_at' => 'CREATE INDEX IF NOT EXISTS idx_admin_sessions_expires_at ON admin_sessions(expires_at)',
    'idx_admin_activity_log_user_id' => 'CREATE INDEX IF NOT EXISTS idx_admin_activity_log_user_id ON admin_activity_log(user_id)',
    'idx_admin_activity_log_created_at' => 'CREATE INDEX IF NOT EXISTS idx_admin_activity_log_created_at ON admin_activity_log(created_at)',
    'idx_api_keys_key_hash' => 'CREATE INDEX IF NOT EXISTS idx_api_keys_key_hash ON api_keys(key_hash)',
    'idx_api_keys_prefix' => 'CREATE INDEX IF NOT EXISTS idx_api_keys_prefix ON api_keys(key_prefix)',
];

// Optimize databases
if ($optimizeProducts) {
    optimizeDatabase($dbConfig['db_file'], 'Products', $productsIndexes);
}

if ($optimizeAdmin) {
    optimizeDatabase($adminConfig['admin_db_file'], 'Admin', $adminIndexes);
}

echo "========================================\n";
echo "✓ Database Optimization Complete!\n";
echo "========================================\n";
echo "Recommendations:\n";
echo "  - Run this script monthly for best performance\n";
echo "  - Monitor database size growth\n";
echo "  - Consider archiving old data if databases grow too large\n";
echo "========================================\n\n";

exit(0);

