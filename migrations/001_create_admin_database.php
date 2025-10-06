#!/usr/bin/env php
<?php
/**
 * Admin Database Migration - Initial Setup
 *
 * Creates the admin.sqlite database with all necessary tables for
 * user authentication, roles, permissions, sessions, and activity logging.
 *
 * Usage:
 *   php migrations/001_create_admin_database.php
 *   php migrations/001_create_admin_database.php --force  # Drop existing tables
 */

require __DIR__ . '/../vendor/autoload.php';

// Parse command-line arguments
$force = in_array('--force', $argv);

// Database path
$dbPath = __DIR__ . '/../data/sqlite/admin.sqlite';
$dbDir = dirname($dbPath);

echo "\n========================================\n";
echo "Admin Database Migration - Initial Setup\n";
echo "========================================\n";
echo "Database: {$dbPath}\n";
echo "Force mode: " . ($force ? 'YES' : 'NO') . "\n";
echo "========================================\n\n";

// Create directory if it doesn't exist
if (!is_dir($dbDir)) {
    echo "→ Creating database directory...\n";
    mkdir($dbDir, 0755, true);
}

// Check if database exists
$dbExists = file_exists($dbPath);
if ($dbExists && !$force) {
    echo "⚠️  Database already exists!\n";
    echo "Use --force flag to drop and recreate all tables.\n";
    echo "WARNING: This will delete all admin data!\n\n";
    exit(1);
}

try {
    // Connect to database
    echo "→ Connecting to database...\n";
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop existing tables if force mode
    if ($force) {
        echo "→ Dropping existing tables (--force mode)...\n";
        $db->exec("DROP TABLE IF EXISTS password_reset_tokens");
        $db->exec("DROP TABLE IF EXISTS api_keys");
        $db->exec("DROP TABLE IF EXISTS admin_activity_log");
        $db->exec("DROP TABLE IF EXISTS admin_sessions");
        $db->exec("DROP TABLE IF EXISTS admin_users");
        $db->exec("DROP TABLE IF EXISTS admin_roles");
    }

    // Create admin_roles table
    echo "→ Creating admin_roles table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            permissions TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create admin_users table
    echo "→ Creating admin_users table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            role_id INTEGER NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            last_login_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES admin_roles(id)
        )
    ");

    // Create admin_sessions table
    echo "→ Creating admin_sessions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_sessions (
            session_id VARCHAR(64) PRIMARY KEY,
            user_id INTEGER NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            last_activity_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
        )
    ");

    // Create admin_activity_log table
    echo "→ Creating admin_activity_log table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            resource VARCHAR(100),
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
        )
    ");

    // Create api_keys table
    echo "→ Creating api_keys table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_hash VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_by INTEGER,
            is_active BOOLEAN DEFAULT 1,
            last_used_at DATETIME,
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES admin_users(id)
        )
    ");

    // Create password_reset_tokens table
    echo "→ Creating password_reset_tokens table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
        )
    ");

    // Create indexes
    echo "→ Creating indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_sessions_user_id ON admin_sessions(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_sessions_expires_at ON admin_sessions(expires_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_activity_log_user_id ON admin_activity_log(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_activity_log_created_at ON admin_activity_log(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_key_hash ON api_keys(key_hash)");

    // Insert default roles
    echo "→ Inserting default roles...\n";
    $roles = [
        [
            'name' => 'super_admin',
            'description' => 'Full system access including user management and API keys',
            'permissions' => json_encode([
                'users' => ['create', 'read', 'update', 'delete'],
                'products' => ['create', 'read', 'update', 'delete'],
                'collections' => ['create', 'read', 'update', 'delete'],
                'categories' => ['create', 'read', 'update', 'delete'],
                'tags' => ['create', 'read', 'update', 'delete'],
                'images' => ['upload', 'delete'],
                'api_keys' => ['create', 'read', 'revoke'],
                'settings' => ['read', 'update']
            ])
        ],
        [
            'name' => 'admin',
            'description' => 'Product and content management (no user management)',
            'permissions' => json_encode([
                'products' => ['create', 'read', 'update', 'delete'],
                'collections' => ['create', 'read', 'update', 'delete'],
                'categories' => ['create', 'read', 'update', 'delete'],
                'tags' => ['create', 'read', 'update', 'delete'],
                'images' => ['upload', 'delete']
            ])
        ],
        [
            'name' => 'editor',
            'description' => 'Edit products and content (no delete)',
            'permissions' => json_encode([
                'products' => ['read', 'update'],
                'collections' => ['read'],
                'categories' => ['read'],
                'tags' => ['read', 'update'],
                'images' => ['upload']
            ])
        ],
        [
            'name' => 'viewer',
            'description' => 'Read-only access to all content',
            'permissions' => json_encode([
                'products' => ['read'],
                'collections' => ['read'],
                'categories' => ['read'],
                'tags' => ['read']
            ])
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO admin_roles (name, description, permissions)
        VALUES (:name, :description, :permissions)
    ");

    foreach ($roles as $role) {
        $stmt->execute($role);
        echo "  ✓ Created role: {$role['name']}\n";
    }

    // Create default super admin user
    echo "→ Creating default super admin user...\n";
    $defaultPassword = 'admin123';
    $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->exec("
        INSERT INTO admin_users (username, email, password_hash, full_name, role_id)
        VALUES (
            'admin',
            'admin@cosmos.local',
            '{$passwordHash}',
            'System Administrator',
            1
        )
    ");

    echo "\n========================================\n";
    echo "✓ Admin Database Created Successfully!\n";
    echo "========================================\n\n";

    echo "Default Admin Credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: {$defaultPassword}\n";
    echo "  Email: admin@cosmos.local\n\n";

    echo "⚠️  IMPORTANT SECURITY NOTICE:\n";
    echo "  1. Change the default password immediately!\n";
    echo "  2. Create a new super admin user\n";
    echo "  3. Delete or disable the default 'admin' user\n\n";

    echo "Database Location:\n";
    echo "  {$dbPath}\n\n";

    echo "Tables Created:\n";
    echo "  - admin_roles (4 default roles)\n";
    echo "  - admin_users (1 default user)\n";
    echo "  - admin_sessions\n";
    echo "  - admin_activity_log\n";
    echo "  - api_keys\n";
    echo "  - password_reset_tokens\n\n";

    echo "Next Steps:\n";
    echo "  1. Access admin dashboard: http://localhost:8080/admin/login\n";
    echo "  2. Login with default credentials\n";
    echo "  3. Change password in user settings\n";
    echo "  4. Create additional admin users as needed\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

exit(0);

