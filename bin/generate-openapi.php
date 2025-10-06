#!/usr/bin/env php
<?php
/**
 * OpenAPI Documentation Generator Wrapper
 *
 * This wrapper script provides PHP version-aware OpenAPI generation:
 * - PHP 8.4+: Filters E_STRICT deprecation warnings from swagger-php
 * - PHP 8.2/8.3: Passes through to vendor/bin/openapi directly
 * - Production-ready: Works in hosting environments with proper error handling
 *
 * The E_STRICT constant was deprecated in PHP 8.4 because strict mode
 * is now always enabled. The swagger-php library still references it
 * in their error handling code.
 *
 * Usage:
 *   php bin/generate-openapi.php src -o openapi.json --format json
 *   php bin/generate-openapi.php src -o openapi.yaml --format yaml
 */

// Ensure we're in the project root
$projectRoot = dirname(__DIR__);
chdir($projectRoot);

// Get the arguments passed to this script
$args = array_slice($argv, 1);

// Build the command to execute the swagger-php openapi binary
$openapiPath = $projectRoot . '/vendor/bin/openapi';

if (!file_exists($openapiPath)) {
    fwrite(STDERR, "Error: swagger-php openapi binary not found at: {$openapiPath}\n");
    fwrite(STDERR, "Please run: composer install\n");
    exit(1);
}

// Ensure vendor autoload is available
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    fwrite(STDERR, "Error: Composer autoload not found at: {$autoloadPath}\n");
    fwrite(STDERR, "Please run: composer install\n");
    exit(1);
}

// Check PHP version - only use wrapper filtering for PHP 8.4+
$phpVersion = PHP_VERSION_ID;
$needsFiltering = $phpVersion >= 80400; // PHP 8.4.0 or higher

// Extract output file path and ensure directory exists
$outputFile = null;
for ($i = 0; $i < count($args); $i++) {
    if (($args[$i] === '-o' || $args[$i] === '--output') && isset($args[$i + 1])) {
        $outputFile = $args[$i + 1];
        break;
    }
}

// Create output directory if it doesn't exist
if ($outputFile) {
    $outputDir = dirname($outputFile);
    if ($outputDir !== '.' && !is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            fwrite(STDERR, "Error: Failed to create output directory: {$outputDir}\n");
            exit(1);
        }
    }
}

// Build the command
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($openapiPath);
foreach ($args as $arg) {
    $command .= ' ' . escapeshellarg($arg);
}

// Log the command for debugging in production
if (getenv('APP_ENV') === 'development') {
    fwrite(STDERR, "Executing: $command\n");
}

// For PHP < 8.4, just execute directly without filtering
if (!$needsFiltering) {
    passthru($command, $exitCode);

    // Verify output file was created if -o flag was used
    if ($outputFile && !file_exists($outputFile)) {
        fwrite(STDERR, "✗ Error: Output file was not created: $outputFile\n");
        exit(1);
    }

    if ($outputFile && file_exists($outputFile)) {
        fwrite(STDERR, "✓ OpenAPI specification generated: $outputFile\n");
    }

    exit($exitCode);
}

// For PHP 8.4+, execute with E_STRICT warning filtering
$descriptors = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['pipe', 'w'],  // stdout
    2 => ['pipe', 'w'],  // stderr
];

$process = proc_open($command, $descriptors, $pipes);

if (is_resource($process)) {
    // Close stdin
    fclose($pipes[0]);

    // Read and output stdout
    $stdout = stream_get_contents($pipes[1]);
    echo $stdout;
    fclose($pipes[1]);

    // Read stderr and filter out E_STRICT deprecation warnings
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    // Filter out the specific E_STRICT deprecation warning
    $lines = explode("\n", $stderr);
    foreach ($lines as $line) {
        if (stripos($line, 'E_STRICT is deprecated') === false) {
            if (!empty(trim($line))) {
                fwrite(STDERR, $line . "\n");
            }
        }
    }

    $exitCode = proc_close($process);

    // Verify output file was created if -o flag was used
    if ($outputFile) {
        if (file_exists($outputFile)) {
            fwrite(STDERR, "✓ OpenAPI specification generated: $outputFile\n");
        } else {
            fwrite(STDERR, "✗ Error: Output file was not created: $outputFile\n");
            exit(1);
        }
    }
} else {
    fwrite(STDERR, "Error: Failed to execute openapi command\n");
    $exitCode = 1;
}

exit($exitCode);

