<?php
// src/Services/ProductProcessor.php
namespace App\Services;

use PDO;
use Salsify\JsonStreamingParser\JsonStreamingParser; // Note: No longer strictly needed but kept if the library is still required elsewhere.
// Assuming DomainUtil exists from previous steps

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
    private array $config; // Keep config accessible

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
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS products_fts");

        // Schema for the 'products' table
        $this->db->exec("
            CREATE TABLE products (
                id INTEGER PRIMARY KEY,
                domain TEXT,
                title TEXT,
                handle TEXT,
                body_html TEXT,
                published_at TEXT,
                created_at TEXT,
                updated_at TEXT,
                vendor TEXT,
                product_type TEXT,
                tags TEXT,
                price REAL,
                compare_at_price REAL,
                variants TEXT,
                options TEXT,
                total_inventory INTEGER,
                metafields TEXT
            );
        ");

        // Schema for the 'product_images' table
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
            );
        ");

        // Full-Text Search (FTS) table
        $this->db->exec("
            CREATE VIRTUAL TABLE products_fts USING fts5(
                id,
                title,
                vendor,
                product_type,
                tags,
                content='products',
                content_rowid='id'
            );
        ");

        echo "--> [SETUP] Database setup complete.\n";
    }

    private function insertImage(int $productId, array $imageData): void
    {
        $sql = "INSERT INTO product_images (
            id, product_id, position, src, width, height, created_at, updated_at
        ) VALUES (
            :id, :product_id, :position, :src, :width, :height, :created_at, :updated_at
        )";

        $stmt = $this->db->prepare($sql);

        // Use current time as fallback if not provided
        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            ':id' => $imageData['id'],
            ':product_id' => $productId,
            ':position' => $imageData['position'] ?? 1,
            ':src' => $imageData['src'] ?? '',
            ':width' => $imageData['width'] ?? 0,
            ':height' => $imageData['height'] ?? 0,
            ':created_at' => $imageData['created_at'] ?? $now,
            ':updated_at' => $imageData['updated_at'] ?? $now
        ]);
    }

    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Applying pricing and inventory logic...\n";

        // Logic 1: Find the minimum price from variants for the product price
        $this->db->exec("
            UPDATE products
            SET price = (
                SELECT MIN(json_extract(value, '$.price'))
                FROM json_each(variants)
            );
        ");

        // Logic 2: Find the minimum compare_at_price from variants for the product compare_at_price
        // Only update if compare_at_price is greater than price (i.e., it's a sale)
        $this->db->exec("
            UPDATE products
            SET compare_at_price = (
                SELECT MIN(json_extract(value, '$.compare_at_price'))
                FROM json_each(variants)
            )
            WHERE compare_at_price > price;
        ");

        // Logic 3: Sum the inventory from all variants for total_inventory
        $this->db->exec("
            UPDATE products
            SET total_inventory = (
                SELECT SUM(json_extract(value, '$.inventory_quantity'))
                FROM json_each(variants)
            );
        ");

        // Logic 4: Delete products with no inventory
        $deletedCount = $this->db->exec("DELETE FROM products WHERE total_inventory <= 0");
        echo "--> [LOGIC] Deleted {$deletedCount} products with zero inventory.\n";

        echo "--> [LOGIC] Pricing and inventory logic complete.\n";
    }

    public function process(): array
    {
        echo "--> [PROCESS] Starting product insertion...\n";

        // FIX: Use glob() on the directory path to find all individual JSON files
        $files = glob("{$this->jsonDirPath}/*.json");
        $fileCount = count($files);
        $productCount = 0;
        $domains = [];
        $productTypes = [];
        $batchSize = 1000;

        $sql = "INSERT INTO products (
            id, domain, title, handle, body_html, published_at, created_at, updated_at, vendor, product_type, tags, variants, options, metafields
        ) VALUES (
            :id, :domain, :title, :handle, :body_html, :published_at, :created_at, :updated_at, :vendor, :product_type, :tags, :variants, :options, :metafields
        )";

        $stmt = $this->db->prepare($sql);

        try {
            $this->db->beginTransaction();

            $fileCounter = 0;
            foreach ($files as $file) {
                $fileCounter++;
                // Read the content of the individual JSON file
                $product = json_decode(file_get_contents($file), true);

                if (empty($product)) {
                    echo "--> [SKIP] Empty or invalid JSON in file: {$file}\n";
                    continue;
                }

                // Skip if product is not published
                if (($product['published_at'] ?? null) === null) {
                    echo "--> [SKIP] Product #{$product['id']} is not published.\n";
                    continue;
                }

                $productCount++;

                // Extract domain from product tags (assuming DomainUtil is available)
                $domainData = DomainUtil::extractDomainFromTags($product['tags'] ?? '');

                // FIX: Explicitly JSON encode variants and options before insertion
                $variantsJson = json_encode($product['variants'] ?? []);
                $optionsJson = json_encode($product['options'] ?? []);

                // Store all image data in the separate table
                foreach ($product['images'] ?? [] as $image) {
                    $this->insertImage($product['id'], $image);
                }

                $stmt->execute([
                    ':id' => $product['id'],
                    ':domain' => $domainData['domain'],
                    ':title' => $product['title'] ?? '',
                    ':handle' => $product['handle'] ?? '',
                    ':body_html' => $product['body_html'] ?? '',
                    ':published_at' => $product['published_at'] ?? null,
                    ':created_at' => $product['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $product['updated_at'] ?? date('Y-m-d H:i:s'),
                    ':vendor' => $product['vendor'] ?? '',
                    ':product_type' => $product['product_type'] ?? '',
                    ':tags' => $product['tags'] ?? '',
                    ':variants' => $variantsJson,
                    ':options' => $optionsJson,
                    ':metafields' => json_encode($product['metafields'] ?? [])
                ]);

                // Insert into FTS table (only relevant fields)
                $this->db->exec("INSERT INTO products_fts (id, title, vendor, product_type, tags) VALUES (
                    {$product['id']},
                    " . $this->db->quote($product['title'] ?? '') . ",
                    " . $this->db->quote($product['vendor'] ?? '') . ",
                    " . $this->db->quote($product['product_type'] ?? '') . ",
                    " . $this->db->quote($product['tags'] ?? '') . "
                )");


                // Collect domains/product_types for final output
                $productType = $product['product_type'] ?? '';
                if (!empty($productType) && !in_array($productType, $productTypes)) {
                    $productTypes[] = $productType;
                }
                if (!empty($domainData['domain']) && !in_array($domainData['domain'], $domains)) {
                    $domains[] = $domainData['domain'];
                }

                // Commit batch
                if ($fileCounter % $batchSize === 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                    echo "--> [DB] Batch of {$batchSize} committed. ({$fileCounter}/{$fileCount})\n";
                }
            }

            // Final commit for remaining records
            echo "--> [DB] Finished inserting all {$productCount} initial records. Committing final transaction...\n";
            $this->db->commit();
            echo "--> [DB] Transaction committed successfully.\n";
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
}
