<?php

namespace App\Services;

use App\Models\Collection;
use PDO;

class CollectionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Evaluate smart collection rules and return matching product IDs
     */
    public function evaluateSmartCollectionRules(array $rules): array
    {
        if (empty($rules) || !isset($rules['type'])) {
            return [];
        }

        $conditions = [];
        $params = [];

        switch ($rules['type']) {
            case 'all':
                // All products
                $conditions[] = '1=1';
                break;

            case 'tag_contains':
                if (!empty($rules['value'])) {
                    $conditions[] = "tags LIKE :tag_value";
                    $params['tag_value'] = '%' . $rules['value'] . '%';
                }
                break;

            case 'has_compare_price':
                $conditions[] = "compare_at_price IS NOT NULL AND compare_at_price > price";
                break;

            case 'price_range':
                if (isset($rules['min_price'])) {
                    $conditions[] = "price >= :min_price";
                    $params['min_price'] = $rules['min_price'];
                }
                if (isset($rules['max_price'])) {
                    $conditions[] = "price <= :max_price";
                    $params['max_price'] = $rules['max_price'];
                }
                break;

            case 'product_type':
                if (!empty($rules['value'])) {
                    $conditions[] = "product_type = :product_type";
                    $params['product_type'] = $rules['value'];
                }
                break;

            case 'vendor':
                if (!empty($rules['value'])) {
                    $conditions[] = "vendor = :vendor";
                    $params['vendor'] = $rules['value'];
                }
                break;

            case 'in_stock':
                $conditions[] = "in_stock = 1";
                break;

            case 'out_of_stock':
                $conditions[] = "in_stock = 0";
                break;

            case 'multiple':
                // Multiple conditions with AND/OR logic
                if (!empty($rules['conditions'])) {
                    $logic = $rules['logic'] ?? 'AND';
                    $subConditions = [];
                    
                    foreach ($rules['conditions'] as $index => $condition) {
                        $subRule = ['type' => $condition['type'], 'value' => $condition['value'] ?? null];
                        if (isset($condition['min_price'])) $subRule['min_price'] = $condition['min_price'];
                        if (isset($condition['max_price'])) $subRule['max_price'] = $condition['max_price'];
                        
                        $subResult = $this->buildConditionFromRule($subRule, $index);
                        if ($subResult['condition']) {
                            $subConditions[] = $subResult['condition'];
                            $params = array_merge($params, $subResult['params']);
                        }
                    }
                    
                    if (!empty($subConditions)) {
                        $conditions[] = '(' . implode(" {$logic} ", $subConditions) . ')';
                    }
                }
                break;
        }

        if (empty($conditions)) {
            return [];
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT id FROM products WHERE {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Build condition from a single rule
     */
    private function buildConditionFromRule(array $rule, int $index): array
    {
        $condition = '';
        $params = [];
        $suffix = "_{$index}";

        switch ($rule['type']) {
            case 'tag_contains':
                if (!empty($rule['value'])) {
                    $condition = "tags LIKE :tag_value{$suffix}";
                    $params["tag_value{$suffix}"] = '%' . $rule['value'] . '%';
                }
                break;

            case 'has_compare_price':
                $condition = "compare_at_price IS NOT NULL AND compare_at_price > price";
                break;

            case 'price_range':
                $subConditions = [];
                if (isset($rule['min_price'])) {
                    $subConditions[] = "price >= :min_price{$suffix}";
                    $params["min_price{$suffix}"] = $rule['min_price'];
                }
                if (isset($rule['max_price'])) {
                    $subConditions[] = "price <= :max_price{$suffix}";
                    $params["max_price{$suffix}"] = $rule['max_price'];
                }
                if (!empty($subConditions)) {
                    $condition = implode(' AND ', $subConditions);
                }
                break;

            case 'product_type':
                if (!empty($rule['value'])) {
                    $condition = "product_type = :product_type{$suffix}";
                    $params["product_type{$suffix}"] = $rule['value'];
                }
                break;

            case 'vendor':
                if (!empty($rule['value'])) {
                    $condition = "vendor = :vendor{$suffix}";
                    $params["vendor{$suffix}"] = $rule['value'];
                }
                break;

            case 'in_stock':
                $condition = "in_stock = 1";
                break;

            case 'out_of_stock':
                $condition = "in_stock = 0";
                break;
        }

        return ['condition' => $condition, 'params' => $params];
    }

    /**
     * Sync smart collection products
     */
    public function syncSmartCollection(Collection $collection): int
    {
        if (!$collection->is_smart || !$collection->rules) {
            return 0;
        }

        $rules = json_decode($collection->rules, true);
        $productIds = $this->evaluateSmartCollectionRules($rules);

        // Clear existing products
        $stmt = $this->db->prepare("DELETE FROM product_collections WHERE collection_id = :collection_id");
        $stmt->execute(['collection_id' => $collection->id]);

        // Add matching products
        $count = 0;
        foreach ($productIds as $position => $productId) {
            if ($collection->addProduct($this->db, $productId, $position)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync all smart collections
     */
    public function syncAllSmartCollections(): int
    {
        $stmt = $this->db->query("SELECT * FROM collections WHERE is_smart = 1");
        $stmt->setFetchMode(PDO::FETCH_CLASS, Collection::class);
        $collections = $stmt->fetchAll();

        $totalSynced = 0;
        foreach ($collections as $collection) {
            $totalSynced += $this->syncSmartCollection($collection);
        }

        return $totalSynced;
    }

    /**
     * Get collection statistics
     */
    public function getStatistics(Collection $collection): array
    {
        return [
            'product_count' => $collection->getProductCount($this->db),
            'is_smart' => $collection->is_smart,
            'is_featured' => $collection->is_featured,
        ];
    }

    /**
     * Reorder products in manual collection
     */
    public function reorderProducts(Collection $collection, array $productIds): bool
    {
        if ($collection->is_smart) {
            return false; // Can't manually reorder smart collections
        }

        $this->db->beginTransaction();

        try {
            foreach ($productIds as $position => $productId) {
                $stmt = $this->db->prepare("
                    UPDATE product_collections 
                    SET position = :position 
                    WHERE collection_id = :collection_id AND product_id = :product_id
                ");
                $stmt->execute([
                    'position' => $position,
                    'collection_id' => $collection->id,
                    'product_id' => $productId,
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
     * Get available product types for rules
     */
    public function getAvailableProductTypes(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT product_type FROM products WHERE product_type IS NOT NULL ORDER BY product_type");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get available vendors for rules
     */
    public function getAvailableVendors(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT vendor FROM products WHERE vendor IS NOT NULL ORDER BY vendor");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

