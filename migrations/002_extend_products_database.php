#!/usr/bin/env php
<?php
/**
 * Products Database Migration - Extensions for Admin Features
 *
 * Extends the existing products.sqlite database with tables for:
 * - Collections (manual and smart)
 * - Categories (hierarchical)
 * - Tags (normalized)
 * - Junction tables for relationships
 *
 * Usage:
 *   php migrations/002_extend_products_database.php
 *   php migrations/002_extend_products_database.php --force  # Drop existing tables
 */

require __DIR__ . '/../vendor/autoload.php';

// Parse command-line arguments
$force = in_array('--force', $argv);

// Load configuration
$dbConfig = require __DIR__ . '/../config/database.php';
$dbPath = $dbConfig['db_file'];

echo "\n========================================\n";
echo "Products Database Migration - Extensions\n";
echo "========================================\n";
echo "Database: {$dbPath}\n";
echo "Force mode: " . ($force ? 'YES' : 'NO') . "\n";
echo "========================================\n\n";

// Check if database exists
if (!file_exists($dbPath)) {
    echo "✗ ERROR: Products database not found!\n";
    echo "Please run 'composer db:setup' first to create the products database.\n\n";
    exit(1);
}

try {
    // Connect to database
    echo "→ Connecting to database...\n";
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if extensions already exist
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='collections'");
    $tablesExist = $result->fetch() !== false;

    if ($tablesExist && !$force) {
        echo "⚠️  Extension tables already exist!\n";
        echo "Use --force flag to drop and recreate all extension tables.\n";
        echo "WARNING: This will delete all collections, categories, and tag relationships!\n\n";
        exit(1);
    }

    // Drop existing tables if force mode
    if ($force) {
        echo "→ Dropping existing extension tables (--force mode)...\n";
        $db->exec("DROP TABLE IF EXISTS product_tags");
        $db->exec("DROP TABLE IF EXISTS tags");
        $db->exec("DROP TABLE IF EXISTS product_categories");
        $db->exec("DROP TABLE IF EXISTS categories");
        $db->exec("DROP TABLE IF EXISTS product_collections");
        $db->exec("DROP TABLE IF EXISTS collections");
    }

    // Create collections table
    echo "→ Creating collections table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS collections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            handle VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            image_url TEXT,
            is_smart BOOLEAN DEFAULT 0,
            rules TEXT,
            sort_order VARCHAR(50) DEFAULT 'manual',
            is_featured BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create product_collections junction table
    echo "→ Creating product_collections table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_collections (
            product_id INTEGER NOT NULL,
            collection_id INTEGER NOT NULL,
            position INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id, collection_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
        )
    ");

    // Create categories table
    echo "→ Creating categories table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            parent_id INTEGER,
            image_url TEXT,
            position INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
        )
    ");

    // Create product_categories junction table
    echo "→ Creating product_categories table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_categories (
            product_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id, category_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        )
    ");

    // Create tags table
    echo "→ Creating tags table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) UNIQUE NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create product_tags junction table
    echo "→ Creating product_tags table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_tags (
            product_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id, tag_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        )
    ");

    // Create indexes
    echo "→ Creating indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collections_handle ON collections(handle)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collections_featured ON collections(is_featured)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collections_is_smart ON collections(is_smart)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_product_collections_collection ON product_collections(collection_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_product_collections_position ON product_collections(position)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_slug ON categories(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_position ON categories(position)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_product_categories_category ON product_categories(category_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tags_slug ON tags(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tags_name ON tags(name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_product_tags_tag ON product_tags(tag_id)");

    // Migrate existing tags from products table to tags table
    echo "→ Migrating existing tags from products table...\n";

    // Start transaction for better performance
    $db->beginTransaction();

    // First, collect all unique tags
    $result = $db->query("SELECT DISTINCT tags FROM products WHERE tags IS NOT NULL AND tags != ''");
    $allTags = [];

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $tagsString = $row['tags'];
        $tags = array_map('trim', explode(',', $tagsString));
        foreach ($tags as $tagName) {
            if (!empty($tagName)) {
                $allTags[$tagName] = true;
            }
        }
    }

    // Insert all unique tags in batch
    $insertTagStmt = $db->prepare("INSERT OR IGNORE INTO tags (name, slug) VALUES (?, ?)");
    $tagCount = 0;

    foreach (array_keys($allTags) as $tagName) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tagName));
        $slug = trim($slug, '-');
        $insertTagStmt->execute([$tagName, $slug]);
        if ($insertTagStmt->rowCount() > 0) {
            $tagCount++;
        }
    }

    echo "  ✓ Created {$tagCount} unique tags\n";

    // Commit tag inserts
    $db->commit();

    // Now link products to tags in batches
    echo "→ Linking products to tags (this may take a minute)...\n";

    // Build tag name to ID map for faster lookups
    $tagMap = [];
    $result = $db->query("SELECT id, name FROM tags");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $tagMap[$row['name']] = $row['id'];
    }

    // Process products in batches
    $db->beginTransaction();
    $insertProductTagStmt = $db->prepare("INSERT OR IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)");
    $productTagCount = 0;
    $batchCount = 0;

    $result = $db->query("SELECT id, tags FROM products WHERE tags IS NOT NULL AND tags != ''");

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $productId = $row['id'];
        $tagsString = $row['tags'];
        $tags = array_map('trim', explode(',', $tagsString));

        foreach ($tags as $tagName) {
            if (empty($tagName)) continue;

            if (isset($tagMap[$tagName])) {
                $insertProductTagStmt->execute([$productId, $tagMap[$tagName]]);
                if ($insertProductTagStmt->rowCount() > 0) {
                    $productTagCount++;
                }
            }
        }

        // Commit every 1000 products for better performance
        $batchCount++;
        if ($batchCount % 1000 === 0) {
            $db->commit();
            $db->beginTransaction();
            echo "  → Processed {$batchCount} products...\n";
        }
    }

    // Commit remaining
    $db->commit();

    echo "  ✓ Created {$productTagCount} product-tag relationships\n";

    // Create default collections
    echo "→ Creating default collections...\n";
    $collections = [
        [
            'title' => 'All Products',
            'handle' => 'all',
            'description' => 'All products in the catalog',
            'is_smart' => 1,
            'rules' => json_encode(['type' => 'all']),
            'is_featured' => 0
        ],
        [
            'title' => 'Featured Products',
            'handle' => 'featured',
            'description' => 'Featured products',
            'is_smart' => 1,
            'rules' => json_encode(['type' => 'tag_contains', 'value' => 'featured']),
            'is_featured' => 1
        ],
        [
            'title' => 'Sale Items',
            'handle' => 'sale',
            'description' => 'Products on sale',
            'is_smart' => 1,
            'rules' => json_encode(['type' => 'has_compare_price']),
            'is_featured' => 1
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO collections (title, handle, description, is_smart, rules, is_featured)
        VALUES (:title, :handle, :description, :is_smart, :rules, :is_featured)
    ");

    foreach ($collections as $collection) {
        $stmt->execute($collection);
        echo "  ✓ Created collection: {$collection['title']}\n";
    }

    echo "\n========================================\n";
    echo "✓ Products Database Extended Successfully!\n";
    echo "========================================\n\n";

    echo "Tables Created:\n";
    echo "  - collections (3 default collections)\n";
    echo "  - product_collections\n";
    echo "  - categories\n";
    echo "  - product_categories\n";
    echo "  - tags ({$tagCount} tags migrated)\n";
    echo "  - product_tags ({$productTagCount} relationships)\n\n";

    echo "Indexes Created:\n";
    echo "  - 12 indexes for performance optimization\n\n";

    echo "Data Migration:\n";
    echo "  ✓ Migrated {$tagCount} unique tags from products.tags column\n";
    echo "  ✓ Created {$productTagCount} product-tag relationships\n";
    echo "  ✓ Original products.tags column preserved (for backward compatibility)\n\n";

    echo "Next Steps:\n";
    echo "  1. Access admin dashboard to manage collections\n";
    echo "  2. Create categories for product organization\n";
    echo "  3. Assign products to collections and categories\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

exit(0);

