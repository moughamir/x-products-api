#!/usr/bin/env php
<?php
/**
 * OpenAPI Documentation Generator Wrapper
 *
 * This wrapper script fixes the PHP 8.4 E_STRICT deprecation warning
 * in swagger-php by suppressing deprecation notices during generation.
 *
 * The E_STRICT constant was deprecated in PHP 8.4 because strict mode
 * is now always enabled. The swagger-php library still references it
 * in their error handling code.
 */

// Suppress deprecation warnings during OpenAPI generation
error_reporting(E_ALL & ~E_DEPRECATED);

// Get the arguments passed to this script
$args = array_slice($argv, 1);

// Build the command to execute the swagger-php openapi binary
$openapiPath = __DIR__ . '/../vendor/bin/openapi';

if (!file_exists($openapiPath)) {
    fwrite(STDERR, "Error: swagger-php openapi binary not found at: {$openapiPath}\n");
    fwrite(STDERR, "Please run: composer install\n");
    exit(1);
}

// Pass all arguments to the openapi binary
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($openapiPath);
foreach ($args as $arg) {
    $command .= ' ' . escapeshellarg($arg);
}

// Execute the command and filter out E_STRICT deprecation warnings
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

// Restore error reporting
error_reporting(E_ALL);

exit($exitCode);

