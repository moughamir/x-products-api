<?php

namespace App\Models;

use PDO;

class Category
{
    public ?int $id = null;
    public string $name;
    public string $slug;
    public ?string $description = null;
    public ?int $parent_id = null;
    public ?string $image_url = null;
    public int $position = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Find category by ID
     */
    public static function find(PDO $db, int $id): ?self
    {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find category by slug
     */
    public static function findBySlug(PDO $db, string $slug): ?self
    {
        $stmt = $db->prepare("SELECT * FROM categories WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all categories
     */
    public static function all(PDO $db, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(name LIKE :search OR slug LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null) {
                $where[] = "parent_id IS NULL";
            } else {
                $where[] = "parent_id = :parent_id";
                $params['parent_id'] = $filters['parent_id'];
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM categories {$whereClause} ORDER BY position ASC, name ASC";
        $stmt = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    /**
     * Get root categories (no parent)
     */
    public static function getRoots(PDO $db): array
    {
        return self::all($db, ['parent_id' => null]);
    }

    /**
     * Get category tree (hierarchical structure)
     */
    public static function getTree(PDO $db): array
    {
        $categories = self::all($db);
        return self::buildTree($categories);
    }

    /**
     * Build hierarchical tree from flat array
     */
    private static function buildTree(array $categories, ?int $parentId = null): array
    {
        $branch = [];
        
        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $children = self::buildTree($categories, $category->id);
                $categoryArray = $category->toArray();
                if (!empty($children)) {
                    $categoryArray['children'] = $children;
                }
                $branch[] = $categoryArray;
            }
        }
        
        return $branch;
    }

    /**
     * Save category (insert or update)
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
     * Insert new category
     */
    private function insert(PDO $db): bool
    {
        $stmt = $db->prepare("
            INSERT INTO categories (name, slug, description, parent_id, image_url, position, created_at, updated_at)
            VALUES (:name, :slug, :description, :parent_id, :image_url, :position, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'image_url' => $this->image_url,
            'position' => $this->position,
        ]);

        if ($result) {
            $this->id = (int)$db->lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing category
     */
    private function update(PDO $db): bool
    {
        $stmt = $db->prepare("
            UPDATE categories 
            SET name = :name, slug = :slug, description = :description, parent_id = :parent_id,
                image_url = :image_url, position = :position, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'image_url' => $this->image_url,
            'position' => $this->position,
        ]);
    }

    /**
     * Delete category
     */
    public function delete(PDO $db): bool
    {
        // First, update children to have no parent
        $stmt = $db->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = :id");
        $stmt->execute(['id' => $this->id]);
        
        // Then delete the category
        $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Get child categories
     */
    public function getChildren(PDO $db): array
    {
        return self::all($db, ['parent_id' => $this->id]);
    }

    /**
     * Get parent category
     */
    public function getParent(PDO $db): ?self
    {
        if (!$this->parent_id) {
            return null;
        }
        return self::find($db, $this->parent_id);
    }

    /**
     * Get breadcrumb path
     */
    public function getBreadcrumbs(PDO $db): array
    {
        $breadcrumbs = [$this->toArray()];
        $parent = $this->getParent($db);
        
        while ($parent) {
            array_unshift($breadcrumbs, $parent->toArray());
            $parent = $parent->getParent($db);
        }
        
        return $breadcrumbs;
    }

    /**
     * Get products in this category
     */
    public function getProducts(PDO $db, int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("
            SELECT p.* 
            FROM products p
            INNER JOIN product_categories pc ON p.id = pc.product_id
            WHERE pc.category_id = :category_id
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':category_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get product count in category
     */
    public function getProductCount(PDO $db): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM product_categories 
            WHERE category_id = :category_id
        ");
        $stmt->execute(['category_id' => $this->id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Add product to category
     */
    public function addProduct(PDO $db, int $productId): bool
    {
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO product_categories (product_id, category_id)
            VALUES (:product_id, :category_id)
        ");
        
        return $stmt->execute([
            'product_id' => $productId,
            'category_id' => $this->id,
        ]);
    }

    /**
     * Remove product from category
     */
    public function removeProduct(PDO $db, int $productId): bool
    {
        $stmt = $db->prepare("
            DELETE FROM product_categories 
            WHERE product_id = :product_id AND category_id = :category_id
        ");
        
        return $stmt->execute([
            'product_id' => $productId,
            'category_id' => $this->id,
        ]);
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
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'image_url' => $this->image_url,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

