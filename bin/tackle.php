#!/usr/bin/env php
<?php
/**
 * Product Database Setup Tool
 *
 * This script populates the SQLite database with product data from JSON files.
 *
 * WARNING: This script DROPS and RECREATES all product tables!
 *
 * Usage:
 *   php bin/tackle.php              # Interactive mode (asks for confirmation)
 *   php bin/tackle.php --force      # Skip confirmation (use in CI/CD)
 *   php bin/tackle.php --skip-if-exists  # Skip if database already has data
 *
 * Environment Variables:
 *   APP_ENV=production  # Requires --force flag to run
 *   APP_ENV=development # Runs without confirmation
 */

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

// Parse command-line arguments
$options = [
    'force' => in_array('--force', $argv),
    'skip_if_exists' => in_array('--skip-if-exists', $argv),
    'help' => in_array('--help', $argv) || in_array('-h', $argv),
];

// Show help
if ($options['help']) {
    echo <<<HELP

Product Database Setup Tool

Usage:
  php bin/tackle.php [OPTIONS]

Options:
  --force            Skip confirmation prompts (required for production)
  --skip-if-exists   Skip if database already contains products
  --help, -h         Show this help message

Environment Variables:
  APP_ENV            Set to 'production' or 'development' (default: production)

Examples:
  php bin/tackle.php                    # Interactive mode
  php bin/tackle.php --force            # Force run without confirmation
  php bin/tackle.php --skip-if-exists   # Only run if database is empty

WARNING: This script DROPS and RECREATES all product tables!

HELP;
    exit(0);
}

// LOAD CONFIGURATION FILES
$appConfig = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

// CLI needs both the app and db configurations
$config = array_merge($appConfig, $dbConfig);

// FIX: Path now references the DIRECTORY containing individual JSON files
$jsonDirPath = __DIR__ . '/../data/json/products_by_id';

// Detect environment
$env = getenv('APP_ENV') ?: 'production';

echo "\n========================================\n";
echo "Product Database Setup Tool\n";
echo "========================================\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Database Target: " . $config['db_file'] . "\n";
echo "Product Source Dir: " . $jsonDirPath . "\n";
echo "========================================\n";

// Check if database exists and has data
$dbExists = file_exists($config['db_file']);
$hasData = false;

if ($dbExists) {
    try {
        $checkDb = new PDO("sqlite:" . $config['db_file']);
        $checkDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = $checkDb->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $hasData = $result > 0;
        if ($hasData) {
            echo "\n⚠️  Database already contains {$result} products.\n";
        }
    } catch (\Exception $e) {
        // Table doesn't exist or other error - proceed with setup
        echo "\n⚠️  Database exists but may be incomplete: " . $e->getMessage() . "\n";
    }
}

// Skip if requested and database has data
if ($options['skip_if_exists'] && $hasData) {
    echo "\n✓ Database already populated. Skipping setup (--skip-if-exists flag).\n";
    exit(0);
}

// Production safety check
if ($env === 'production' && !$options['force']) {
    echo "\n⚠️  PRODUCTION ENVIRONMENT DETECTED\n";
    echo "This script will DROP and RECREATE all product tables!\n";
    echo "To run on production, use: php bin/tackle.php --force\n\n";
    exit(1);
}

// Interactive confirmation (unless --force)
if (!$options['force'] && $hasData) {
    echo "\n⚠️  WARNING: This will DELETE all existing product data!\n";
    echo "Are you sure you want to continue? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) !== 'yes') {
        echo "\nAborted by user.\n";
        exit(0);
    }
}

echo "\n→ Starting database setup...\n";

try {
    // Pass the DIRECTORY path to the processor
    $processor = new ProductProcessor($jsonDirPath, $config);
    $result = $processor->process();

    echo "\n========================================\n";
    echo "✓ Database Setup Complete!\n";
    echo "========================================\n";
    echo "Total products: " . $result['total_products'] . "\n";
    echo "Domains found: " . implode(', ', $result['domains']) . "\n";
    echo "Product types: " . count($result['product_types']) . "\n";
    echo "========================================\n\n";

} catch (\Exception $e) {
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

