#!/usr/bin/env php
<?php
/**
 * Migration: Add Related Products Support
 *
 * This migration adds:
 * - product_relations table for manual product relationships
 * - Indexes for optimized related product queries
 * - Support for different relation types (related, upsell, cross-sell)
 *
 * Usage:
 *   php migrations/004_add_related_products.php
 *   php migrations/004_add_related_products.php --force
 */

// Parse command-line arguments
$options = [
    'force' => in_array('--force', $argv ?? []),
    'help' => in_array('--help', $argv ?? []) || in_array('-h', $argv ?? []),
];

if ($options['help']) {
    echo <<<HELP

Migration: Add Related Products Support

Usage:
  php migrations/004_add_related_products.php [OPTIONS]

Options:
  --force            Skip confirmation prompts
  --help, -h         Show this help message

HELP;
    exit(0);
}

echo "\n========================================\n";
echo "Migration: Add Related Products Support\n";
echo "========================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Load configuration
$dbConfig = require __DIR__ . '/../config/database.php';
$dbFile = $dbConfig['db_file'];

if (!file_exists($dbFile)) {
    echo "✗ Error: Products database not found at: {$dbFile}\n";
    echo "Please run the product import first: php bin/tackle.php\n\n";
    exit(1);
}

// Confirmation prompt
if (!$options['force']) {
    echo "This migration will add related products support to the database.\n";
    echo "Continue? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        echo "\nMigration cancelled.\n\n";
        exit(0);
    }
}

try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "→ Creating product_relations table...\n";
    
    // Create product_relations table
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_relations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            related_product_id INTEGER NOT NULL,
            relation_type VARCHAR(50) DEFAULT 'related',
            weight REAL DEFAULT 1.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE(product_id, related_product_id, relation_type)
        )
    ");
    
    echo "  ✓ product_relations table created\n";
    
    // Create indexes for performance
    echo "\n→ Creating indexes...\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_product_relations_product ON product_relations(product_id)",
        "CREATE INDEX IF NOT EXISTS idx_product_relations_related ON product_relations(related_product_id)",
        "CREATE INDEX IF NOT EXISTS idx_product_relations_type ON product_relations(relation_type)",
        "CREATE INDEX IF NOT EXISTS idx_product_relations_weight ON product_relations(weight)",
    ];
    
    foreach ($indexes as $indexSql) {
        $db->exec($indexSql);
        echo "  ✓ Index created\n";
    }
    
    // Add sample data (optional - can be commented out)
    echo "\n→ Generating sample related product relationships...\n";
    
    // Get products grouped by type
    $productTypes = $db->query("
        SELECT DISTINCT product_type
        FROM products
        WHERE product_type IS NOT NULL
        LIMIT 10
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $relationsCreated = 0;
    
    foreach ($productTypes as $type) {
        // Get products of this type
        $products = $db->prepare("
            SELECT id
            FROM products
            WHERE product_type = :type
            LIMIT 20
        ");
        $products->execute(['type' => $type]);
        $productIds = $products->fetchAll(PDO::FETCH_COLUMN);
        
        // Create relations between products of same type
        foreach ($productIds as $i => $productId) {
            // Relate to next 3 products in the list
            for ($j = 1; $j <= 3; $j++) {
                $relatedIndex = ($i + $j) % count($productIds);
                $relatedId = $productIds[$relatedIndex];
                
                if ($productId !== $relatedId) {
                    try {
                        $stmt = $db->prepare("
                            INSERT OR IGNORE INTO product_relations 
                            (product_id, related_product_id, relation_type, weight)
                            VALUES (:product_id, :related_id, 'related', :weight)
                        ");
                        $stmt->execute([
                            'product_id' => $productId,
                            'related_id' => $relatedId,
                            'weight' => 1.0 - ($j * 0.1) // Decreasing weight
                        ]);
                        
                        if ($stmt->rowCount() > 0) {
                            $relationsCreated++;
                        }
                    } catch (PDOException $e) {
                        // Ignore duplicate errors
                    }
                }
            }
        }
    }
    
    echo "  ✓ Created {$relationsCreated} sample product relationships\n";
    
    // Statistics
    echo "\n→ Database statistics:\n";
    
    $stats = [
        'Total products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'Total relations' => $db->query("SELECT COUNT(*) FROM product_relations")->fetchColumn(),
        'Products with relations' => $db->query("SELECT COUNT(DISTINCT product_id) FROM product_relations")->fetchColumn(),
    ];
    
    foreach ($stats as $label => $value) {
        echo "  {$label}: {$value}\n";
    }
    
    echo "\n========================================\n";
    echo "✓ Migration Complete!\n";
    echo "========================================\n";
    echo "Related products support has been added.\n";
    echo "\nRelation types supported:\n";
    echo "  - related: General related products\n";
    echo "  - upsell: Higher-priced alternatives\n";
    echo "  - cross-sell: Complementary products\n";
    echo "\nNext steps:\n";
    echo "  1. Use RelatedProductsService to get related products\n";
    echo "  2. Manually add product relations via admin panel\n";
    echo "  3. Run optimize-database.php for best performance\n";
    echo "========================================\n\n";
    
} catch (PDOException $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

exit(0);

