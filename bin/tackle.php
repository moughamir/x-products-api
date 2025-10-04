<?php
// bin/tackle.php (CLI Runner for Product Processor)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fix: Disable output buffering and enable flushing for real-time logging
if (ob_get_level()) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 'on');
ob_implicit_flush(true);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ProductProcessor;

$config = require __DIR__ . '/../config/app.php';
// IMPORTANT: Update this path to your actual JSON file
$jsonFilePath = __DIR__ . '/../data/products.json';

echo "\n========================================\n";
echo "Starting Product Processor CLI Tool...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
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
