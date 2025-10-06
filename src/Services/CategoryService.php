<?php

namespace App\Services;

use App\Models\Category;
use PDO;

class CategoryService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get category tree with product counts
     */
    public function getTreeWithCounts(): array
    {
        $categories = Category::all($this->db);
        
        // Add product counts
        foreach ($categories as $category) {
            $category->product_count = $category->getProductCount($this->db);
        }
        
        return $this->buildTree($categories);
    }

    /**
     * Build hierarchical tree from flat array
     */
    private function buildTree(array $categories, ?int $parentId = null): array
    {
        $branch = [];
        
        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $children = $this->buildTree($categories, $category->id);
                $categoryArray = $category->toArray();
                $categoryArray['product_count'] = $category->product_count ?? 0;
                
                if (!empty($children)) {
                    $categoryArray['children'] = $children;
                }
                $branch[] = $categoryArray;
            }
        }
        
        return $branch;
    }

    /**
     * Get flattened category list with indentation
     */
    public function getFlattenedTree(): array
    {
        $tree = $this->getTreeWithCounts();
        return $this->flattenTree($tree);
    }

    /**
     * Flatten tree for dropdown display
     */
    private function flattenTree(array $tree, int $level = 0): array
    {
        $result = [];
        
        foreach ($tree as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            
            $node['level'] = $level;
            $node['indent'] = str_repeat('—', $level);
            $result[] = $node;
            
            if (!empty($children)) {
                $result = array_merge($result, $this->flattenTree($children, $level + 1));
            }
        }
        
        return $result;
    }

    /**
     * Validate parent-child relationship (prevent circular references)
     */
    public function validateParentChild(int $categoryId, ?int $parentId): bool
    {
        if ($parentId === null) {
            return true; // Root category is always valid
        }

        if ($categoryId === $parentId) {
            return false; // Can't be its own parent
        }

        // Check if parent is a descendant of this category
        $parent = Category::find($this->db, $parentId);
        if (!$parent) {
            return false;
        }

        $ancestors = $this->getAncestors($parent);
        foreach ($ancestors as $ancestor) {
            if ($ancestor['id'] === $categoryId) {
                return false; // Circular reference detected
            }
        }

        return true;
    }

    /**
     * Get all ancestors of a category
     */
    public function getAncestors(Category $category): array
    {
        $ancestors = [];
        $current = $category;

        while ($current->parent_id) {
            $parent = Category::find($this->db, $current->parent_id);
            if (!$parent) {
                break;
            }
            $ancestors[] = $parent->toArray();
            $current = $parent;
        }

        return array_reverse($ancestors);
    }

    /**
     * Get all descendants of a category
     */
    public function getDescendants(Category $category): array
    {
        $descendants = [];
        $children = $category->getChildren($this->db);

        foreach ($children as $child) {
            $descendants[] = $child->toArray();
            $descendants = array_merge($descendants, $this->getDescendants($child));
        }

        return $descendants;
    }

    /**
     * Move category to new parent
     */
    public function moveCategory(Category $category, ?int $newParentId): bool
    {
        if (!$this->validateParentChild($category->id, $newParentId)) {
            return false;
        }

        $category->parent_id = $newParentId;
        return $category->save($this->db);
    }

    /**
     * Reorder categories at the same level
     */
    public function reorderCategories(array $categoryIds): bool
    {
        $this->db->beginTransaction();

        try {
            foreach ($categoryIds as $position => $categoryId) {
                $stmt = $this->db->prepare("UPDATE categories SET position = :position WHERE id = :id");
                $stmt->execute([
                    'position' => $position,
                    'id' => $categoryId,
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Get category path (breadcrumbs)
     */
    public function getCategoryPath(Category $category): array
    {
        return $category->getBreadcrumbs($this->db);
    }

    /**
     * Delete category and handle children
     */
    public function deleteCategory(Category $category, bool $deleteChildren = false): bool
    {
        $this->db->beginTransaction();

        try {
            if ($deleteChildren) {
                // Delete all descendants
                $descendants = $this->getDescendants($category);
                foreach ($descendants as $descendant) {
                    $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
                    $stmt->execute(['id' => $descendant['id']]);
                }
            } else {
                // Move children to parent's parent
                $stmt = $this->db->prepare("UPDATE categories SET parent_id = :parent_id WHERE parent_id = :id");
                $stmt->execute([
                    'parent_id' => $category->parent_id,
                    'id' => $category->id,
                ]);
            }

            // Delete the category
            $category->delete($this->db);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Get category statistics
     */
    public function getStatistics(Category $category): array
    {
        $descendants = $this->getDescendants($category);
        $totalProducts = $category->getProductCount($this->db);

        // Count products in all descendants
        foreach ($descendants as $descendant) {
            $descendantCategory = Category::find($this->db, $descendant['id']);
            if ($descendantCategory) {
                $totalProducts += $descendantCategory->getProductCount($this->db);
            }
        }

        return [
            'direct_products' => $category->getProductCount($this->db),
            'total_products' => $totalProducts,
            'children_count' => count($category->getChildren($this->db)),
            'descendants_count' => count($descendants),
        ];
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = $this->slugify($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Convert string to slug
     */
    private function slugify(string $string): string
    {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM categories WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}

