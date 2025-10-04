<?php
// src/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use App\Services\ImageProxy; // Added for dependency injection
use PDO;

class ProductService
{
    private PDO $db;
    private ImageProxy $imageProxy; // Added for dependency injection

    public function __construct(PDO $db, ImageProxy $imageProxy) // Constructor updated
    {
        $this->db = $db;
        $this->imageProxy = $imageProxy;
    }

    // --- Retrieval Methods ---

    /**
     * Retrieves a paginated list of products, limited by selected fields.
     */
    public function getProducts(int $page, int $limit, string $selectFields = '*'): array
    {
        $offset = ($page - 1) * $limit;

        // FIX: Use $selectFields instead of hardcoding '*'
        $sql = "SELECT {$selectFields} FROM products LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getTotalProducts(): int
    {
        $totalStmt = $this->db->query("SELECT COUNT(*) FROM products");
        return $totalStmt->fetchColumn();
    }

    /**
     * Retrieves a single product by ID or handle. Always uses SELECT * * to get full JSON data for variants/options.
     */
    public function getProductOrHandle(string $key): ?Product
    {
        if (is_numeric($key) && ctype_digit($key)) {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :key");
            $stmt->bindValue(':key', (int)$key, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE handle = :key");
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        }
        $stmt->execute();

        $product = $stmt->fetchObject(Product::class);

        return $product ?: null;
    }

    /**
     * Searches products using FTS5 (Full-Text Search).
     * Only returns a limited set of fields for performance on list views.
     */
    public function searchProducts(string $query, int $page, int $limit, string $selectFields = '*'): array
    {
        $offset = ($page - 1) * $limit;

        // FTS5 query to find matching product IDs
        $sqlFts = "SELECT rowid FROM products_fts WHERE products_fts MATCH :query LIMIT :limit OFFSET :offset";
        $stmtFts = $this->db->prepare($sqlFts);
        // The query string for FTS is typically escaped or handled by the DB. We use simple binding.
        $stmtFts->bindValue(':query', $query, PDO::PARAM_STR);
        $stmtFts->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtFts->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtFts->execute();
        $ids = $stmtFts->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return [];
        }

        $idString = implode(',', $ids);

        // Fetch product data using the found IDs, ensuring correct order
        $sqlProducts = "SELECT {$selectFields} FROM products WHERE id IN ({$idString}) ORDER BY INSTR(',{$idString},', ',' || id || ',')";
        $stmtProducts = $this->db->query($sqlProducts);

        return $stmtProducts->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getTotalSearchProducts(string $query): int
    {
        $sqlFts = "SELECT COUNT(rowid) FROM products_fts WHERE products_fts MATCH :query";
        $stmtFts = $this->db->prepare($sqlFts);
        $stmtFts->bindValue(':query', $query, PDO::PARAM_STR);
        $stmtFts->execute();
        return (int) $stmtFts->fetchColumn();
    }


    /**
     * Retrieves a list of products by collection handle.
     * Only returns a limited set of fields for performance on list views.
     */
    public function getCollectionProducts(string $collectionHandle, int $page, int $limit, string $selectFields = '*'): array
    {
        $whereClause = '1=1';
        $orderBy = 'id DESC'; // Default sort

        // Pagination check: 'featured' and 'trending' often show a small, curated set
        $isPaginated = true;

        switch ($collectionHandle) {
            case 'all':
                // Handled by default.
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                $orderBy = 'rating DESC, review_count DESC';
                $isPaginated = false;
                break;
            case 'sale':
                $whereClause = "compare_at_price IS NOT NULL AND compare_at_price > price";
                $orderBy = '(compare_at_price - price) DESC'; // Sort by maximum discount
                break;
            case 'new':
                $orderBy = 'created_at DESC';
                break;
            case 'bestsellers':
                $orderBy = 'bestseller_score DESC';
                break;
            case 'trending':
                $whereClause = "rating >= 4.5 AND review_count >= 50";
                $orderBy = 'rating DESC, review_count DESC';
                $isPaginated = false;
                break;
            default:
                $whereClause = '0=1'; // Return nothing for unknown handles
        }

        $limit = $isPaginated ? $limit : min(50, $limit); // Cap featured limit
        $offset = $isPaginated ? ($page - 1) * $limit : 0;

        $sql = "SELECT {$selectFields}
                FROM products
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT :limit
                OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getTotalCollectionProducts(string $collectionHandle): int
    {
        $whereClause = '1=1';

        switch ($collectionHandle) {
            case 'all':
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                break;
            case 'sale':
                $whereClause = "compare_at_price IS NOT NULL AND compare_at_price > price";
                break;
            case 'new':
            case 'bestsellers':
            case 'trending':
                $whereClause = '1=1';
                break;
            default:
                return 0;
        }

        $totalStmt = $this->db->query("SELECT COUNT(*) FROM products WHERE {$whereClause}");
        return $totalStmt->fetchColumn();
    }

    // --- Service Helper Methods ---

    public function getImageProxyService(): ImageProxy
    {
        return $this->imageProxy;
    }
}
