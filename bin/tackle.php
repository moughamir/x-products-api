<?php
// bin/tackle.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Path fix: Reference the single products.json file (adjust this if you use the nested JSON structure)
$jsonFilePath = __DIR__ . '/../data/products.json';

echo "\n========================================\n";
echo "Starting Product Processor CLI Tool...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Database Target: " . $config['db_file'] . "\n";
echo "========================================\n";

try {
    $processor = new ProductProcessor($jsonFilePath, $config);
    $result = $processor->process();

    echo "\n=== Processing Final Summary ===\n";
    echo "Total products remaining after filtering: " . $result['total_products'] . "\n";
    echo "Domains found: " . implode(', ', $result['domains']) . "\n";
    echo "Product types found: " . count($result['product_types']) . "\n";

} catch (\Exception $e) {
    echo "\nFATAL ERROR: Processing failed at " . date('Y-m-d H:i:s') . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    exit(1);
}
