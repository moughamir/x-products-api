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
     * Performs a Full-Text Search (FTS) for products, limited by selected fields.
     */
    public function searchProducts(string $query, int $page, int $limit, string $selectFields = '*'): array
    {
        $offset = ($page - 1) * $limit;

        // Use the FTS index created on products_fts table
        $sql = "SELECT {$selectFields}\n"
             . "FROM products\n"
             . "WHERE id IN (\n"
             . "    SELECT id FROM products_fts WHERE products_fts MATCH :query\n"
             . ")\n"
             . "LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getTotalSearchProducts(string $query): int
    {
        // Use the FTS index for counting as well
        $sql = "SELECT COUNT(*) FROM products_fts WHERE products_fts MATCH :query";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $query, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    /**
     * Retrieves products for a specific collection handle, limited by selected fields.
     */
    public function getCollectionProducts(string $collectionHandle, int $page, int $limit, string $selectFields = '*'): array
    {
        $isPaginated = true;
        $whereClause = '1=1';
        $orderBy = 'id DESC';

        switch ($collectionHandle) {
            case 'all':
                $orderBy = 'id DESC';
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                $orderBy = 'id DESC';
                $isPaginated = false; // Usually, featured is a fixed list (max 50)
                break;
            case 'sale':
                $whereClause = "compare_at_price IS NOT NULL AND compare_at_price > price";
                $orderBy = 'id DESC';
                break;
            case 'new':
            case 'bestsellers':
            case 'trending':
                // For demonstration, these are treated as 'all' with pagination
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
