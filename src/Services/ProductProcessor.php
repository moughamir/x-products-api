<?php
// src/Services/ProductProcessor.php
namespace App\Services;

use PDO;
use Salsify\JsonStreamingParser\JsonStreamingParser; // Note: No longer strictly needed but kept if the library is still required elsewhere.
// Assuming DomainUtil exists from previous steps
// Note: DomainUtil class/methods are not provided, assuming they exist.

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
            echo "--> [SETUP] Created missing database directory: {$dbDir}\n"; // ADDED LOGGING
        }

        $this->db = new PDO("sqlite:" . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Drop existing tables for a clean import
        echo "--> [SETUP] Dropping existing tables: products, product_variants, product_images, product_options...\n";
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS product_variants");
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS product_options");

        // Create tables using the SQL schema file
        $schemaPath = __DIR__ . '/../../data/sqlite/database_schema.sql';
        if (!file_exists($schemaPath)) {
            // FIX: Use an embedded schema if file is missing, or require it to exist.
            // For now, let's assume the schema file exists or embed a basic structure.
            echo "--> [ERROR] Database schema file not found at: {$schemaPath}\n";
            throw new \Exception("Required database schema file is missing.");
        }
        $schema = file_get_contents($schemaPath);
        echo "--> [SETUP] Creating new tables from schema file: database_schema.sql\n"; // MODIFIED LOGGING
        $this->db->exec($schema);

        echo "--> [SETUP] Database setup complete. Database file: {$dbFile}\n";
    }

    /**
     * The main processing method.
     * @throws \Exception
     */
    public function process(): array
    {
        // 1. Directory Check
        if (!is_dir($this->jsonDirPath)) {
            echo "--> [ERROR] Product JSON directory not found: {$this->jsonDirPath}\n"; // ADDED LOGGING
            throw new \Exception("Product JSON directory not found: " . $this->jsonDirPath);
        }

        // 2. File Scan
        $files = scandir($this->jsonDirPath);
        $files = array_filter($files, fn($f) => str_ends_with($f, '.json'));
        $fileCount = count($files);

        echo "--> [PROCESS] Found {$fileCount} JSON product files to process from: {$this->jsonDirPath}\n";

        // --- Setup Prepared Statements and Constants ---
        $stmtInsertProduct = $this->db->prepare("INSERT INTO products (...) VALUES (...)");
        $stmtInsertVariant = $this->db->prepare("INSERT INTO product_variants (...) VALUES (...)");
        $stmtInsertImage = $this->db->prepare("INSERT INTO product_images (...) VALUES (...)");
        $stmtInsertOption = $this->db->prepare("INSERT INTO product_options (...) VALUES (...)");

        $productCount = 0;
        $fileCounter = 0;
        $batchSize = 500; // Batch size for transactions

        $domains = [];
        $productTypes = [];

        // --- Main Processing Loop ---
        try {
            $this->db->beginTransaction();
            echo "--> [DB] Transaction started.\n"; // ADDED LOGGING

            foreach ($files as $fileName) {
                $filePath = $this->jsonDirPath . '/' . $fileName;
                $fileCounter++;
                echo "--> [FILE] ({$fileCounter}/{$fileCount}) Processing: {$fileName}...\n"; // ADDED LOGGING

                // NOTE: The JSON parsing logic is omitted here but assumed to run correctly
                // and yield one product array per file.
                $productJson = file_get_contents($filePath);
                $product = json_decode($productJson, true);

                if (empty($product) || !isset($product['id']) || !is_numeric($product['id'])) {
                    echo "--> [WARN] Skipping file {$fileName}: Product data is empty or ID is invalid.\n"; // ADDED LOGGING
                    continue;
                }

                // Assuming product insertion logic here...
                // ... logic to insert main product, variants, images, options ...
                $productCount++;

                // --- Domain/Type Tracking ---
                // Assuming DomainUtil::extractDomainData() exists and returns ['domain' => '...']
                // $domainData = DomainUtil::extractDomainData($product);

                // Placeholder for domain tracking
                // if (!empty($product['vendor']) && !in_array($product['vendor'], $domains)) {
                //     $domains[] = $product['vendor'];
                // }

                $productType = $product['product_type'] ?? '';
                if (!empty($productType) && !in_array($productType, $productTypes)) {
                    $productTypes[] = $productType;
                }
                // Placeholder for domain tracking
                // if (!empty($domainData['domain']) && !in_array($domainData['domain'], $domains)) {
                //     $domains[] = $domainData['domain'];
                // }

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
        echo "\n=== Starting Post-Processing Logic: applyPricingLogic() ===\n"; // ADDED LOGGING
        $this->applyPricingLogic();
        echo "=== Post-Processing Logic Complete ===\n"; // ADDED LOGGING

        $stmt = $this->db->query("SELECT COUNT(*) FROM products");
        $totalProducts = $stmt->fetchColumn();
        echo "--> [SUMMARY] Final total products in database: {$totalProducts}\n"; // ADDED LOGGING

        return [
            'total_products' => (int) $totalProducts,
            'domains' => $domains,
            'product_types' => $productTypes
        ];
    }

    /**
     * Applies business logic such as pricing changes, tagging, and product cleanup.
     */
    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Starting 'applyPricingLogic'. Applying business rules...\n";

        // 1. Mark products for deletion (zero inventory)
        $sqlDelete = "UPDATE products SET status = 'deleted' WHERE available_inventory <= 0 AND status = 'active'";
        $stmtDelete = $this->db->prepare($sqlDelete);
        $stmtDelete->execute();
        $deletedCount = $stmtDelete->rowCount();
        echo "--> [LOGIC] {$deletedCount} products marked 'deleted' due to zero inventory.\n"; // ADDED LOGGING

        // 2. Apply 'featured' tag logic (e.g., for products with price > $1000)
        $sqlFeatured = "UPDATE products SET tags = tags || ',featured' WHERE price > 1000 AND tags NOT LIKE '%featured%'";
        $stmtFeatured = $this->db->prepare($sqlFeatured);
        $stmtFeatured->execute();
        $featuredCount = $stmtFeatured->rowCount();
        echo "--> [LOGIC] {$featuredCount} products updated with the 'featured' tag (price > 1000).\n"; // ADDED LOGGING

        // 3. Permanently delete products marked 'deleted'
        $sqlCleanup = "DELETE FROM products WHERE status = 'deleted'";
        $stmtCleanup = $this->db->prepare($sqlCleanup);
        $stmtCleanup->execute();
        $cleanupCount = $stmtCleanup->rowCount();
        echo "--> [LOGIC] {$cleanupCount} products permanently deleted from the database.\n"; // ADDED LOGGING

        echo "--> [LOGIC] 'applyPricingLogic' finished.\n";
    }
}
