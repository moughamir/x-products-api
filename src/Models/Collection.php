<?php

namespace App\Models;

use PDO;

class Collection
{
    public ?int $id = null;
    public string $title;
    public string $handle;
    public ?string $description = null;
    public ?string $image_url = null;
    public bool $is_smart = false;
    public ?string $rules = null;
    public string $sort_order = 'manual';
    public bool $is_featured = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Find collection by ID
     */
    public static function find(PDO $db, int $id): ?self
    {
        $stmt = $db->prepare("SELECT * FROM collections WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find collection by handle
     */
    public static function findByHandle(PDO $db, string $handle): ?self
    {
        $stmt = $db->prepare("SELECT * FROM collections WHERE handle = :handle");
        $stmt->execute(['handle' => $handle]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all collections with pagination
     */
    public static function all(PDO $db, int $page = 1, int $limit = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(title LIKE :search OR handle LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_smart'])) {
            $where[] = "is_smart = :is_smart";
            $params['is_smart'] = (int)$filters['is_smart'];
        }

        if (isset($filters['is_featured'])) {
            $where[] = "is_featured = :is_featured";
            $params['is_featured'] = (int)$filters['is_featured'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM collections {$whereClause} ORDER BY is_featured DESC, title ASC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    /**
     * Count total collections
     */
    public static function count(PDO $db, array $filters = []): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(title LIKE :search OR handle LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_smart'])) {
            $where[] = "is_smart = :is_smart";
            $params['is_smart'] = (int)$filters['is_smart'];
        }

        if (isset($filters['is_featured'])) {
            $where[] = "is_featured = :is_featured";
            $params['is_featured'] = (int)$filters['is_featured'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM collections {$whereClause}");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Save collection (insert or update)
     */
    public function save(PDO $db): bool
    {
        if ($this->id) {
            return $this->update($db);
        } else {
            return $this->insert($db);
        }
    }

    /**
     * Insert new collection
     */
    private function insert(PDO $db): bool
    {
        $stmt = $db->prepare("
            INSERT INTO collections (title, handle, description, image_url, is_smart, rules, sort_order, is_featured, created_at, updated_at)
            VALUES (:title, :handle, :description, :image_url, :is_smart, :rules, :sort_order, :is_featured, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([
            'title' => $this->title,
            'handle' => $this->handle,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_smart' => (int)$this->is_smart,
            'rules' => $this->rules,
            'sort_order' => $this->sort_order,
            'is_featured' => (int)$this->is_featured,
        ]);

        if ($result) {
            $this->id = (int)$db->lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing collection
     */
    private function update(PDO $db): bool
    {
        $stmt = $db->prepare("
            UPDATE collections 
            SET title = :title, handle = :handle, description = :description, image_url = :image_url,
                is_smart = :is_smart, rules = :rules, sort_order = :sort_order, is_featured = :is_featured,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $this->id,
            'title' => $this->title,
            'handle' => $this->handle,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_smart' => (int)$this->is_smart,
            'rules' => $this->rules,
            'sort_order' => $this->sort_order,
            'is_featured' => (int)$this->is_featured,
        ]);
    }

    /**
     * Delete collection
     */
    public function delete(PDO $db): bool
    {
        $stmt = $db->prepare("DELETE FROM collections WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Get products in this collection
     */
    public function getProducts(PDO $db, int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("
            SELECT p.* 
            FROM products p
            INNER JOIN product_collections pc ON p.id = pc.product_id
            WHERE pc.collection_id = :collection_id
            ORDER BY pc.position ASC, p.id DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':collection_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get product count in collection
     */
    public function getProductCount(PDO $db): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM product_collections 
            WHERE collection_id = :collection_id
        ");
        $stmt->execute(['collection_id' => $this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Add product to collection
     */
    public function addProduct(PDO $db, int $productId, int $position = 0): bool
    {
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO product_collections (product_id, collection_id, position)
            VALUES (:product_id, :collection_id, :position)
        ");
        
        return $stmt->execute([
            'product_id' => $productId,
            'collection_id' => $this->id,
            'position' => $position,
        ]);
    }

    /**
     * Remove product from collection
     */
    public function removeProduct(PDO $db, int $productId): bool
    {
        $stmt = $db->prepare("
            DELETE FROM product_collections 
            WHERE product_id = :product_id AND collection_id = :collection_id
        ");
        
        return $stmt->execute([
            'product_id' => $productId,
            'collection_id' => $this->id,
        ]);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'handle' => $this->handle,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_smart' => $this->is_smart,
            'rules' => $this->rules ? json_decode($this->rules, true) : null,
            'sort_order' => $this->sort_order,
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

