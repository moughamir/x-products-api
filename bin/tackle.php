<?php
// bin/tackle.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure output is immediate for CLI visibility
if (ob_get_level()) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 'on');
ob_implicit_flush(true);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ProductProcessor;

// LOAD CONFIGURATION FILES
$appConfig = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

// CLI needs both the app and db configurations
$config = array_merge($appConfig, $dbConfig);

// FIX: Path now references the DIRECTORY containing individual JSON files
$jsonDirPath = __DIR__ . '/../data/json/products_by_id';

echo "\n========================================\n";
echo "Starting Product Processor CLI Tool...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Database Target: " . $config['db_file'] . "\n";
// Display the new source directory path
echo "Product Source Dir: " . $jsonDirPath . "\n";
echo "========================================\n";

try {
    // Pass the DIRECTORY path to the processor
    $processor = new ProductProcessor($jsonDirPath, $config);
    $result = $processor->process();

    echo "\n=== Processing Final Summary ===\n";
    echo "Total products remaining after filtering: " . $result['total_products'] . "\n";
    echo "Domains found: " . implode(', ', $result['domains']) . "\n";
    echo "Product types found: " . count($result['product_types']) . "\n";

} catch (\Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
