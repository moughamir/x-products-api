#!/usr/bin/env php
<?php
/**
 * Clean Expired Sessions Script
 *
 * This script removes expired admin sessions from the database.
 * Designed to be run as a cron job.
 *
 * Usage:
 *   php bin/clean-sessions.php
 *   php bin/clean-sessions.php --dry-run
 */

require __DIR__ . '/../vendor/autoload.php';

// Parse command-line arguments
$options = [
    'dry_run' => in_array('--dry-run', $argv),
    'help' => in_array('--help', $argv) || in_array('-h', $argv),
];

if ($options['help']) {
    echo <<<HELP

Clean Expired Sessions Script

Usage:
  php bin/clean-sessions.php [OPTIONS]

Options:
  --dry-run          Preview what would be deleted without actually deleting
  --help, -h         Show this help message

HELP;
    exit(0);
}

// Load configuration
$adminConfig = require __DIR__ . '/../config/admin.php';

echo "\n========================================\n";
echo "Clean Expired Sessions\n";
echo "========================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Mode: " . ($options['dry_run'] ? 'DRY RUN' : 'LIVE') . "\n";
echo "========================================\n\n";

try {
    $db = new PDO("sqlite:" . $adminConfig['admin_db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Count expired sessions
    $count = $db->query("
        SELECT COUNT(*) 
        FROM admin_sessions 
        WHERE expires_at < datetime('now')
    ")->fetchColumn();
    
    echo "→ Found {$count} expired sessions\n";
    
    if ($count === 0) {
        echo "  ✓ No cleanup needed\n\n";
        exit(0);
    }
    
    if ($options['dry_run']) {
        echo "\n→ Preview of sessions to be deleted:\n";
        $sessions = $db->query("
            SELECT s.id, s.user_id, u.username, s.expires_at
            FROM admin_sessions s
            LEFT JOIN admin_users u ON s.user_id = u.id
            WHERE s.expires_at < datetime('now')
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sessions as $session) {
            echo "  - Session {$session['id']} (User: {$session['username']}, Expired: {$session['expires_at']})\n";
        }
        
        if ($count > 10) {
            echo "  ... and " . ($count - 10) . " more\n";
        }
        
        echo "\nDRY RUN - No sessions deleted\n\n";
        exit(0);
    }
    
    // Delete expired sessions
    echo "\n→ Deleting expired sessions...\n";
    $deleted = $db->exec("
        DELETE FROM admin_sessions 
        WHERE expires_at < datetime('now')
    ");
    
    echo "  ✓ Deleted {$deleted} expired sessions\n";
    
    // Show remaining active sessions
    $active = $db->query("
        SELECT COUNT(*) 
        FROM admin_sessions 
        WHERE expires_at >= datetime('now')
    ")->fetchColumn();
    
    echo "\n→ Active sessions remaining: {$active}\n";
    
    echo "\n========================================\n";
    echo "✓ Cleanup Complete!\n";
    echo "========================================\n\n";
    
} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

exit(0);

