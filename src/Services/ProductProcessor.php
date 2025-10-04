<?php
// src/Services/ProductProcessor.php
namespace App\Services;

use PDO;
use Salsify\JsonStreamingParser\JsonStreamingParser;

// Set high limits for the CLI script
ini_set('memory_limit', '-1'); // Set to -1 for unlimited memory
ini_set('max_execution_time', 0); // Unlimited execution time
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Main Product Processor Class
 * Handles reading product JSON files, setting up the database, and inserting product data.
 */
class ProductProcessor
{
    private PDO $db;
    private string $jsonDirPath;
    private array $config;

    public function __construct(string $jsonDirPath, array $config)
    {
        $this->jsonDirPath = $jsonDirPath;
        $this->config = $config;
        $this->setupDatabase($config['db_file']);
    }

    private function setupDatabase(string $dbFile): void
    {
        echo "--> [SETUP] Starting database setup...\n";
        $dbDir = dirname($dbFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->db = new PDO("sqlite:" . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Drop existing tables for a clean import
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS products_fts"); // FTS table drop
        $this->db->exec("DROP TRIGGER IF EXISTS product_ai"); // FTS trigger drop
        $this->db->exec("DROP TRIGGER IF EXISTS product_au"); // FTS trigger drop
        $this->db->exec("DROP TRIGGER IF EXISTS product_ad"); // FTS trigger drop

        // 1. Create the `products` table
        $this->db->exec("
            CREATE TABLE products (
                id INTEGER PRIMARY KEY,
                title TEXT,
                handle TEXT,
                body_html TEXT,
                vendor TEXT,
                product_type TEXT,
                created_at TEXT,
                updated_at TEXT,
                tags TEXT,
                source_domain TEXT,
                price REAL,
                compare_at_price REAL,
                in_stock INTEGER, -- 1 for true, 0 for false
                category TEXT,
                rating REAL DEFAULT 0.0,
                review_count INTEGER DEFAULT 0,
                bestseller_score REAL DEFAULT 0.0
            )
        ");

        // 2. Create the `product_images` table
        $this->db->exec("
            CREATE TABLE product_images (
                id INTEGER PRIMARY KEY,
                product_id INTEGER,
                position INTEGER,
                src TEXT,
                width INTEGER,
                height INTEGER,
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");

        // ====================================================================
        // 3. Create FTS5 Virtual Table for Search
        // ====================================================================
        echo "--> [SETUP] Creating FTS5 search table...\n";
        $this->db->exec("
            CREATE VIRTUAL TABLE products_fts USING fts5(
                title,
                body_html,
                content='products',
                content_rowid='id'
            )
        ");

        // ====================================================================
        // 4. Create Triggers to Keep FTS Index in Sync
        // FTS index must be updated on every INSERT and UPDATE to the main table.
        // ====================================================================
        echo "--> [SETUP] Creating FTS sync triggers...\n";

        // Trigger on INSERT: Insert new row into FTS table
        $this->db->exec("
            CREATE TRIGGER product_ai AFTER INSERT ON products BEGIN
                INSERT INTO products_fts(rowid, title, body_html) VALUES (new.id, new.title, new.body_html);
            END
        ");

        // Trigger on UPDATE: Update existing row in FTS table
        $this->db->exec("
            CREATE TRIGGER product_au AFTER UPDATE ON products BEGIN
                INSERT INTO products_fts(products_fts, rowid, title, body_html) VALUES ('delete', old.id, old.title, old.body_html);
                INSERT INTO products_fts(rowid, title, body_html) VALUES (new.id, new.title, new.body_html);
            END
        ");

        // Trigger on DELETE: Delete row from FTS table
        $this->db->exec("
            CREATE TRIGGER product_ad AFTER DELETE ON products BEGIN
                INSERT INTO products_fts(products_fts, rowid, title, body_html) VALUES ('delete', old.id, old.title, old.body_html);
            END
        ");

        echo "--> [SETUP] Database setup complete.\n";
    }

    /**
     * The main processing pipeline: reads all product files and inserts them.
     */
    public function process(): array
    {
        $fileCount = 0;
        $productCount = 0;
        $batchSize = 500;
        $domains = [];
        $productTypes = [];
        $imageInsertData = []; // Buffer for image data

        echo "--> [FILE] Scanning directory: {$this->jsonDirPath}...\n";

        // Get all JSON files in the specified directory
        $files = glob($this->jsonDirPath . '/*.json');
        $fileCount = count($files);

        if ($fileCount === 0) {
            echo "--> [WARNING] No JSON files found in directory: {$this->jsonDirPath}\n";
            return ['total_products' => 0, 'domains' => [], 'product_types' => []];
        }

        echo "--> [FILE] Found {$fileCount} product files. Starting import...\n";

        // Prepared statements for faster batch insertion
        $productStmt = $this->db->prepare("
            INSERT OR REPLACE INTO products (
                id, title, handle, body_html, vendor, product_type, created_at, updated_at,
                tags, source_domain, price, compare_at_price
            ) VALUES (
                :id, :title, :handle, :body_html, :vendor, :product_type, :created_at, :updated_at,
                :tags, :source_domain, :price, :compare_at_price
            )
        ");

        $imageStmt = $this->db->prepare("
            INSERT OR REPLACE INTO product_images (
                id, product_id, position, src, width, height, created_at, updated_at
            ) VALUES (
                :id, :product_id, :position, :src, :width, :height, :created_at, :updated_at
            )
        ");

        try {
            $this->db->beginTransaction();

            foreach ($files as $filePath) {
                $fileCounter++;

                $jsonContent = file_get_contents($filePath);
                $product = json_decode($jsonContent, true);

                if (!$product || !isset($product['id'])) {
                    echo "--> [ERROR] Skipping invalid or empty file: " . basename($filePath) . "\n";
                    continue;
                }

                // --- 1. EXTRACT DATA ---
                $productId = $product['id'];
                $variant = $product['variants'][0] ?? [];

                // Simple price check based on the first variant (simplification)
                $price = $variant['price'] ?? null;
                $compareAtPrice = $variant['compare_at_price'] ?? null;

                // Extract domain from one of the image src URLs
                $imageSrc = $product['images'][0]['src'] ?? '';
                $domainData = parse_url($imageSrc);
                $sourceDomain = $domainData['host'] ?? null;

                // Collect product images for batch insert later
                foreach ($product['images'] as $image) {
                    // Use a composite key or simply the image ID as primary key
                    $imageInsertData[] = [
                        'id' => $image['id'] ?? null,
                        'product_id' => $productId,
                        'position' => $image['position'] ?? 1,
                        'src' => $image['src'] ?? null,
                        'width' => $image['width'] ?? null,
                        'height' => $image['height'] ?? null,
                        'created_at' => $image['created_at'] ?? date('Y-m-d H:i:s'),
                        'updated_at' => $image['updated_at'] ?? date('Y-m-d H:i:s'),
                    ];
                }

                // --- 2. INSERT PRODUCT ---
                $productStmt->execute([
                    ':id' => $productId,
                    ':title' => $product['title'] ?? '',
                    ':handle' => $product['handle'] ?? '',
                    ':body_html' => $product['body_html'] ?? '',
                    ':vendor' => $product['vendor'] ?? '',
                    ':product_type' => $product['product_type'] ?? '',
                    ':created_at' => $product['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $product['updated_at'] ?? date('Y-m-d H:i:s'),
                    ':tags' => implode(',', $product['tags'] ?? []),
                    ':source_domain' => $sourceDomain,
                    ':price' => $price,
                    ':compare_at_price' => $compareAtPrice,
                ]);

                $productCount++;

                // Track metadata for final output
                $productType = $product['product_type'] ?? '';
                if (!empty($productType) && !in_array($productType, $productTypes)) {
                    $productTypes[] = $productType;
                }
                if (!empty($sourceDomain) && !in_array($sourceDomain, $domains)) {
                    $domains[] = $sourceDomain;
                }

                // Commit batch
                if ($fileCounter % $batchSize === 0) {
                    // Commit main products batch
                    $this->db->commit();
                    $this->db->beginTransaction();
                    echo "--> [DB] Products batch of {$batchSize} committed. ({$fileCounter}/{$fileCount})\n";

                    // Insert images batch
                    $this->insertImagesBatch($imageStmt, $imageInsertData);
                    $imageInsertData = []; // Reset image buffer
                }
            }

            // Final commit for remaining products
            $this->db->commit();
            echo "--> [DB] Finished inserting all {$productCount} initial records. Committing final transaction...\n";
            $this->db->beginTransaction(); // Start new transaction for final image batch

            // Final image batch insertion
            $this->insertImagesBatch($imageStmt, $imageInsertData);
            $this->db->commit();
            echo "--> [DB] Image transaction committed successfully.\n";

        } catch (\Exception $e) {
            echo "--> [ERROR] Processing failed. Rolling back transaction...\n";
            $this->db->rollBack();
            throw $e;
        }

        // Apply product logic (pricing, inventory, deletion of zero-inventory products)
        $this->applyPricingLogic();

        $stmt = $this->db->query("SELECT COUNT(*) FROM products");
        $totalProducts = $stmt->fetchColumn();

        return [
            'total_products' => (int) $totalProducts,
            'domains' => $domains,
            'product_types' => $productTypes
        ];
    }

    /**
     * Helper function to execute the batched image insertion.
     */
    private function insertImagesBatch(PDOStatement $stmt, array $images): void
    {
        if (empty($images)) {
            return;
        }
        foreach ($images as $image) {
            $stmt->execute([
                ':id' => $image['id'],
                ':product_id' => $image['product_id'],
                ':position' => $image['position'],
                ':src' => $image['src'],
                ':width' => $image['width'],
                ':height' => $image['height'],
                ':created_at' => $image['created_at'],
                ':updated_at' => $image['updated_at'],
            ]);
        }
        echo "--> [DB] Image batch committed.\n";
    }

    /**
     * Applies business logic to the products, including setting pricing flags and scores.
     * MODIFIED: This version ENSURES products are NOT deleted and are ALWAYS marked as 'in_stock'.
     */
    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Applying pricing and scoring logic...\n";

        // ====================================================================
        // 1. SET IN_STOCK STATUS (MODIFIED: Always In Stock)
        // Per requirement: All products should be marked in-stock regardless of inventory data.
        // ====================================================================
        echo "--> [LOGIC] FORCING all products to be marked 'in_stock = 1' (true).\n";
        $this->db->exec("UPDATE products SET in_stock = 1");


        // ====================================================================
        // 2. PRODUCT DELETION LOGIC (MODIFIED: REMOVED/SKIPPED)
        // Per requirement: "we shouldnt remove products".
        // ====================================================================
        /*
        // ORIGINAL DELETION LOGIC (NOW COMMENTED OUT):
        // $this->db->exec("DELETE FROM products WHERE in_stock = 0");
        */
        echo "--> [LOGIC] Product deletion logic has been SKIPPED (products are not removed).\n";


        // ====================================================================
        // 3. APPLY PRICING LOGIC (Set 'sale' category)
        // ====================================================================
        echo "--> [LOGIC] Applying sale price logic...\n";
        // Set 'sale' category: compare_at_price must be non-null and greater than price
        $this->db->exec("
            UPDATE products
            SET category = 'sale'
            WHERE compare_at_price IS NOT NULL AND compare_at_price > price
        ");

        // Clear category for products that no longer meet the 'sale' condition
        $this->db->exec("
            UPDATE products
            SET category = NULL
            WHERE category = 'sale'
              AND (compare_at_price IS NULL OR compare_at_price <= price)
        ");


        // ====================================================================
        // 4. APPLY BESTSELLER/TRENDING LOGIC (Dummy/Example Scoring)
        // ====================================================================
        echo "--> [LOGIC] Applying dummy bestseller scores (random for now)...\n";
        $this->db->exec("
            UPDATE products
            SET bestseller_score = ABS(RANDOM() % 100) / 100.0
        ");

        // Example 2: Tag a few random products as 'featured'
        echo "--> [LOGIC] Tagging random products as 'featured'...\n";
        $this->db->exec("
            UPDATE products
            SET tags = tags || ',featured'
            WHERE id IN (
                SELECT id FROM products ORDER BY RANDOM() LIMIT 5
            )
            AND tags NOT LIKE '%featured%'
        ");


        echo "--> [LOGIC] Pricing and scoring logic complete.\n";
    }
}
