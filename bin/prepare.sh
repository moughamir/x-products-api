#!/bin/bash
# X-Products API Deployment Preparation Script
# This script prepares the application for production deployment

set -e  # Exit on error

# --- Configuration Variables ---
CLI_USER=$(whoami)
DB_FILE="data/sqlite/products.sqlite"
ADMIN_DB_FILE="data/sqlite/admin.sqlite"
OPENAPI_FILE="openapi.json"
# -------------------------------

echo "========================================="
echo "X-Products API Deployment Preparation"
echo "========================================="
echo "CLI User (Owner): ${CLI_USER}"
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="

# --- 1. Validate Project Structure ---
echo ""
echo "→ Step 1: Validating project structure..."
if [ ! -d "data" ] || [ ! -d "src" ] || [ ! -f "bin/tackle.php" ]; then
    echo "❌ ERROR: Cannot find required directories (data/, src/) or file (bin/tackle.php)."
    echo "Please run this script from the root directory of your project."
    exit 1
fi
echo "✓ Project structure validated"


# --- 2. Generate OpenAPI Specification ---
echo ""
echo "→ Step 2: Generating OpenAPI specification..."
if [ -f "bin/generate-openapi.php" ]; then
    php bin/generate-openapi.php src -o "$OPENAPI_FILE" --format json
    if [ -f "$OPENAPI_FILE" ]; then
        chmod 644 "$OPENAPI_FILE"
        echo "✓ OpenAPI specification generated: $OPENAPI_FILE"
    else
        echo "⚠️  Warning: OpenAPI generation completed but file not found"
    fi
else
    echo "⚠️  Warning: bin/generate-openapi.php not found, skipping OpenAPI generation"
fi

# --- 3. Set Ownership and Permissions for Data Directory ---
echo ""
echo "→ Step 3: Setting ownership and permissions for data directory..."
chown -R "${CLI_USER}" data/ 2>/dev/null || echo "⚠️  Could not change ownership (may require sudo)"
chmod -R 775 data/
echo "✓ Data directory permissions set to 775"

# --- 4. Set Permissions for Database Files ---
echo ""
echo "→ Step 4: Setting permissions for database files..."
for db_file in "$DB_FILE" "$ADMIN_DB_FILE"; do
    if [ -f "$db_file" ]; then
        chmod 664 "$db_file"
        echo "✓ Set permissions for $db_file to 664"
    fi
done

# Set permissions for database directory
if [ -d "data/sqlite" ]; then
    chmod 775 data/sqlite
    echo "✓ Set permissions for data/sqlite/ to 775"
fi

# --- 5. Set Execute Permissions for Scripts ---
echo ""
echo "→ Step 5: Setting execute permissions for scripts..."
find bin/ -type f -name "*.sh" -exec chmod 755 {} \;
find bin/ -type f -name "*.php" -exec chmod 755 {} \;
echo "✓ Script permissions set to 755"

# --- 6. Set Permissions for Source Code ---
echo ""
echo "→ Step 6: Setting permissions for source code..."
find src/ config/ -type d -exec chmod 755 {} \; 2>/dev/null
find src/ config/ -type f -exec chmod 644 {} \; 2>/dev/null
if [ -f "index.php" ]; then
    chmod 644 index.php
fi
echo "✓ Source code permissions set (directories: 755, files: 644)"

# --- 7. Set Permissions for OpenAPI File ---
echo ""
echo "→ Step 7: Setting permissions for OpenAPI file..."
if [ -f "$OPENAPI_FILE" ]; then
    chmod 644 "$OPENAPI_FILE"
    echo "✓ OpenAPI file permissions set"
fi

# --- 8. Create Required Directories ---
echo ""
echo "→ Step 8: Creating required directories..."
mkdir -p data/sqlite data/cache data/logs
chmod 775 data/sqlite data/cache data/logs 2>/dev/null
echo "✓ Required directories created"

echo ""
echo "========================================="
echo "✓ Deployment Preparation Complete!"
echo "========================================="
echo "Next steps:"
echo "  1. Run migrations: php migrations/001_create_admin_database.php"
echo "  2. Run migrations: php migrations/002_extend_products_database.php"
echo "  3. Run migrations: php migrations/003_add_api_keys_and_settings.php"
echo "  4. Import products: php bin/tackle.php --force"
echo "  5. Test the API endpoints"
echo "========================================="
