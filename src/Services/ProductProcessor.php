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

        // Load and execute the schema
        $schemaSql = file_get_contents(__DIR__ . '/../../data/sqlite/database_schema.sql');
        if ($schemaSql === false) {
            throw new \Exception("Could not read database_schema.sql");
        }
        $this->db->exec($schemaSql);
        echo "--> [SETUP] Database schema created successfully.\n";
    }

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
        // NOTE: In a real-world scenario, you would calculate these scores
        // based on sales data, page views, etc. Here we use an example.

        // Example 1: Set a random bestseller score for demonstration (0.0 to 1.0)
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
    private function getDomainData(array $product): array
    {
        $domain = 'unknown';
        $vendor = $product['vendor'] ?? '';

        if (stripos($vendor, 'vendor') !== false) {
            $domain = 'domestic';
        } elseif (stripos($vendor, 'global') !== false) {
            $domain = 'international';
        }

        return [
            'domain' => $domain,
            'rating' => mt_rand(30, 50) / 10,
            'review_count' => mt_rand(10, 200),
            'category' => $product['product_type'] ?? 'General'
        ];
    }

    public function process(): array
    {
        $fileCounter = 0;
        $productCount = 0;
        $batchSize = 500;
        $productTypes = [];
        $domains = [];

        $directory = new \DirectoryIterator($this->jsonDirPath);
        $fileCount = iterator_count($directory) - 2; // Subtract . and ..

        if ($fileCount <= 0) {
            echo "--> [WARN] No product JSON files found in {$this->jsonDirPath}. Database will be empty.\n";
            return [
                'total_products' => 0,
                'domains' => [],
                'product_types' => []
            ];
        }

        // Prepare statements outside the loop
        $insertProductSql = "INSERT INTO products (id, title, handle, body_html, vendor, product_type, created_at, updated_at, tags, price, compare_at_price, in_stock, source_domain, category, rating, review_count)
                             VALUES (:id, :title, :handle, :body_html, :vendor, :product_type, :created_at, :updated_at, :tags, :price, :compare_at_price, :in_stock, :source_domain, :category, :rating, :review_count)";
        $stmtProduct = $this->db->prepare($insertProductSql);

        $insertImageSql = "INSERT INTO product_images (id, product_id, position, src, width, height, created_at, updated_at)
                           VALUES (:id, :product_id, :position, :src, :width, :height, :created_at, :updated_at)";
        $stmtImage = $this->db->prepare($insertImageSql);

        $insertFtsSql = "INSERT INTO products_fts (rowid, title, body_html) VALUES (:id, :title, :body_html)";
        $stmtFts = $this->db->prepare($insertFtsSql);


        try {
            $this->db->beginTransaction();

            // Loop through all files in the directory
            foreach (new \DirectoryIterator($this->jsonDirPath) as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getExtension() !== 'json') {
                    continue;
                }

                $fileCounter++;
                echo "--> [FILE] Processing file {$fileCounter}/{$fileCount}: {$fileInfo->getFilename()}...\n";

                $jsonContent = file_get_contents($fileInfo->getRealPath());
                if ($jsonContent === false) {
                    echo "--> [ERROR] Could not read file: {$fileInfo->getFilename()}. Skipping.\n";
                    continue;
                }
                $product = json_decode($jsonContent, true);

                if (!isset($product['id'])) {
                    echo "--> [WARN] Product ID missing in file {$fileInfo->getFilename()}. Skipping.\n";
                    continue;
                }
                $productCount++;

                $id = (int)$product['id'];

                // 1. Get synthetic data
                $domainData = $this->getDomainData($product);

                // 2. Insert main product data
                $stmtProduct->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtProduct->bindValue(':title', $product['title'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':handle', $product['handle'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':body_html', $product['body_html'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':vendor', $product['vendor'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':product_type', $product['product_type'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':created_at', $product['created_at'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':updated_at', $product['updated_at'] ?? '', PDO::PARAM_STR);
                $stmtProduct->bindValue(':tags', implode(',', $product['tags'] ?? []), PDO::PARAM_STR);
                $stmtProduct->bindValue(':price', $product['variants'][0]['price'] ?? 0.0, PDO::PARAM_STR); // Use first variant price
                $stmtProduct->bindValue(':compare_at_price', $product['variants'][0]['compare_at_price'] ?? null, PDO::PARAM_STR); // Use first variant compare_at_price
                $stmtProduct->bindValue(':in_stock', array_sum(array_column($product['variants'] ?? [], 'inventory_quantity') ?? [0]), PDO::PARAM_INT);
                $stmtProduct->bindValue(':source_domain', $domainData['domain'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':category', $domainData['category'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':rating', $domainData['rating'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':review_count', $domainData['review_count'], PDO::PARAM_INT);
                $stmtProduct->execute();

                // 3. Insert images
                if (isset($product['images']) && is_array($product['images'])) {
                    foreach ($product['images'] as $image) {
                        $stmtImage->bindValue(':id', $image['id'] ?? null, PDO::PARAM_INT);
                        $stmtImage->bindValue(':product_id', $id, PDO::PARAM_INT);
                        $stmtImage->bindValue(':position', $image['position'] ?? 0, PDO::PARAM_INT);
                        $stmtImage->bindValue(':src', $image['src'] ?? '', PDO::PARAM_STR);
                        $stmtImage->bindValue(':width', $image['width'] ?? 0, PDO::PARAM_INT);
                        $stmtImage->bindValue(':height', $image['height'] ?? 0, PDO::PARAM_INT);
                        $stmtImage->bindValue(':created_at', $image['created_at'] ?? '', PDO::PARAM_STR);
                        $stmtImage->bindValue(':updated_at', $image['updated_at'] ?? '', PDO::PARAM_STR);
                        $stmtImage->execute();
                    }
                }

                // 4. Insert into FTS table
                $stmtFts->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtFts->bindValue(':title', $product['title'] ?? '', PDO::PARAM_STR);
                $stmtFts->bindValue(':body_html', strip_tags($product['body_html'] ?? ''), PDO::PARAM_STR); // Strip HTML for FTS
                $stmtFts->execute();

                // Collect summary domains and product types for final output
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
