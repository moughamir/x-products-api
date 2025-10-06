#!/usr/bin/env php
<?php
/**
 * Finish Products Database Migration
 *
 * Completes the migration by:
 * - Linking products to tags
 * - Creating default collections
 */

require __DIR__ . '/../vendor/autoload.php';

// Load configuration
$dbConfig = require __DIR__ . '/../config/database.php';
$dbPath = $dbConfig['db_file'];

echo "\n========================================\n";
echo "Finishing Products Database Migration\n";
echo "========================================\n";

try {
    // Connect to database
    echo "→ Connecting to database...\n";
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check current state
    $tagCount = $db->query("SELECT COUNT(*) FROM tags")->fetchColumn();
    $productTagCount = $db->query("SELECT COUNT(*) FROM product_tags")->fetchColumn();
    $collectionCount = $db->query("SELECT COUNT(*) FROM collections")->fetchColumn();

    echo "Current state:\n";
    echo "  - Tags: {$tagCount}\n";
    echo "  - Product-Tag relationships: {$productTagCount}\n";
    echo "  - Collections: {$collectionCount}\n\n";

    // Link products to tags if not done
    if ($productTagCount == 0) {
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
    } else {
        echo "→ Product-tag relationships already exist, skipping...\n";
    }

    // Create default collections if not done
    if ($collectionCount == 0) {
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
    } else {
        echo "→ Collections already exist, skipping...\n";
    }

    echo "\n========================================\n";
    echo "✓ Migration Completed Successfully!\n";
    echo "========================================\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);

