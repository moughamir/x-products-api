<?php

namespace App\Services;

use App\Models\Product;
use PDO;

class ProductManagementService
{
    private PDO $db;
    private ProductService $productService;
    private ImageService $imageService;

    public function __construct(PDO $db, ProductService $productService, ImageService $imageService)
    {
        $this->db = $db;
        $this->productService = $productService;
        $this->imageService = $imageService;
    }

    /**
     * Get products with admin-specific data (including images)
     */
    public function getProductsForAdmin(int $page = 1, int $limit = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE :search OR handle LIKE :search OR vendor LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $where[] = "id IN (SELECT product_id FROM product_categories WHERE category_id = :category_id)";
            $params['category_id'] = $filters['category_id'];
        }

        // Collection filter
        if (!empty($filters['collection_id'])) {
            $where[] = "id IN (SELECT product_id FROM product_collections WHERE collection_id = :collection_id)";
            $params['collection_id'] = $filters['collection_id'];
        }

        // Tag filter
        if (!empty($filters['tag_id'])) {
            $where[] = "id IN (SELECT product_id FROM product_tags WHERE tag_id = :tag_id)";
            $params['tag_id'] = $filters['tag_id'];
        }

        // Stock filter
        if (isset($filters['in_stock'])) {
            $where[] = "in_stock = :in_stock";
            $params['in_stock'] = (int)$filters['in_stock'];
        }

        // Price range filter
        if (isset($filters['min_price'])) {
            $where[] = "price >= :min_price";
            $params['min_price'] = $filters['min_price'];
        }
        if (isset($filters['max_price'])) {
            $where[] = "price <= :max_price";
            $params['max_price'] = $filters['max_price'];
        }

        // Product type filter
        if (!empty($filters['product_type'])) {
            $where[] = "product_type = :product_type";
            $params['product_type'] = $filters['product_type'];
        }

        // Vendor filter
        if (!empty($filters['vendor'])) {
            $where[] = "vendor = :vendor";
            $params['vendor'] = $filters['vendor'];
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'id DESC';

        $sql = "SELECT * FROM products WHERE {$whereClause} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach primary image to each product
        foreach ($products as &$product) {
            $product['primary_image'] = $this->getPrimaryImage($product['id']);
            $product['image_count'] = $this->getImageCount($product['id']);
        }

        return $products;
    }

    /**
     * Get primary (first) image for a product
     */
    private function getPrimaryImage(int $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM product_images
            WHERE product_id = :product_id
            ORDER BY position ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute(['product_id' => $productId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        return $image ?: null;
    }

    /**
     * Get total image count for a product
     */
    private function getImageCount(int $productId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = :product_id");
        $stmt->execute(['product_id' => $productId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count products with filters
     */
    public function countProducts(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(title LIKE :search OR handle LIKE :search OR vendor LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $where[] = "id IN (SELECT product_id FROM product_categories WHERE category_id = :category_id)";
            $params['category_id'] = $filters['category_id'];
        }

        if (!empty($filters['collection_id'])) {
            $where[] = "id IN (SELECT product_id FROM product_collections WHERE collection_id = :collection_id)";
            $params['collection_id'] = $filters['collection_id'];
        }

        if (!empty($filters['tag_id'])) {
            $where[] = "id IN (SELECT product_id FROM product_tags WHERE tag_id = :tag_id)";
            $params['tag_id'] = $filters['tag_id'];
        }

        if (isset($filters['in_stock'])) {
            $where[] = "in_stock = :in_stock";
            $params['in_stock'] = (int)$filters['in_stock'];
        }

        if (isset($filters['min_price'])) {
            $where[] = "price >= :min_price";
            $params['min_price'] = $filters['min_price'];
        }
        if (isset($filters['max_price'])) {
            $where[] = "price <= :max_price";
            $params['max_price'] = $filters['max_price'];
        }

        if (!empty($filters['product_type'])) {
            $where[] = "product_type = :product_type";
            $params['product_type'] = $filters['product_type'];
        }

        if (!empty($filters['vendor'])) {
            $where[] = "vendor = :vendor";
            $params['vendor'] = $filters['vendor'];
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM products WHERE {$whereClause}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(array $productIds): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM products WHERE id IN ({$placeholders})");
        $stmt->execute($productIds);

        return $stmt->rowCount();
    }

    /**
     * Bulk update product field
     */
    public function bulkUpdate(array $productIds, string $field, $value): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $allowedFields = ['in_stock', 'vendor', 'product_type', 'category'];
        if (!in_array($field, $allowedFields)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "UPDATE products SET {$field} = ? WHERE id IN ({$placeholders})";

        $params = array_merge([$value], $productIds);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Bulk assign products to collection
     */
    public function bulkAssignToCollection(array $productIds, int $collectionId): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $count = 0;
        foreach ($productIds as $productId) {
            $stmt = $this->db->prepare("
                INSERT OR IGNORE INTO product_collections (product_id, collection_id, position)
                VALUES (:product_id, :collection_id, 0)
            ");
            if ($stmt->execute(['product_id' => $productId, 'collection_id' => $collectionId])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk assign products to category
     */
    public function bulkAssignToCategory(array $productIds, int $categoryId): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $count = 0;
        foreach ($productIds as $productId) {
            $stmt = $this->db->prepare("
                INSERT OR IGNORE INTO product_categories (product_id, category_id)
                VALUES (:product_id, :category_id)
            ");
            if ($stmt->execute(['product_id' => $productId, 'category_id' => $categoryId])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Generate unique handle
     */
    public function generateUniqueHandle(string $title, ?int $excludeId = null): string
    {
        $handle = $this->slugify($title);
        $originalHandle = $handle;
        $counter = 1;

        while ($this->handleExists($handle, $excludeId)) {
            $handle = $originalHandle . '-' . $counter;
            $counter++;
        }

        return $handle;
    }

    /**
     * Convert string to handle/slug
     */
    private function slugify(string $string): string
    {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Check if handle exists
     */
    private function handleExists(string $handle, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM products WHERE handle = :handle";
        $params = ['handle' => $handle];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}

