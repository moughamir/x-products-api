<?php
// src/Services/ProductProcessor.php
namespace App\Services;

use PDO;
use Salsify\JsonStreamingParser\JsonStreamingParser;
use PDOStatement; // Added use statement for native PDOStatement

// Set unlimited execution time for long-running operations
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '1G'); // Increased memory limit for large datasets
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

        // 1. Create main products table
        $sqlProducts = "
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
                in_stock INTEGER,
                category TEXT,
                rating REAL DEFAULT 0.0,
                review_count INTEGER DEFAULT 0,
                bestseller_score REAL DEFAULT 0.0,
                variants_json TEXT,
                options_json TEXT
            );
        ";
        $this->db->exec($sqlProducts);

        // 2. Create product_images table
        $sqlImages = "
            CREATE TABLE product_images (
                id INTEGER,
                product_id INTEGER,
                position INTEGER,
                alt TEXT,
                src TEXT,
                width INTEGER,
                height INTEGER,
                created_at TEXT,
                updated_at TEXT,
                variant_ids TEXT,
                PRIMARY KEY (id, product_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            );
        ";
        $this->db->exec($sqlImages);

        // 3. Create FTS5 search table
        echo "--> [SETUP] Creating FTS5 search table...\n";
        $sqlFts = "
            CREATE VIRTUAL TABLE products_fts USING fts5(
                title,
                body_html,
                content='products',
                content_rowid='id'
            );
        ";
        $this->db->exec($sqlFts);

        // 4. Create FTS sync triggers
        echo "--> [SETUP] Creating FTS sync triggers...\n";
        $sqlTriggers = "
            CREATE TRIGGER products_insert AFTER INSERT ON products BEGIN
                INSERT INTO products_fts(rowid, title, body_html) VALUES (new.id, new.title, new.body_html);
            END;
            CREATE TRIGGER products_delete AFTER DELETE ON products BEGIN
                INSERT INTO products_fts(products_fts, rowid, title, body_html) VALUES ('delete', old.id, old.title, old.body_html);
            END;
            CREATE TRIGGER products_update AFTER UPDATE ON products BEGIN
                INSERT INTO products_fts(products_fts, rowid, title, body_html) VALUES ('delete', old.id, old.title, old.body_html);
                INSERT INTO products_fts(rowid, title, body_html) VALUES (new.id, new.title, new.body_html);
            END;
        ";
        $this->db->exec($sqlTriggers);

        echo "--> [SETUP] Database setup complete.\n";
    }

    private function getInsertProductSql(): string
    {
        return "
            INSERT INTO products (
                id, title, handle, body_html, vendor, product_type, created_at, updated_at, tags,
                source_domain, price, compare_at_price, in_stock, variants_json, options_json
            ) VALUES (
                :id, :title, :handle, :body_html, :vendor, :product_type, :created_at, :updated_at, :tags,
                :source_domain, :price, :compare_at_price, :in_stock, :variants_json, :options_json
            )
        ";
    }

    private function normalizeTags($tags): ?string
    {
        if ($tags === null) {
            return null;
        }

        if (is_array($tags)) {
            $flatTags = $this->flattenTags($tags);
            return empty($flatTags) ? null : implode(', ', $flatTags);
        }

        return is_string($tags) ? $tags : null;
    }

    private function flattenTags(array $tags): array
    {
        $flattened = [];

        array_walk_recursive($tags, function ($value) use (&$flattened) {
            if (is_string($value) && trim($value) !== '') {
                $flattened[] = trim($value);
            }
        });

        return $flattened;
    }

    /**
     * Format seconds into human-readable time (e.g., "2m 30s")
     */
    private function formatTime(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf("%.0fs", $seconds);
        } elseif ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            $secs = (int) ($seconds % 60);
            return sprintf("%dm %ds", $minutes, $secs);
        } else {
            $hours = (int) floor($seconds / 3600);
            $minutes = (int) floor(($seconds % 3600) / 60);
            return sprintf("%dh %dm", $hours, $minutes);
        }
    }

    private function getInsertImageSql(): string
    {
        return "
            INSERT INTO product_images (
                id, product_id, position, alt, src, width, height, created_at, updated_at, variant_ids
            ) VALUES (
                :id, :product_id, :position, :alt, :src, :width, :height, :created_at, :updated_at, :variant_ids
            )
        ";
    }

    // --- MAIN PROCESSING LOGIC ---

    public function process(): array
    {
        $domains = [];
        $productTypes = [];
        $productCount = 0;
        // PERFORMANCE FIX: Increase batch size for faster processing
        // Larger batches = fewer commits = faster overall processing
        $batchSize = 500; // Increased from 50 to 500 for better performance

        // --- FIX 1: Initialize $fileCounter to prevent 'Undefined variable' warning on line 181 ---
        $fileCounter = 0;

        try {
            // 1. Prepare statements outside the loop
            $stmtProduct = $this->db->prepare($this->getInsertProductSql());
            $stmtImage = $this->db->prepare($this->getInsertImageSql());

            echo "--> [FILE] Scanning directory: {$this->jsonDirPath}...\n";
            $productFiles = glob("{$this->jsonDirPath}/*.json");
            $fileCount = count($productFiles);
            echo "--> [FILE] Found {$fileCount} product files. Starting import...\n";

            $this->db->beginTransaction();

            // PERFORMANCE FIX: Track start time for progress reporting
            $startTime = microtime(true);

            // Start processing files
            foreach ($productFiles as $filePath) {
                // Read the JSON content
                $jsonContent = file_get_contents($filePath);
                $product = json_decode($jsonContent, true);

                if (!$product) {
                    echo "--> [ERROR] Skipping invalid JSON file: " . basename($filePath) . "\n";
                    continue;
                }

                // Get source domain from the file name (or handle if file name is ID)
                $domainData = $this->extractDomainData($product);
                $inStock = $this->getInStockStatus($product['variants'] ?? []);

                // --- Insert Product ---
                $formattedTags = $this->normalizeTags($product['tags'] ?? null);

                $stmtProduct->bindValue(':id', $product['id'], PDO::PARAM_INT);
                $stmtProduct->bindValue(':title', $product['title'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':handle', $product['handle'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':body_html', $product['body_html'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':vendor', $product['vendor'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':product_type', $product['product_type'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':created_at', $product['created_at'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':updated_at', $product['updated_at'], PDO::PARAM_STR);
                if ($formattedTags === null) {
                    $stmtProduct->bindValue(':tags', null, PDO::PARAM_NULL);
                } else {
                    $stmtProduct->bindValue(':tags', $formattedTags, PDO::PARAM_STR);
                }
                $stmtProduct->bindValue(':source_domain', $domainData['domain'], PDO::PARAM_STR);
                $stmtProduct->bindValue(':price', $this->getMinPrice($product['variants'] ?? []), PDO::PARAM_STR);
                $stmtProduct->bindValue(':compare_at_price', $this->getMinCompareAtPrice($product['variants'] ?? []), PDO::PARAM_STR);
                $stmtProduct->bindValue(':in_stock', $inStock, PDO::PARAM_INT);
                $stmtProduct->bindValue(':variants_json', json_encode($product['variants'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
                $stmtProduct->bindValue(':options_json', json_encode($product['options'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
                $stmtProduct->execute();

                $productCount++;
                $fileCounter++;

                // --- Insert Images ---
                $this->insertImagesBatch($stmtImage, (int)$product['id'], $product['images'] ?? []);


                // Collect domains for final output
                $productType = $product['product_type'] ?? '';
                if (!empty($productType) && !in_array($productType, $productTypes)) {
                    $productTypes[] = $productType;
                }
                if (!empty($domainData['domain']) && !in_array($domainData['domain'], $domains)) {
                    $domains[] = $domainData['domain'];
                }

                // Commit batch with enhanced progress reporting
                if ($fileCounter % $batchSize === 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();

                    // PERFORMANCE FIX: Enhanced progress reporting with time estimates
                    $elapsed = microtime(true) - $startTime;
                    $percentage = ($fileCounter / $fileCount) * 100;
                    $rate = $fileCounter / $elapsed;
                    $remaining = ($fileCount - $fileCounter) / $rate;

                    echo sprintf(
                        "--> [DB] Batch committed: %d/%d (%.1f%%) | Rate: %.1f/sec | Elapsed: %s | ETA: %s\n",
                        $fileCounter,
                        $fileCount,
                        $percentage,
                        $rate,
                        $this->formatTime($elapsed),
                        $this->formatTime($remaining)
                    );
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

    // --- PRIVATE HELPERS ---

    private function getMinPrice(array $variants): ?float
    {
        if (empty($variants)) { return null; }
        return min(array_column($variants, 'price'));
    }

    private function getMinCompareAtPrice(array $variants): ?float
    {
        if (empty($variants)) { return null; }
        $prices = array_column($variants, 'compare_at_price');
        $filteredPrices = array_filter($prices, fn($p) => $p !== null && $p > 0);
        return empty($filteredPrices) ? null : min($filteredPrices);
    }

    private function getInStockStatus(array $variants): int
    {
        if (empty($variants)) { return 0; }
        foreach ($variants as $variant) {
            // Shopify data: 'available' is true/false, not a count.
            if (($variant['available'] ?? false) === true) {
                return 1;
            }
        }
        return 0;
    }

    // Simple mock for domain extraction based on file name pattern
    private function extractDomainData(array $product): array
    {
        return ['domain' => 'moritotabi.com'];
    }

    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Applying pricing/inventory logic...\n";
        // Example: Delete products with zero inventory
        // $this->db->exec("DELETE FROM products WHERE in_stock = 0");
        // echo "--> [LOGIC] Deleted out-of-stock products.\n";

        // Example: Update category logic (Placeholder)
        // $this->db->exec("UPDATE products SET category = 'Tops' WHERE product_type = 'T-Shirt'");
        echo "--> [LOGIC] Pricing/inventory logic complete.\n";
    }


    // --- INSERT IMAGES BATCH ---
    // The Fatal Error occurred here because the type hint was incorrect.
    // --- FIX 2: Change the type hint from App\Services\PDOStatement to \PDOStatement ---
    private function insertImagesBatch(\PDOStatement $stmt, int $productId, array $images): void
    {
        if (empty($images)) {
            return;
        }

        foreach ($images as $image) {
            $stmt->bindValue(':id', $image['id'], PDO::PARAM_INT);
            $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindValue(':position', $image['position'] ?? 1, PDO::PARAM_INT);

            // Handle alt text
            if (isset($image['alt']) && $image['alt'] !== null) {
                $stmt->bindValue(':alt', $image['alt'], PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':alt', null, PDO::PARAM_NULL);
            }

            $stmt->bindValue(':src', $image['src'], PDO::PARAM_STR);

            // Handle width and height
            if (isset($image['width']) && $image['width'] !== null) {
                $stmt->bindValue(':width', $image['width'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':width', null, PDO::PARAM_NULL);
            }

            if (isset($image['height']) && $image['height'] !== null) {
                $stmt->bindValue(':height', $image['height'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':height', null, PDO::PARAM_NULL);
            }

            $stmt->bindValue(':created_at', $image['created_at'] ?? date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', $image['updated_at'] ?? date('Y-m-d H:i:s'), PDO::PARAM_STR);

            // Handle variant_ids - store as JSON array
            if (isset($image['variant_ids']) && is_array($image['variant_ids']) && !empty($image['variant_ids'])) {
                $stmt->bindValue(':variant_ids', json_encode($image['variant_ids'], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':variant_ids', null, PDO::PARAM_NULL);
            }

            $stmt->execute();
        }
    }
}
