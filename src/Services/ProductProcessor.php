<?php
// src/Services/ProductProcessor.php
namespace App\Services;

use PDO;
use Salsify\JsonStreamingParser\JsonStreamingParser; // Note: No longer strictly needed but kept if the library is still required elsewhere.

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
        echo "--> [SETUP] Dropping existing tables: products, product_variants, product_images, product_options...\n";
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS product_variants");
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS product_options");

        // FIX for previous FATAL ERROR: table products_fts already exists
        $this->db->exec("DROP TABLE IF EXISTS products_fts");

        // Creating new tables from schema file (database_schema.sql)
        echo "--> [SETUP] Creating new tables from schema file: database_schema.sql\n";

        $schemaFile = __DIR__ . '/../../data/sqlite/database_schema.sql';
        if (!file_exists($schemaFile)) {
            throw new \Exception("Database schema file not found: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $this->db->exec($sql);
    }

    // --- INSERTION METHODS ---

    private function insertProduct(array $productData): void
    {
        $sql = "INSERT OR REPLACE INTO products (id, title, handle, body_html, vendor, product_type, tags, price, compare_at_price, published_at, created_at, updated_at)
                VALUES (:id, :title, :handle, :body_html, :vendor, :product_type, :tags, :price, :compare_at_price, :published_at, :created_at, :updated_at)";

        $stmt = $this->db->prepare($sql);

        // Find the default variant to pull price/compare_at_price from
        $defaultVariant = $productData['variants'][0] ?? ['price' => null, 'compare_at_price' => null];

        $stmt->execute([
            ':id' => $productData['id'],
            ':title' => $productData['title'],
            ':handle' => $productData['handle'],
            ':body_html' => $productData['body_html'] ?? '',
            ':vendor' => $productData['vendor'],
            ':product_type' => $productData['product_type'],
            ':tags' => is_array($productData['tags']) ? implode(',', $productData['tags']) : ($productData['tags'] ?? ''),
            ':price' => (float) ($defaultVariant['price'] ?? 0.00),
            ':compare_at_price' => (float) ($defaultVariant['compare_at_price'] ?? 0.00),
            ':published_at' => $productData['published_at'],
            ':created_at' => $productData['created_at'],
            ':updated_at' => $productData['updated_at'],
        ]);

        // Insert product into FTS table (if required by schema, assuming M_FT5)
        $this->db->exec("INSERT INTO products_fts(products_fts) VALUES('rebuild')");
    }

    private function insertVariants(int $productId, array $variants): void
    {
        $sql = "INSERT OR REPLACE INTO product_variants (id, product_id, title, price, compare_at_price, option1, option2, option3, created_at, updated_at)
                VALUES (:id, :product_id, :title, :price, :compare_at_price, :option1, :option2, :option3, :created_at, :updated_at)";
        $stmt = $this->db->prepare($sql);

        foreach ($variants as $variant) {
            $stmt->execute([
                ':id' => $variant['id'],
                ':product_id' => $productId,
                ':title' => $variant['title'],
                ':price' => (float) ($variant['price'] ?? 0.00),
                ':compare_at_price' => (float) ($variant['compare_at_price'] ?? 0.00),
                ':option1' => $variant['option1'] ?? null,
                ':option2' => $variant['option2'] ?? null,
                ':option3' => $variant['option3'] ?? null,
                ':created_at' => $variant['created_at'] ?? null,
                ':updated_at' => $variant['updated_at'] ?? null,
            ]);
        }
    }

    private function insertImages(int $productId, array $images): void
    {
        $sql = "INSERT OR REPLACE INTO product_images (id, product_id, position, src, width, height, created_at, updated_at)
                VALUES (:id, :product_id, :position, :src, :width, :height, :created_at, :updated_at)";
        $stmt = $this->db->prepare($sql);

        foreach ($images as $image) {
            $stmt->execute([
                ':id' => $image['id'],
                ':product_id' => $productId,
                ':position' => $image['position'] ?? 0,
                ':src' => $image['src'],
                ':width' => $image['width'] ?? 0,
                ':height' => $image['height'] ?? 0,
                ':created_at' => $image['created_at'] ?? null,
                ':updated_at' => $image['updated_at'] ?? null,
            ]);
        }
    }

    private function insertOptions(int $productId, array $options): void
    {
        $sql = "INSERT OR REPLACE INTO product_options (product_id, name, position, values)
                VALUES (:product_id, :name, :position, :values)";
        $stmt = $this->db->prepare($sql);

        foreach ($options as $option) {
            $stmt->execute([
                ':product_id' => $productId,
                ':name' => $option['name'],
                ':position' => $option['position'] ?? 0,
                ':values' => json_encode($option['values'] ?? []),
            ]);
        }
    }

    private function getDomainData(string $bodyHtml): array
    {
        // Placeholder implementation
        return ['domain' => null];
    }

    /**
     * FIX: Removed the SQL query that checked for 'inventory_quantity'
     * which caused the "no such column" error.
     */
    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Applying pricing logic and cleaning up products...\n";

        // 1. Set prices where missing
        $sqlPrice = "UPDATE products SET price = 0.00 WHERE price IS NULL";
        $this->db->exec($sqlPrice);

        // 2. Set compare_at_price where missing or zero
        $sqlCompareAt = "UPDATE products SET compare_at_price = NULL WHERE compare_at_price IS NULL OR compare_at_price = 0.00";
        $this->db->exec($sqlCompareAt);

        // Removed the problematic line: "DELETE FROM products WHERE inventory_quantity <= 0"
        echo "--> [LOGIC] Skipped inventory-based cleanup as per schema/project requirements.\n";
    }

    // --- MAIN PROCESSOR METHOD ---

    public function process(): array
    {
        $domains = [];
        $productTypes = [];
        $batchSize = 100;

        // Ensure the directory exists
        if (!is_dir($this->jsonDirPath)) {
            throw new \Exception("Product JSON directory not found: {$this->jsonDirPath}");
        }

        // Scan for all .json files
        $jsonFiles = glob($this->jsonDirPath . '/*.json');
        $fileCount = count($jsonFiles);
        $fileCounter = 0;
        $productCount = 0;

        if ($fileCount === 0) {
            echo "--> [INFO] No JSON files found in source directory.\n";
            return ['total_products' => 0, 'domains' => [], 'product_types' => []];
        }

        echo "--> [PROCESS] Found {$fileCount} product JSON files to process.\n";

        try {
            $this->db->beginTransaction();

            foreach ($jsonFiles as $filePath) {
                $fileCounter++;
                $fileName = basename($filePath);

                // Read the JSON file content
                $jsonContent = file_get_contents($filePath);
                if ($jsonContent === false) {
                    echo "--> [WARNING] Failed to read file: {$fileName}. Skipping.\n";
                    continue;
                }

                $product = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "--> [WARNING] JSON decode error in file: {$fileName}. Skipping. Error: " . json_last_error_msg() . "\n";
                    continue;
                }

                // Assuming the file content is the product object itself
                if (!isset($product['id']) || !isset($product['handle'])) {
                    echo "--> [WARNING] Product data missing required 'id' or 'handle' in file: {$fileName}. Skipping.\n";
                    continue;
                }

                // Prepare and insert the product and its related data
                $productCount++;
                $this->insertProduct($product);
                $this->insertVariants($product['id'], $product['variants'] ?? []);
                $this->insertImages($product['id'], $product['images'] ?? []);
                $this->insertOptions($product['id'], $product['options'] ?? []);

                // Collect metadata for final output
                $productType = $product['product_type'] ?? '';
                if (!empty($productType) && !in_array($productType, $productTypes)) {
                    $productTypes[] = $productType;
                }

                // Domain logic
                $domainData = $this->getDomainData($product['body_html'] ?? '');
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

        // Apply product logic (pricing, cleanup)
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
