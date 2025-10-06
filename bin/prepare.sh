#!/bin/bash
# X-Products API Deployment Preparation Script
# This script prepares the application for production deployment
# Optimized for shared hosting environments (Hostinger, etc.)

# Don't exit on error - we'll handle errors gracefully
# set -e  # Commented out for shared hosting compatibility

# --- Configuration Variables ---
CLI_USER=$(whoami)
DB_FILE="data/sqlite/products.sqlite"
ADMIN_DB_FILE="data/sqlite/admin.sqlite"
OPENAPI_FILE="openapi.json"
SHARED_HOSTING=false
ERROR_COUNT=0
WARNING_COUNT=0
# -------------------------------

# Detect shared hosting environment
if [[ "$HOME" == /home/u* ]] || [[ "$HOME" == /home/*/domains/* ]]; then
    SHARED_HOSTING=true
    echo "ℹ️  Shared hosting environment detected"
fi

echo "========================================="
echo "X-Products API Deployment Preparation"
echo "========================================="
echo "CLI User (Owner): ${CLI_USER}"
echo "Environment: $([ "$SHARED_HOSTING" = true ] && echo "Shared Hosting" || echo "Standard")"
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
    # Run OpenAPI generation and capture output
    if php bin/generate-openapi.php src -o "$OPENAPI_FILE" --format json 2>&1; then
        if [ -f "$OPENAPI_FILE" ]; then
            if chmod 644 "$OPENAPI_FILE" 2>/dev/null; then
                echo "✓ OpenAPI specification generated: $OPENAPI_FILE"
            else
                echo "⚠️  Warning: OpenAPI generated but could not set permissions"
                WARNING_COUNT=$((WARNING_COUNT + 1))
            fi
        else
            echo "⚠️  Warning: OpenAPI generation completed but file not found"
            WARNING_COUNT=$((WARNING_COUNT + 1))
        fi
    else
        echo "⚠️  Warning: OpenAPI generation failed (continuing anyway)"
        WARNING_COUNT=$((WARNING_COUNT + 1))
    fi
else
    echo "⚠️  Warning: bin/generate-openapi.php not found, skipping OpenAPI generation"
    WARNING_COUNT=$((WARNING_COUNT + 1))
fi

# --- 3. Set Ownership and Permissions for Data Directory ---
echo ""
echo "→ Step 3: Setting ownership and permissions for data directory..."

# Skip chown on shared hosting (will always fail)
if [ "$SHARED_HOSTING" = false ]; then
    if chown -R "${CLI_USER}" data/ 2>/dev/null; then
        echo "✓ Data directory ownership set to ${CLI_USER}"
    else
        echo "⚠️  Could not change ownership (may require sudo or running on shared hosting)"
        WARNING_COUNT=$((WARNING_COUNT + 1))
    fi
else
    echo "ℹ️  Skipping ownership change (shared hosting environment)"
fi

# Set permissions with error handling
if chmod -R 775 data/ 2>/dev/null; then
    echo "✓ Data directory permissions set to 775"
else
    echo "⚠️  Warning: Could not set all data directory permissions"
    WARNING_COUNT=$((WARNING_COUNT + 1))
    # Try to set at least the main directory
    chmod 775 data/ 2>/dev/null || true
fi

# --- 4. Set Permissions for Database Files ---
echo ""
echo "→ Step 4: Setting permissions for database files..."
db_count=0
for db_file in "$DB_FILE" "$ADMIN_DB_FILE"; do
    if [ -f "$db_file" ]; then
        if chmod 664 "$db_file" 2>/dev/null; then
            echo "✓ Set permissions for $db_file to 664"
            db_count=$((db_count + 1))
        else
            echo "⚠️  Warning: Could not set permissions for $db_file"
            WARNING_COUNT=$((WARNING_COUNT + 1))
        fi
    fi
done

# Set permissions for database directory
if [ -d "data/sqlite" ]; then
    if chmod 775 data/sqlite 2>/dev/null; then
        echo "✓ Set permissions for data/sqlite/ to 775"
    else
        echo "⚠️  Warning: Could not set permissions for data/sqlite/"
        WARNING_COUNT=$((WARNING_COUNT + 1))
    fi
fi

# --- 5. Set Execute Permissions for Scripts ---
echo ""
echo "→ Step 5: Setting execute permissions for scripts..."
if find bin/ -type f -name "*.sh" -exec chmod 755 {} \; 2>/dev/null && \
   find bin/ -type f -name "*.php" -exec chmod 755 {} \; 2>/dev/null; then
    echo "✓ Script permissions set to 755"
else
    echo "⚠️  Warning: Could not set all script permissions"
    WARNING_COUNT=$((WARNING_COUNT + 1))
    # Try individual files as fallback
    chmod 755 bin/*.sh 2>/dev/null || true
    chmod 755 bin/*.php 2>/dev/null || true
fi

# --- 6. Set Permissions for Source Code ---
echo ""
echo "→ Step 6: Setting permissions for source code..."
perm_success=true

if ! find src/ config/ -type d -exec chmod 755 {} \; 2>/dev/null; then
    echo "⚠️  Warning: Could not set all directory permissions"
    WARNING_COUNT=$((WARNING_COUNT + 1))
    perm_success=false
fi

if ! find src/ config/ -type f -exec chmod 644 {} \; 2>/dev/null; then
    echo "⚠️  Warning: Could not set all file permissions"
    WARNING_COUNT=$((WARNING_COUNT + 1))
    perm_success=false
fi

if [ -f "index.php" ]; then
    chmod 644 index.php 2>/dev/null || true
fi

if [ "$perm_success" = true ]; then
    echo "✓ Source code permissions set (directories: 755, files: 644)"
else
    echo "ℹ️  Some permissions could not be set (may be normal on shared hosting)"
fi

# --- 7. Set Permissions for OpenAPI File ---
echo ""
echo "→ Step 7: Setting permissions for OpenAPI file..."
if [ -f "$OPENAPI_FILE" ]; then
    if chmod 644 "$OPENAPI_FILE" 2>/dev/null; then
        echo "✓ OpenAPI file permissions set"
    else
        echo "⚠️  Warning: Could not set OpenAPI file permissions"
        WARNING_COUNT=$((WARNING_COUNT + 1))
    fi
else
    echo "ℹ️  OpenAPI file not found (may not have been generated)"
fi

# --- 8. Create Required Directories ---
echo ""
echo "→ Step 8: Creating required directories..."
if mkdir -p data/sqlite data/cache data/logs 2>/dev/null; then
    echo "✓ Required directories created"
    # Try to set permissions, but don't fail if it doesn't work
    chmod 775 data/sqlite data/cache data/logs 2>/dev/null || {
        echo "⚠️  Warning: Could not set permissions on all directories"
        WARNING_COUNT=$((WARNING_COUNT + 1))
    }
else
    echo "⚠️  Warning: Could not create all required directories"
    WARNING_COUNT=$((WARNING_COUNT + 1))
fi

# --- Final Summary ---
echo ""
echo "========================================="
if [ $ERROR_COUNT -eq 0 ]; then
    echo "✓ Deployment Preparation Complete!"
else
    echo "⚠️  Deployment Preparation Completed with Errors"
fi
echo "========================================="
echo "Summary:"
echo "  Errors: $ERROR_COUNT"
echo "  Warnings: $WARNING_COUNT"
if [ "$SHARED_HOSTING" = true ]; then
    echo "  Environment: Shared Hosting"
    echo ""
    echo "ℹ️  Note: Some permission warnings are normal on shared hosting"
    echo "  as certain operations require elevated privileges."
fi
echo "========================================="
echo ""
echo "Next steps:"
echo "  1. Run migrations: php migrations/001_create_admin_database.php"
echo "  2. Run migrations: php migrations/002_extend_products_database.php"
echo "  3. Run migrations: php migrations/003_add_api_keys_and_settings.php"
echo "  4. Import products: php bin/tackle.php --force"
echo "  5. Test the API endpoints"
echo "========================================="

# Exit with appropriate code
if [ $ERROR_COUNT -gt 0 ]; then
    exit 1
else
    exit 0
fi
