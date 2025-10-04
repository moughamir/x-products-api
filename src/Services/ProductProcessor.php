<?php
// src/Services/ProductProcessor.php
// CLI script to process and insert products from a large JSON file into SQLite DB
namespace App\Services;

use PDO;

// Set high limits for the CLI script
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Main Product Processor Class
 * Handles database setup and streaming insertion from a JSON file.
 */
class ProductProcessor
{
    private PDO $db;
    private string $jsonFilePath;

    public function __construct(string $jsonFilePath, array $config)
    {
        $this->jsonFilePath = $jsonFilePath;
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

        // Drop tables for a fresh run
        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS products_fts");
        $this->db->exec("DROP TABLE IF EXISTS product_images");
        $this->db->exec("DROP TABLE IF EXISTS product_variants");
        $this->db->exec("DROP TABLE IF EXISTS product_options");


        // --- PRODUCTS Table ---
        $this->db->exec("
            CREATE TABLE products (
                id INTEGER PRIMARY KEY,
                title TEXT,
                handle TEXT,
                body_html TEXT,
                vendor TEXT,
                product_type TEXT,
                tags TEXT,
                price REAL,
                compare_at_price REAL,
                in_stock BOOLEAN,
                rating REAL,
                review_count INTEGER,
                bestseller_score REAL,
                created_at TEXT,
                updated_at TEXT,
                published_at TEXT,
                raw_json TEXT
            )
        ");

        // --- FTS Table ---
        $this->db->exec("
            CREATE VIRTUAL TABLE products_fts USING fts5(
                title,
                body_html,
                vendor,
                product_type,
                tags,
                content='products',
                content_rowid='id'
            )
        ");

        // --- RELATED DATA Tables ---
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
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ");

        $this->db->exec("
            CREATE TABLE product_variants (
                id INTEGER PRIMARY KEY,
                product_id INTEGER,
                title TEXT,
                sku TEXT,
                price REAL,
                compare_at_price REAL,
                available BOOLEAN,
                grams INTEGER,
                position INTEGER,
                option1 TEXT,
                option2 TEXT,
                option3 TEXT,
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ");

        $this->db->exec("
            CREATE TABLE product_options (
                id INTEGER PRIMARY KEY,
                product_id INTEGER,
                name TEXT,
                position INTEGER,
                \"values\" TEXT, -- Quoted to avoid SQLite reserved keyword
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ");

        echo "--> [SETUP] Database setup complete. All tables created.\n";
    }

    /**
     * The Generator uses native PHP functions to stream products
     * character-by-character to bypass I/O stalls and corruption issues.
     */
    private function streamProductsFromFile(string $jsonFilePath): \Generator
    {
        if (!file_exists($jsonFilePath)) {
            throw new \Exception("Product JSON file not found at: " . $jsonFilePath);
        }

        // Dummy generator for brevity, replace with actual streaming logic
        yield from [
            ['id' => 1, 'title' => 'Product A', 'handle' => 'product-a', 'body_html' => '...', 'price' => 10.0, 'tags' => 'tag1,tag2', 'vendor' => 'VendorX'],
            ['id' => 2, 'title' => 'Product B', 'handle' => 'product-b', 'body_html' => '...', 'price' => 20.0, 'tags' => 'tag3', 'vendor' => 'VendorY'],
            // ... actual products from file
        ];
    }

    // --- Data Insertion ---

    private function insertProduct(array $product): void
    {
        // NOTE: This insertion is highly simplified for this code output.
        // A complete implementation would handle all related tables (images, variants, options) here.

        $stmt = $this->db->prepare("
            INSERT INTO products (
                id, title, handle, body_html, vendor, product_type, tags, price,
                compare_at_price, in_stock, created_at, updated_at, published_at, raw_json
            ) VALUES (
                :id, :title, :handle, :body_html, :vendor, :product_type, :tags, :price,
                :compare_at_price, :in_stock, :created_at, :updated_at, :published_at, :raw_json
            )
        ");

        $stmt->bindValue(':id', $product['id']);
        $stmt->bindValue(':title', $product['title'] ?? '');
        $stmt->bindValue(':handle', $product['handle'] ?? '');
        $stmt->bindValue(':body_html', $product['body_html'] ?? '');
        $stmt->bindValue(':vendor', $product['vendor'] ?? '');
        $stmt->bindValue(':product_type', $product['product_type'] ?? '');
        $stmt->bindValue(':tags', $product['tags'] ?? '');
        $stmt->bindValue(':price', $product['price'] ?? 0.0);
        $stmt->bindValue(':compare_at_price', $product['compare_at_price'] ?? null);
        $stmt->bindValue(':in_stock', (int)($product['available'] ?? 1));
        $stmt->bindValue(':created_at', $product['created_at'] ?? date('c'));
        $stmt->bindValue(':updated_at', $product['updated_at'] ?? date('c'));
        $stmt->bindValue(':published_at', $product['published_at'] ?? date('c'));
        $stmt->bindValue(':raw_json', json_encode($product));

        $stmt->execute();

        // Insert into FTS table
        $stmtFTS = $this->db->prepare("INSERT INTO products_fts (rowid, title, body_html, vendor, product_type, tags) VALUES (:id, :title, :body_html, :vendor, :product_type, :tags)");
        $stmtFTS->bindValue(':id', $product['id']);
        $stmtFTS->bindValue(':title', $product['title'] ?? '');
        $stmtFTS->bindValue(':body_html', $product['body_html'] ?? '');
        $stmtFTS->bindValue(':vendor', $product['vendor'] ?? '');
        $stmtFTS->bindValue(':product_type', $product['product_type'] ?? '');
        $stmtFTS->bindValue(':tags', $product['tags'] ?? '');
        $stmtFTS->execute();
    }

    private function applyPricingLogic(): void
    {
        echo "--> [LOGIC] Applying pricing/rating logic...\n";
        // Logic to calculate bestseller_score, average price, etc.
        $this->db->exec("UPDATE products SET bestseller_score = (rating * review_count) / price WHERE review_count > 0");
        echo "--> [LOGIC] Pricing/rating logic complete.\n";
    }

    // --- Main Processor ---

    public function process(): array
    {
        $productCount = 0;
        $domains = [];
        $productTypes = [];
        $batchSize = 1000;

        try {
            $this->db->beginTransaction();

            foreach ($this->streamProductsFromFile($this->jsonFilePath) as $product) {
                $this->insertProduct($product);
                $productCount++;

                if ($productCount % $batchSize === 0) {
                    $this->db->commit();
                    echo "--> [DB] Commit at product #{$productCount}\n";
                    $this->db->beginTransaction();
                }

                // Log every product inserted
                $productTitle = substr($product['title'] ?? 'N/A', 0, 50) . (strlen($product['title'] ?? '') > 50 ? '...' : '');
                echo "--> [INSERT] Product #{$productCount}: ID={$product['id']} Title='{$productTitle}'\n";

                // Track unique product types for final output
                if (!empty($product['product_type']) && !in_array($product['product_type'], $productTypes)) {
                    $productTypes[] = $product['product_type'];
                }
            }

            echo "--> [DB] Finished inserting all {$productCount} initial records. Committing transaction...\n";
            $this->db->commit();
            echo "--> [DB] Transaction committed successfully.\n";
        } catch (\Exception $e) {
            echo "--> [ERROR] Processing failed. Rolling back transaction...\n";
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
