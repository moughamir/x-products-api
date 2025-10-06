<?php

namespace App\Services;

use App\Models\Tag;
use PDO;

class TagService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find or create tag by name
     */
    public function findOrCreate(string $name): Tag
    {
        $tag = Tag::findByName($this->db, $name);
        
        if ($tag) {
            return $tag;
        }

        $tag = new Tag();
        $tag->name = $name;
        $tag->slug = $this->generateUniqueSlug($name);
        $tag->save($this->db);

        return $tag;
    }

    /**
     * Merge multiple tags into one
     */
    public function mergeTags(array $sourceTagIds, int $targetTagId): bool
    {
        $targetTag = Tag::find($this->db, $targetTagId);
        if (!$targetTag) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            foreach ($sourceTagIds as $sourceTagId) {
                if ($sourceTagId == $targetTagId) {
                    continue; // Skip target tag
                }

                $sourceTag = Tag::find($this->db, $sourceTagId);
                if ($sourceTag) {
                    $sourceTag->mergeInto($this->db, $targetTagId);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Bulk delete tags
     */
    public function bulkDelete(array $tagIds): int
    {
        $deleted = 0;

        foreach ($tagIds as $tagId) {
            $tag = Tag::find($this->db, $tagId);
            if ($tag && $tag->delete($this->db)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get tag suggestions based on partial name
     */
    public function getSuggestions(string $query, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT name, slug, COUNT(pt.product_id) as product_count
            FROM tags t
            LEFT JOIN product_tags pt ON t.id = pt.tag_id
            WHERE t.name LIKE :query OR t.slug LIKE :query
            GROUP BY t.id
            ORDER BY product_count DESC, t.name ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get popular tags
     */
    public function getPopularTags(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, COUNT(pt.product_id) as product_count
            FROM tags t
            LEFT JOIN product_tags pt ON t.id = pt.tag_id
            GROUP BY t.id
            HAVING product_count > 0
            ORDER BY product_count DESC, t.name ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unused tags
     */
    public function getUnusedTags(): array
    {
        $stmt = $this->db->query("
            SELECT t.*
            FROM tags t
            LEFT JOIN product_tags pt ON t.id = pt.tag_id
            WHERE pt.product_id IS NULL
            ORDER BY t.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete unused tags
     */
    public function deleteUnusedTags(): int
    {
        $stmt = $this->db->exec("
            DELETE FROM tags
            WHERE id NOT IN (SELECT DISTINCT tag_id FROM product_tags)
        ");

        return $stmt;
    }

    /**
     * Sync product tags from comma-separated string
     */
    public function syncProductTags(int $productId, string $tagsString): bool
    {
        $tagNames = array_map('trim', explode(',', $tagsString));
        $tagNames = array_filter($tagNames); // Remove empty values

        $this->db->beginTransaction();

        try {
            // Remove existing tags
            $stmt = $this->db->prepare("DELETE FROM product_tags WHERE product_id = :product_id");
            $stmt->execute(['product_id' => $productId]);

            // Add new tags
            foreach ($tagNames as $tagName) {
                $tag = $this->findOrCreate($tagName);
                $tag->addProduct($this->db, $productId);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Get product tags as comma-separated string
     */
    public function getProductTagsString(int $productId): string
    {
        $stmt = $this->db->prepare("
            SELECT t.name
            FROM tags t
            INNER JOIN product_tags pt ON t.id = pt.tag_id
            WHERE pt.product_id = :product_id
            ORDER BY t.name ASC
        ");

        $stmt->execute(['product_id' => $productId]);
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return implode(', ', $tags);
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Tag::generateSlug($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM tags WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Normalize tag name
     */
    public function normalizeName(string $name): string
    {
        return trim($name);
    }

    /**
     * Get tag statistics
     */
    public function getStatistics(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM tags");
        $totalTags = $stmt->fetchColumn();

        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT t.id)
            FROM tags t
            INNER JOIN product_tags pt ON t.id = pt.tag_id
        ");
        $usedTags = $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM product_tags");
        $totalAssignments = $stmt->fetchColumn();

        return [
            'total_tags' => $totalTags,
            'used_tags' => $usedTags,
            'unused_tags' => $totalTags - $usedTags,
            'total_assignments' => $totalAssignments,
        ];
    }
}

