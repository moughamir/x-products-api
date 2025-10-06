<?php
/**
 * Common Utilities for CLI Scripts
 *
 * This file contains shared functions used across multiple CLI scripts
 * to eliminate code duplication and maintain consistency.
 */

/**
 * Display a formatted header
 */
function displayHeader(string $title, array $info = []): void
{
    $separator = str_repeat('=', 60);

    echo "\n{$separator}\n";
    echo "{$title}\n";
    echo "{$separator}\n";

    foreach ($info as $key => $value) {
        echo "{$key}: {$value}\n";
    }

    if (!empty($info)) {
        echo "{$separator}\n";
    }

    echo "\n";
}

/**
 * Display a formatted footer
 */
function displayFooter(string $message, array $info = []): void
{
    $separator = str_repeat('=', 60);

    echo "\n{$separator}\n";
    echo "{$message}\n";
    echo "{$separator}\n";

    foreach ($info as $key => $value) {
        echo "{$key}: {$value}\n";
    }

    if (!empty($info)) {
        echo "{$separator}\n";
    }

    echo "\n";
}

/**
 * Display a step message
 */
function displayStep(string $message, bool $success = true): void
{
    $icon = $success ? '✓' : '→';
    echo "{$icon} {$message}\n";
}

/**
 * Display an error message
 */
function displayError(string $message): void
{
    echo "✗ Error: {$message}\n";
}

/**
 * Display a warning message
 */
function displayWarning(string $message): void
{
    echo "⚠️  Warning: {$message}\n";
}

/**
 * Ask for user confirmation
 */
function confirmAction(string $message, bool $defaultYes = false): bool
{
    $default = $defaultYes ? 'yes' : 'no';
    echo "{$message} (yes/no) [{$default}]: ";

    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (empty($line)) {
        return $defaultYes;
    }

    return in_array(strtolower($line), ['yes', 'y']);
}

/**
 * Parse command-line options
 */
function parseOptions(array $argv, array $definitions): array
{
    $options = [];

    // Set defaults
    foreach ($definitions as $name => $definition) {
        $options[$name] = $definition['default'] ?? null;
    }

    // Parse arguments
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        foreach ($definitions as $name => $definition) {
            $flags = $definition['flags'] ?? ["--{$name}"];

            if (in_array($arg, $flags)) {
                if ($definition['type'] === 'boolean') {
                    $options[$name] = true;
                } elseif (isset($argv[$i + 1])) {
                    $value = $argv[$i + 1];

                    switch ($definition['type']) {
                        case 'int':
                            $options[$name] = (int)$value;
                            break;
                        case 'float':
                            $options[$name] = (float)$value;
                            break;
                        default:
                            $options[$name] = $value;
                    }

                    $i++; // Skip next argument
                }
                break;
            }
        }
    }

    return $options;
}

/**
 * Display help message
 */
function displayHelp(string $scriptName, string $description, array $options, array $examples = []): void
{
    echo "\n{$description}\n\n";
    echo "Usage:\n";
    echo "  php {$scriptName} [OPTIONS]\n\n";

    if (!empty($options)) {
        echo "Options:\n";
        foreach ($options as $name => $definition) {
            $flags = implode(', ', $definition['flags'] ?? ["--{$name}"]);
            $desc = $definition['description'] ?? '';
            $default = isset($definition['default']) ? " (default: {$definition['default']})" : '';

            echo "  {$flags}\n";
            echo "      {$desc}{$default}\n";
        }
        echo "\n";
    }

    if (!empty($examples)) {
        echo "Examples:\n";
        foreach ($examples as $example) {
            echo "  {$example}\n";
        }
        echo "\n";
    }
}

/**
 * Connect to database with error handling
 */
function connectDatabase(string $dbPath, string $dbName = 'Database'): ?PDO
{
    if (!file_exists($dbPath)) {
        displayError("{$dbName} not found at: {$dbPath}");
        return null;
    }

    try {
        $db = new PDO("sqlite:" . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        displayError("Failed to connect to {$dbName}: " . $e->getMessage());
        return null;
    }
}

/**
 * Format bytes to human-readable size
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Format seconds to human-readable time
 */
function formatTime(float $seconds): string
{
    if ($seconds < 60) {
        return sprintf("%.0fs", $seconds);
    } elseif ($seconds < 3600) {
        $minutes = (int) floor($seconds / 60);
        $secs = (int) ($seconds % 60);
        return sprintf("%dm %ds", $minutes, $secs);
    } else {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        return sprintf("%dh %dm", $hours, $minutes);
    }
}

/**
 * Get database statistics
 */
function getDatabaseStats(PDO $db): array
{
    $stats = [];

    try {
        $stats['page_count'] = $db->query("PRAGMA page_count")->fetchColumn();
        $stats['page_size'] = $db->query("PRAGMA page_size")->fetchColumn();
        $stats['size_bytes'] = $stats['page_count'] * $stats['page_size'];
        $stats['size_formatted'] = formatBytes($stats['size_bytes']);
        $stats['journal_mode'] = $db->query("PRAGMA journal_mode")->fetchColumn();
    } catch (PDOException $e) {
        displayWarning("Could not retrieve database statistics: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Enable WAL mode for better concurrency
 */
function enableWALMode(PDO $db): bool
{
    try {
        $result = $db->query("PRAGMA journal_mode=WAL")->fetchColumn();
        return $result === 'wal';
    } catch (PDOException $e) {
        displayWarning("Could not enable WAL mode: " . $e->getMessage());
        return false;
    }
}

/**
 * Optimize database (VACUUM + ANALYZE)
 */
function optimizeDatabase(PDO $db): array
{
    $results = [
        'vacuum' => false,
        'analyze' => false,
        'space_saved' => 0,
    ];

    try {
        // Get size before
        $stats = getDatabaseStats($db);
        $sizeBefore = $stats['size_bytes'] ?? 0;

        // Run VACUUM
        $db->exec("VACUUM");
        $results['vacuum'] = true;

        // Run ANALYZE
        $db->exec("ANALYZE");
        $results['analyze'] = true;

        // Get size after
        $stats = getDatabaseStats($db);
        $sizeAfter = $stats['size_bytes'] ?? 0;

        $results['space_saved'] = $sizeBefore - $sizeAfter;
    } catch (PDOException $e) {
        displayWarning("Database optimization failed: " . $e->getMessage());
    }

    return $results;
}

/**
 * Create index if not exists
 */
function createIndex(PDO $db, string $indexName, string $indexSql): bool
{
    try {
        $db->exec($indexSql);
        return true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            return true; // Index already exists, that's fine
        }
        displayWarning("Failed to create index {$indexName}: " . $e->getMessage());
        return false;
    }
}

/**
 * Log message to file
 */
function logMessage(string $message, string $logFile = null): void
{
    if ($logFile === null) {
        $logFile = __DIR__ . '/../logs/cli.log';
    }

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Validate required PHP extensions
 */
function validateExtensions(array $required): bool
{
    $missing = [];

    foreach ($required as $extension) {
        if (!extension_loaded($extension)) {
            $missing[] = $extension;
        }
    }

    if (!empty($missing)) {
        displayError("Missing required PHP extensions: " . implode(', ', $missing));
        return false;
    }

    return true;
}

/**
 * Check if running in CLI mode
 */
function isCLI(): bool
{
    return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
}

/**
 * Ensure script is running in CLI mode
 */
function requireCLI(): void
{
    if (!isCLI()) {
        die("This script must be run from the command line.\n");
    }
}

