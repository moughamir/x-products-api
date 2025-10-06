#!/usr/bin/env php
<?php
/**
 * OpenAPI Documentation Generator Wrapper
 *
 * This wrapper script provides PHP version-aware OpenAPI generation:
 * - PHP 8.4+: Filters E_STRICT deprecation warnings from swagger-php
 * - PHP 8.2/8.3: Passes through to vendor/bin/openapi directly
 *
 * The E_STRICT constant was deprecated in PHP 8.4 because strict mode
 * is now always enabled. The swagger-php library still references it
 * in their error handling code.
 */

// Get the arguments passed to this script
$args = array_slice($argv, 1);

// Build the command to execute the swagger-php openapi binary
$openapiPath = __DIR__ . '/../vendor/bin/openapi';

if (!file_exists($openapiPath)) {
    fwrite(STDERR, "Error: swagger-php openapi binary not found at: {$openapiPath}\n");
    fwrite(STDERR, "Please run: composer install\n");
    exit(1);
}

// Check PHP version - only use wrapper filtering for PHP 8.4+
$phpVersion = PHP_VERSION_ID;
$needsFiltering = $phpVersion >= 80400; // PHP 8.4.0 or higher

// Build the command
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($openapiPath);
foreach ($args as $arg) {
    $command .= ' ' . escapeshellarg($arg);
}

// For PHP < 8.4, just execute directly without filtering
if (!$needsFiltering) {
    passthru($command, $exitCode);
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
    echo stream_get_contents($pipes[1]);
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
} else {
    fwrite(STDERR, "Error: Failed to execute openapi command\n");
    $exitCode = 1;
}

exit($exitCode);

