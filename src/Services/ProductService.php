<?php
// src/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use PDO;

class ProductService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // --- Retrieval Methods ---

    public function getProducts(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("SELECT * FROM products LIMIT :limit OFFSET :offset");
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

    // --- Search ---

    public function searchProducts(string $query, int $limit, string $selectFields = '*'): array
    {
        $sql = "SELECT {$selectFields}
                FROM products
                WHERE id IN (
                    SELECT rowid
                    FROM products_fts
                    WHERE products_fts MATCH :query
                )
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    // --- Collections ---

    public function getCollectionProducts(string $collectionHandle, int $page, int $limit, string $fields): array
    {
        $selectFields = $fields ?: '*';
        $orderBy = 'id ASC';
        $isPaginated = true;

        // --- Collection Logic (Simplified) ---
        switch ($collectionHandle) {
            case 'all':
                $whereClause = '1=1';
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                $orderBy = 'bestseller_score DESC';
                $isPaginated = false; // Usually featured lists are not paginated
                $limit = 20;
                break;
            case 'sale':
                $whereClause = "compare_at_price IS NOT NULL AND compare_at_price > price";
                $orderBy = 'price ASC';
                break;
            case 'new':
                $whereClause = '1=1';
                $orderBy = 'created_at DESC';
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
}
