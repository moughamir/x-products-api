<?php
// src/Services/ProductProcessor.php
namespace App\Services;

use PDO;

// Set high limits for the CLI script
ini_set('memory_limit', '-1'); // Set to -1 for unlimited memory
ini_set('max_execution_time', 0); // Unlimited execution time
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Main Product Processor Class
 */
class ProductProcessor
{
    private PDO $db;
    // Changed property name to reflect directory path instead of file path
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
        echo "--> [SETUP] Starting database setup...\\n";
        $dbDir = dirname($dbFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        // Use the db file from the config file, which is loaded in tackle.php
        $dbConfig = require __DIR__ . '/../../config/database.php';
        $this->db = new PDO("sqlite:" . $dbConfig['db_file']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Drop existing tables for a clean import
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS products_fts"); // Full Text Search

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
                in_stock INTEGER,
                category TEXT,
                rating REAL DEFAULT 0.0,
                review_count INTEGER DEFAULT 0,
                bestseller_score REAL DEFAULT 0.0
            );
        ");

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

        // FTS5 table for fast searching on title and body
        $this->db->exec("
            CREATE VIRTUAL TABLE products_fts USING fts5(
                title,
                body_html,
                content='products',
                content_rowid='id'
            );
        ");

        echo "--> [SETUP] Database schema created successfully.\\n";
    }

    /**
     * Helper to sanitize and clean HTML content.
     */
    private function sanitizeHtml(string $html): string
    {
        // Simple sanitization for PHP context
        $allowedTags = '<a><p><br><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        // Remove potentially malicious tags, keeping basic formatting
        return strip_tags($html, $allowedTags);
    }

    /**
     * Cleans up the image source URL and extracts the base domain.
     */
    private function processImageUrl(string $src): array
    {
        $domain = 'unknown';
        $path = $src;

        // Try to parse the URL
        if ($parsed = parse_url($src)) {
            $domain = $parsed['host'] ?? 'unknown';
            $path = $parsed['path'] ?? '';

            // Clean up common CDN path structure for local proxying
            // This is crucial for matching the expected structure of the ImageProxy route
            $path = preg_replace('/^\/s\/files\/1\/[^\/]+\/files\//', '', $path);
        }

        return [
            'domain' => $domain,
            'path' => $path
        ];
    }

    /**
     * Applies pricing logic based on a simplified formula (e.g., 10% bestseller boost).
     */
    private function applyPricingLogic(): void
    {
        echo "--> [DB] Applying pricing/scoring logic...\\n";

        // Example: Apply a simple bestseller score based on number of images
        $this->db->exec("
            UPDATE products
            SET bestseller_score = (
                SELECT COUNT(t2.id) * 0.1
                FROM product_images t2
                WHERE t2.product_id = products.id
            ) + 1.0; -- Base score of 1.0
        ");

        echo "--> [DB] Pricing/scoring logic applied.\\n";
    }

    /**
     * Main processing method that reads individual JSON files from the directory.
     */
    public function process(): array
    {
        if (!is_dir($this->jsonDirPath)) {
            throw new \Exception("Product JSON directory not found: {$this->jsonDirPath}");
        }

        // Get all .json files in the directory
        $files = glob("{$this->jsonDirPath}/*.json");
        $fileCount = count($files);

        if ($fileCount === 0) {
            echo "--> [INFO] No JSON files found in {$this->jsonDirPath}. Skipping import.\\n";
            return [
                'total_products' => 0,
                'domains' => [],
                'product_types' => []
            ];
        }

        echo "--> [INFO] Found {$fileCount} product files to process.\\n";

        $productCount = 0;
        $domains = [];
        $productTypes = [];
        $batchSize = 100;
        $fileCounter = 0;

        $productStmt = $this->db->prepare("
            INSERT INTO products (
                id, title, handle, body_html, vendor, product_type, created_at,
                updated_at, tags, source_domain, price, compare_at_price, in_stock, category
            ) VALUES (
                :id, :title, :handle, :body_html, :vendor, :product_type, :created_at,
                :updated_at, :tags, :source_domain, :price, :compare_at_price, :in_stock, :category
            );
        ");

        $imageStmt = $this->db->prepare("
            INSERT INTO product_images (
                id, product_id, position, src, width, height, created_at, updated_at
            ) VALUES (
                :id, :product_id, :position, :src, :width, :height, :created_at, :updated_at
            );
        ");

        $ftsStmt = $this->db->prepare("
            INSERT INTO products_fts (rowid, title, body_html) VALUES (:rowid, :title, :body_html);
        ");


        try {
            $this->db->beginTransaction();

            foreach ($files as $filePath) {
                $fileCounter++;
                $jsonContent = file_get_contents($filePath);
                $product = json_decode($jsonContent, true);

                if (empty($product) || !is_array($product) || !isset($product['id'])) {
                    echo "--> [WARN] Skipping invalid or empty JSON file: " . basename($filePath) . "\\n";
                    continue;
                }

                $productCount++;

                // --- 1. Product Insertion ---
                $title = $product['title'] ?? 'Untitled';
                $handle = $product['handle'] ?? 'no-handle';
                $body = $this->sanitizeHtml($product['body_html'] ?? '');

                // Simple price logic: use first variant price if available
                $price = $product['variants'][0]['price'] ?? 0.0;
                $compareAtPrice = $product['variants'][0]['compare_at_price'] ?? null;
                $inStock = ($product['variants'][0]['available'] ?? false) ? 1 : 0;

                // Process domain for proxying
                $domainData = $this->processImageUrl($product['images'][0]['src'] ?? '');

                // Convert tags array to comma-separated string
                $tagsString = is_array($product['tags'] ?? []) ? implode(', ', $product['tags']) : ($product['tags'] ?? '');

                $productStmt->execute([
                    ':id' => $product['id'],
                    ':title' => $title,
                    ':handle' => $handle,
                    ':body_html' => $body,
                    ':vendor' => $product['vendor'] ?? null,
                    ':product_type' => $product['product_type'] ?? null,
                    ':created_at' => $product['created_at'] ?? null,
                    ':updated_at' => $product['updated_at'] ?? null,
                    ':tags' => $tagsString,
                    ':source_domain' => $domainData['domain'],
                    ':price' => (float) $price,
                    ':compare_at_price' => $compareAtPrice !== null ? (float) $compareAtPrice : null,
                    ':in_stock' => $inStock,
                    ':category' => null // Placeholder
                ]);

                // --- 2. FTS Indexing ---
                $ftsStmt->execute([
                    ':rowid' => $product['id'],
                    ':title' => $title,
                    ':body_html' => strip_tags($body)
                ]);

                // --- 3. Image Insertion ---
                $imagePos = 0;
                $currentImageId = $product['image']['id'] ?? 10000000 + $product['id']; // Fallback ID

                foreach ($product['images'] ?? [] as $image) {
                    $imagePos++;
                    $imagePath = $this->processImageUrl($image['src'])['path'];

                    // The ID for the image must be unique. Using a combination of a large offset and position.
                    // If image['id'] is not present, we create a fallback ID.
                    $imageId = $image['id'] ?? (100000000 + $product['id'] * 1000 + $imagePos);

                    $imageStmt->execute([
                        ':id' => $imageId,
                        ':product_id' => $product['id'],
                        ':position' => $imagePos,
                        ':src' => $imagePath,
                        ':width' => $image['width'] ?? null,
                        ':height' => $image['height'] ?? null,
                        ':created_at' => $image['created_at'] ?? null,
                        ':updated_at' => $image['updated_at'] ?? null,
                    ]);
                }

                // Log every product inserted
                $productTitle = substr($title, 0, 50) . (strlen($title) > 50 ? '...' : '');
                echo "--> [INSERT] Product #{$fileCounter}/{$fileCount}: ID={$product['id']} Title='{$productTitle}'\\n";

                // Track all unique product types and domains for final output
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
                    echo "--> [DB] Batch of {$batchSize} committed. ({$fileCounter}/{$fileCount})\\n";
                }
            }

            // Final commit for remaining records
            echo "--> [DB] Finished inserting all {$productCount} initial records. Committing final transaction...\\n";
            $this->db->commit();
            echo "--> [DB] Transaction committed successfully.\\n";
        } catch (\Exception $e) {
            echo "--> [ERROR] Processing failed. Rolling back transaction...\\n";
            $this->db->rollBack();
            throw $e;
        }

        $this->applyPricingLogic();

        $stmt = $this->db->query("SELECT COUNT(*) FROM products");
        $totalProducts = $stmt->fetchColumn();

        return [
            'total_products' => $totalProducts,
            'domains' => $domains,
            'product_types' => $productTypes
        ];
    }
}
