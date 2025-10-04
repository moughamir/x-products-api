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
        echo "--> [SETUP] Dropping existing tables: products, product_variants, product_images, product_options...\n";
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS product_variants");
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS product_options");

        // **********************************************
        // FIX: The missing step that caused the FATAL ERROR
        // The FTS table (Full-Text Search) must also be dropped
        // if it is created by the database_schema.sql file.
        $this->db->exec("DROP TABLE IF EXISTS products_fts");
        // **********************************************

        // Creating new tables from schema file (database_schema.sql)
        echo "--> [SETUP] Creating new tables from schema file: database_schema.sql\n";

        $schemaFile = __DIR__ . '/../../data/sqlite/database_schema.sql';
        if (!file_exists($schemaFile)) {
            throw new \Exception("Database schema file not found: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $this->db->exec($sql);
    }

    // The rest of the class methods (e.g., process(), applyPricingLogic(), etc.)
    // would follow here, based on your original file content.
    // ... (rest of the file content from the snippet)

    private function insertProduct(array $productData): void
    {
        // ... (insert logic for main product table)
    }

    private function insertVariants(int $productId, array $variants): void
    {
        // ... (insert logic for product_variants)
    }

    private function insertImages(int $productId, array $images): void
    {
        // ... (insert logic for product_images)
    }

    private function insertOptions(int $productId, array $options): void
    {
        // ... (insert logic for product_options)
    }

    private function getDomainData(string $bodyHtml): array
    {
        // Placeholder for logic to extract domain information from bodyHtml
        // Based on the process() method, this function should exist.
        return ['domain' => null];
    }

    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Applying pricing logic and cleaning up products...\n";

        // 1. Set prices where missing
        $sqlPrice = "UPDATE products SET price = 0.00 WHERE price IS NULL";
        $this->db->exec($sqlPrice);

        // 2. Set compare_at_price where missing or zero
        $sqlCompareAt = "UPDATE products SET compare_at_price = NULL WHERE compare_at_price IS NULL OR compare_at_price = 0.00";
        $this->db->exec($sqlCompareAt);

        // 3. Delete products with zero inventory
        // Assuming inventory_quantity is a column
        $sqlDelete = "DELETE FROM products WHERE inventory_quantity <= 0";
        $deletedCount = $this->db->exec($sqlDelete);
        echo "--> [LOGIC] Deleted {$deletedCount} products with zero inventory.\n";
    }


    /**
     * Main method to read and process all JSON product files into the database.
     */
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

                // Domain logic (requires getDomainData() or similar util)
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
