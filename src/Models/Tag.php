<?php

namespace App\Models;

use PDO;

class Tag
{
    public ?int $id = null;
    public string $name;
    public string $slug;
    public ?string $created_at = null;

    /**
     * Find tag by ID
     */
    public static function find(PDO $db, int $id): ?self
    {
        $stmt = $db->prepare("SELECT * FROM tags WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find tag by slug
     */
    public static function findBySlug(PDO $db, string $slug): ?self
    {
        $stmt = $db->prepare("SELECT * FROM tags WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find tag by name
     */
    public static function findByName(PDO $db, string $name): ?self
    {
        $stmt = $db->prepare("SELECT * FROM tags WHERE name = :name");
        $stmt->execute(['name' => $name]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all tags with pagination
     */
    public static function all(PDO $db, int $page = 1, int $limit = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(name LIKE :search OR slug LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "
            SELECT t.*, COUNT(pt.product_id) as product_count
            FROM tags t
            LEFT JOIN product_tags pt ON t.id = pt.tag_id
            {$whereClause}
            GROUP BY t.id
            ORDER BY product_count DESC, t.name ASC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total tags
     */
    public static function count(PDO $db, array $filters = []): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(name LIKE :search OR slug LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM tags {$whereClause}");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Save tag (insert or update)
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
     * Insert new tag
     */
    private function insert(PDO $db): bool
    {
        $stmt = $db->prepare("
            INSERT INTO tags (name, slug, created_at)
            VALUES (:name, :slug, CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([
            'name' => $this->name,
            'slug' => $this->slug,
        ]);

        if ($result) {
            $this->id = (int)$db->lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing tag
     */
    private function update(PDO $db): bool
    {
        $stmt = $db->prepare("
            UPDATE tags 
            SET name = :name, slug = :slug
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ]);
    }

    /**
     * Delete tag
     */
    public function delete(PDO $db): bool
    {
        $stmt = $db->prepare("DELETE FROM tags WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Get products with this tag
     */
    public function getProducts(PDO $db, int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("
            SELECT p.* 
            FROM products p
            INNER JOIN product_tags pt ON p.id = pt.product_id
            WHERE pt.tag_id = :tag_id
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':tag_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get product count for this tag
     */
    public function getProductCount(PDO $db): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM product_tags 
            WHERE tag_id = :tag_id
        ");
        $stmt->execute(['tag_id' => $this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Add product to tag
     */
    public function addProduct(PDO $db, int $productId): bool
    {
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO product_tags (product_id, tag_id)
            VALUES (:product_id, :tag_id)
        ");
        
        return $stmt->execute([
            'product_id' => $productId,
            'tag_id' => $this->id,
        ]);
    }

    /**
     * Remove product from tag
     */
    public function removeProduct(PDO $db, int $productId): bool
    {
        $stmt = $db->prepare("
            DELETE FROM product_tags 
            WHERE product_id = :product_id AND tag_id = :tag_id
        ");
        
        return $stmt->execute([
            'product_id' => $productId,
            'tag_id' => $this->id,
        ]);
    }

    /**
     * Merge this tag into another tag
     */
    public function mergeInto(PDO $db, int $targetTagId): bool
    {
        $db->beginTransaction();
        
        try {
            // Move all product associations to target tag
            $stmt = $db->prepare("
                INSERT OR IGNORE INTO product_tags (product_id, tag_id)
                SELECT product_id, :target_tag_id
                FROM product_tags
                WHERE tag_id = :source_tag_id
            ");
            $stmt->execute([
                'target_tag_id' => $targetTagId,
                'source_tag_id' => $this->id,
            ]);
            
            // Delete this tag
            $this->delete($db);
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Generate slug from name
     */
    public static function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
        ];
    }
}

