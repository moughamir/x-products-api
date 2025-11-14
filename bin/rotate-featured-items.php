#!/usr/bin/env php
<?php
/**
 * Featured Items Rotation Script
 *
 * This script automatically rotates featured items by:
 * - Selecting high-rated products that aren't currently featured
 * - Removing the 'featured' tag from old featured items
 * - Adding the 'featured' tag to new featured items
 * - Maintaining a configurable number of featured items
 *
 * Designed to be run as a cron job.
 *
 * Usage:
 *   php bin/rotate-featured-items.php                    # Default: 20 featured items
 *   php bin/rotate-featured-items.php --count 30         # Custom count
 *   php bin/rotate-featured-items.php --min-rating 4.5   # Minimum rating threshold
 *   php bin/rotate-featured-items.php --dry-run          # Preview changes without applying
 */

require __DIR__ . '/../vendor/autoload.php';

// Parse command-line arguments
$options = [
    'count' => 20,
    'min_rating' => 4.0,
    'min_reviews' => 10,
    'dry_run' => in_array('--dry-run', $argv),
    'help' => in_array('--help', $argv) || in_array('-h', $argv),
];

// Parse custom options
foreach ($argv as $i => $arg) {
    if ($arg === '--count' && isset($argv[$i + 1])) {
        $options['count'] = (int)$argv[$i + 1];
    }
    if ($arg === '--min-rating' && isset($argv[$i + 1])) {
        $options['min_rating'] = (float)$argv[$i + 1];
    }
    if ($arg === '--min-reviews' && isset($argv[$i + 1])) {
        $options['min_reviews'] = (int)$argv[$i + 1];
    }
}

// Show help
if ($options['help']) {
    echo <<<HELP

Featured Items Rotation Script

Usage:
  php bin/rotate-featured-items.php [OPTIONS]

Options:
  --count N            Number of featured items to maintain (default: 20)
  --min-rating N       Minimum rating threshold (default: 4.0)
  --min-reviews N      Minimum number of reviews (default: 10)
  --dry-run            Preview changes without applying them
  --help, -h           Show this help message

Examples:
  php bin/rotate-featured-items.php                    # Default settings
  php bin/rotate-featured-items.php --count 30         # 30 featured items
  php bin/rotate-featured-items.php --dry-run          # Preview changes

Cron Job Example (daily at 2 AM):
  0 2 * * * cd /path/to/project && php bin/rotate-featured-items.php >> logs/featured-rotation.log 2>&1

HELP;
    exit(0);
}

// Load configuration
$dbConfig = require __DIR__ . '/../config/database.php';

echo "\n========================================\n";
echo "Featured Items Rotation\n";
echo "========================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Target count: {$options['count']}\n";
echo "Min rating: {$options['min_rating']}\n";
echo "Min reviews: {$options['min_reviews']}\n";
echo "Mode: " . ($options['dry_run'] ? 'DRY RUN' : 'LIVE') . "\n";
echo "========================================\n\n";

try {
    $db = new PDO("sqlite:" . $dbConfig['db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Get current featured items
    echo "→ Analyzing current featured items...\n";
    $currentFeatured = $db->query("
        SELECT id, title, rating, review_count, bestseller_score
        FROM products
        WHERE tags LIKE '%featured%'
        ORDER BY rating DESC, review_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Current featured items: " . count($currentFeatured) . "\n";
    
    // 2. Get candidate products for featuring
    echo "\n→ Finding candidate products...\n";
    $candidates = $db->query("
        SELECT id, title, rating, review_count, bestseller_score, tags
        FROM products
        WHERE rating >= {$options['min_rating']}
          AND review_count >= {$options['min_reviews']}
          AND (tags NOT LIKE '%featured%' OR tags IS NULL)
        ORDER BY 
          rating DESC,
          review_count DESC,
          bestseller_score DESC
        LIMIT " . ($options['count'] * 2) . "
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Eligible candidates: " . count($candidates) . "\n";
    
    if (count($candidates) < $options['count']) {
        echo "  ⚠️  Warning: Not enough candidates to fill {$options['count']} slots\n";
    }
    
    // 3. Determine rotation strategy
    $currentCount = count($currentFeatured);
    $targetCount = $options['count'];
    
    if ($currentCount < $targetCount) {
        // Add more featured items
        $toAdd = min($targetCount - $currentCount, count($candidates));
        $newFeatured = array_slice($candidates, 0, $toAdd);
        $toRemove = [];
        
        echo "\n→ Strategy: ADD {$toAdd} new featured items\n";
    } elseif ($currentCount > $targetCount) {
        // Remove some featured items and add new ones
        $toRemoveCount = $currentCount - $targetCount;
        $toRemove = array_slice($currentFeatured, -$toRemoveCount); // Remove lowest rated
        $toAdd = min($targetCount, count($candidates));
        $newFeatured = array_slice($candidates, 0, $toAdd);
        
        echo "\n→ Strategy: REMOVE {$toRemoveCount} old items, ADD {$toAdd} new items\n";
    } else {
        // Rotate some items (replace bottom 25% with new candidates)
        $rotateCount = max(1, (int)($targetCount * 0.25));
        $toRemove = array_slice($currentFeatured, -$rotateCount);
        $newFeatured = array_slice($candidates, 0, $rotateCount);
        
        echo "\n→ Strategy: ROTATE {$rotateCount} items (25% refresh)\n";
    }
    
    // 4. Preview changes
    if (!empty($toRemove)) {
        echo "\n→ Items to remove from featured:\n";
        foreach ($toRemove as $item) {
            echo "  - [{$item['id']}] {$item['title']} (Rating: {$item['rating']}, Reviews: {$item['review_count']})\n";
        }
    }
    
    if (!empty($newFeatured)) {
        echo "\n→ Items to add to featured:\n";
        foreach ($newFeatured as $item) {
            echo "  + [{$item['id']}] {$item['title']} (Rating: {$item['rating']}, Reviews: {$item['review_count']})\n";
        }
    }
    
    // 5. Apply changes (unless dry run)
    if ($options['dry_run']) {
        echo "\n========================================\n";
        echo "DRY RUN - No changes applied\n";
        echo "========================================\n\n";
        exit(0);
    }
    
    echo "\n→ Applying changes...\n";
    $db->beginTransaction();
    
    $removedCount = 0;
    $addedCount = 0;
    
    // Remove featured tag from old items
    foreach ($toRemove as $item) {
        $tags = $item['tags'] ?? '';
        $newTags = preg_replace('/,?\s*featured\s*,?/', '', $tags);
        $newTags = trim($newTags, ', ');
        
        $stmt = $db->prepare("UPDATE products SET tags = :tags WHERE id = :id");
        $stmt->execute([
            'tags' => $newTags ?: null,
            'id' => $item['id']
        ]);
        $removedCount++;
    }
    
    // Add featured tag to new items
    foreach ($newFeatured as $item) {
        $tags = $item['tags'] ?? '';
        $tagsArray = array_filter(array_map('trim', explode(',', $tags)));
        
        if (!in_array('featured', $tagsArray)) {
            $tagsArray[] = 'featured';
        }
        
        $newTags = implode(', ', $tagsArray);
        
        $stmt = $db->prepare("UPDATE products SET tags = :tags WHERE id = :id");
        $stmt->execute([
            'tags' => $newTags,
            'id' => $item['id']
        ]);
        $addedCount++;
    }
    
    $db->commit();
    
    echo "  ✓ Removed featured tag from {$removedCount} items\n";
    echo "  ✓ Added featured tag to {$addedCount} items\n";
    
    // 6. Verify final count
    $finalCount = $db->query("SELECT COUNT(*) FROM products WHERE tags LIKE '%featured%'")->fetchColumn();
    
    echo "\n========================================\n";
    echo "✓ Rotation Complete!\n";
    echo "========================================\n";
    echo "Final featured items count: {$finalCount}\n";
    echo "Target count: {$targetCount}\n";
    echo "========================================\n\n";
    
} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

exit(0);

