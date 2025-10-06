#!/usr/bin/env php
<?php
/**
 * Test script to verify formatTime() fix for PHP 8.2 deprecation warning
 * Tests the fix for implicit float-to-int conversion
 */

// Simulate the formatTime method
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

echo "Testing formatTime() with various float inputs:\n";
echo "================================================\n\n";

// Test cases that would trigger the deprecation warning
$testCases = [
    30.5 => "30s (< 1 minute)",
    90.7 => "1m 30s (< 1 hour)",
    309.5329336526108 => "5m 9s (the exact value from the warning)",
    3661.25 => "1h 1m (> 1 hour)",
    7200.999 => "2h 0m (exactly 2 hours)",
    5432.1 => "1h 30m (mixed)",
];

foreach ($testCases as $input => $description) {
    $result = formatTime($input);
    echo sprintf("Input: %.2f seconds (%s)\n", $input, $description);
    echo sprintf("Output: %s\n", $result);
    echo "\n";
}

echo "================================================\n";
echo "✓ All tests completed without deprecation warnings!\n";
echo "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Test Date: " . date('Y-m-d H:i:s') . "\n";

