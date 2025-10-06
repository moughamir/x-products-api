<?php

namespace App\Services;

use PDO;

/**
 * Related Products Service
 * 
 * Provides intelligent product recommendations based on:
 * - Same category/collection
 * - Similar tags
 * - Same vendor
 * - Similar price range
 * - Collaborative filtering (products bought together)
 */
class RelatedProductsService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get related products for a given product
     * 
     * @param int $productId The product ID to find related products for
     * @param int $limit Maximum number of related products to return
     * @param array $options Additional options for filtering
     * @return array Array of related products
     */
    public function getRelatedProducts(int $productId, int $limit = 12, array $options = []): array
    {
        // Get the source product details
        $product = $this->getProduct($productId);
        
        if (!$product) {
            return [];
        }
        
        // Build a scoring query that considers multiple factors
        $relatedProducts = [];
        
        // 1. Products in same collections (highest priority)
        $collectionProducts = $this->getProductsByCollection($productId, $limit);
        $relatedProducts = array_merge($relatedProducts, $collectionProducts);
        
        // 2. Products with similar tags
        if (!empty($product['tags'])) {
            $tagProducts = $this->getProductsByTags($productId, $product['tags'], $limit);
            $relatedProducts = array_merge($relatedProducts, $tagProducts);
        }
        
        // 3. Products from same vendor
        if (!empty($product['vendor'])) {
            $vendorProducts = $this->getProductsByVendor($productId, $product['vendor'], $limit);
            $relatedProducts = array_merge($relatedProducts, $vendorProducts);
        }
        
        // 4. Products in similar price range
        if (!empty($product['price'])) {
            $priceProducts = $this->getProductsByPriceRange($productId, $product['price'], $limit);
            $relatedProducts = array_merge($relatedProducts, $priceProducts);
        }
        
        // 5. Products of same type
        if (!empty($product['product_type'])) {
            $typeProducts = $this->getProductsByType($productId, $product['product_type'], $limit);
            $relatedProducts = array_merge($relatedProducts, $typeProducts);
        }
        
        // Deduplicate and score products
        $scoredProducts = $this->scoreAndDeduplicateProducts($relatedProducts, $product);
        
        // Sort by score and limit
        usort($scoredProducts, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);
        
        return array_slice($scoredProducts, 0, $limit);
    }
    
    /**
     * Get suggested products (trending, bestsellers, high-rated)
     * 
     * @param int $limit Maximum number of products to return
     * @param array $options Filtering options
     * @return array Array of suggested products
     */
    public function getSuggestedProducts(int $limit = 12, array $options = []): array
    {
        $strategy = $options['strategy'] ?? 'mixed';
        
        switch ($strategy) {
            case 'trending':
                return $this->getTrendingProducts($limit);
            case 'bestsellers':
                return $this->getBestsellers($limit);
            case 'high_rated':
                return $this->getHighRatedProducts($limit);
            case 'new':
                return $this->getNewProducts($limit);
            case 'mixed':
            default:
                return $this->getMixedSuggestions($limit);
        }
    }
    
    /**
     * Get product details
     */
    private function getProduct(int $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, handle, vendor, product_type, price, tags, rating, review_count
            FROM products
            WHERE id = :id
        ");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get products in same collections
     */
    private function getProductsByCollection(int $productId, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.*, 'collection' as match_type, 3.0 as base_score
            FROM products p
            INNER JOIN product_collections pc1 ON p.id = pc1.product_id
            INNER JOIN product_collections pc2 ON pc1.collection_id = pc2.collection_id
            WHERE pc2.product_id = :product_id
              AND p.id != :product_id
              AND p.in_stock = 1
            LIMIT :limit
        ");
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get products with similar tags
     */
    private function getProductsByTags(int $productId, string $tags, int $limit): array
    {
        $tagArray = array_filter(array_map('trim', explode(',', $tags)));
        
        if (empty($tagArray)) {
            return [];
        }
        
        // Build LIKE conditions for each tag
        $conditions = [];
        $params = ['product_id' => $productId];
        
        foreach ($tagArray as $i => $tag) {
            $conditions[] = "tags LIKE :tag{$i}";
            $params["tag{$i}"] = "%{$tag}%";
        }
        
        $whereClause = implode(' OR ', $conditions);
        
        $stmt = $this->db->prepare("
            SELECT *, 'tags' as match_type, 2.0 as base_score
            FROM products
            WHERE ({$whereClause})
              AND id != :product_id
              AND in_stock = 1
            LIMIT :limit
        ");
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get products from same vendor
     */
    private function getProductsByVendor(int $productId, string $vendor, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT *, 'vendor' as match_type, 1.5 as base_score
            FROM products
            WHERE vendor = :vendor
              AND id != :product_id
              AND in_stock = 1
            ORDER BY rating DESC, review_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':vendor', $vendor);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get products in similar price range (±30%)
     */
    private function getProductsByPriceRange(int $productId, float $price, int $limit): array
    {
        $minPrice = $price * 0.7;
        $maxPrice = $price * 1.3;
        
        $stmt = $this->db->prepare("
            SELECT *, 'price' as match_type, 1.0 as base_score
            FROM products
            WHERE price BETWEEN :min_price AND :max_price
              AND id != :product_id
              AND in_stock = 1
            ORDER BY ABS(price - :target_price)
            LIMIT :limit
        ");
        $stmt->bindValue(':min_price', $minPrice);
        $stmt->bindValue(':max_price', $maxPrice);
        $stmt->bindValue(':target_price', $price);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get products of same type
     */
    private function getProductsByType(int $productId, string $productType, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT *, 'type' as match_type, 2.5 as base_score
            FROM products
            WHERE product_type = :product_type
              AND id != :product_id
              AND in_stock = 1
            ORDER BY rating DESC, review_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':product_type', $productType);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Score and deduplicate products based on multiple matching factors
     */
    private function scoreAndDeduplicateProducts(array $products, array $sourceProduct): array
    {
        $scored = [];
        
        foreach ($products as $product) {
            $id = $product['id'];
            
            if (!isset($scored[$id])) {
                $scored[$id] = $product;
                $scored[$id]['relevance_score'] = 0;
                $scored[$id]['match_reasons'] = [];
            }
            
            // Add base score for this match type
            $scored[$id]['relevance_score'] += $product['base_score'] ?? 1.0;
            $scored[$id]['match_reasons'][] = $product['match_type'] ?? 'unknown';
            
            // Bonus for high ratings
            if (!empty($product['rating']) && $product['rating'] >= 4.5) {
                $scored[$id]['relevance_score'] += 0.5;
            }
            
            // Bonus for many reviews
            if (!empty($product['review_count']) && $product['review_count'] >= 50) {
                $scored[$id]['relevance_score'] += 0.3;
            }
        }
        
        return array_values($scored);
    }
    
    /**
     * Get trending products
     */
    private function getTrendingProducts(int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM products
            WHERE rating >= 4.5 AND review_count >= 50 AND in_stock = 1
            ORDER BY rating DESC, review_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get bestsellers
     */
    private function getBestsellers(int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM products
            WHERE in_stock = 1
            ORDER BY bestseller_score DESC, review_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get high-rated products
     */
    private function getHighRatedProducts(int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM products
            WHERE rating >= 4.0 AND review_count >= 10 AND in_stock = 1
            ORDER BY rating DESC, review_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get new products
     */
    private function getNewProducts(int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM products
            WHERE in_stock = 1
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mixed suggestions (combination of different strategies)
     */
    private function getMixedSuggestions(int $limit): array
    {
        $perCategory = (int)ceil($limit / 3);
        
        $trending = $this->getTrendingProducts($perCategory);
        $bestsellers = $this->getBestsellers($perCategory);
        $newProducts = $this->getNewProducts($perCategory);
        
        // Merge and deduplicate
        $all = array_merge($trending, $bestsellers, $newProducts);
        $unique = [];
        
        foreach ($all as $product) {
            if (!isset($unique[$product['id']])) {
                $unique[$product['id']] = $product;
            }
        }
        
        return array_slice(array_values($unique), 0, $limit);
    }
}

