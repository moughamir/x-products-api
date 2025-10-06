#!/bin/bash
# Diagnostic Script for X-Products API
# Checks environment and identifies potential issues

echo "========================================="
echo "X-Products API - Environment Diagnostics"
echo "========================================="
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# --- System Information ---
echo "→ System Information"
echo "  User: $(whoami)"
echo "  Home: $HOME"
echo "  PWD: $(pwd)"
echo "  Hostname: $(hostname)"

# Detect environment type
if [[ "$HOME" == /home/u* ]] || [[ "$HOME" == /home/*/domains/* ]]; then
    echo "  Environment: Shared Hosting (detected)"
else
    echo "  Environment: Standard Server"
fi
echo ""

# --- PHP Information ---
echo "→ PHP Information"
php -v | head -n 1
echo "  PHP Binary: $(which php)"
echo "  Memory Limit: $(php -r 'echo ini_get("memory_limit");')"
echo "  Max Execution Time: $(php -r 'echo ini_get("max_execution_time");')"
echo "  OPcache: $(php -r 'echo extension_loaded("opcache") ? "Enabled" : "Disabled";')"
echo ""

# --- Required Extensions ---
echo "→ PHP Extensions"
required_extensions=("pdo" "pdo_sqlite" "json" "mbstring")
for ext in "${required_extensions[@]}"; do
    if php -m | grep -q "^${ext}$"; then
        echo "  ✓ $ext"
    else
        echo "  ✗ $ext (MISSING)"
    fi
done
echo ""

# --- Project Structure ---
echo "→ Project Structure"
required_dirs=("data" "src" "config" "bin" "migrations" "vendor")
for dir in "${required_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "  ✓ $dir/ ($(find "$dir" -type f | wc -l) files)"
    else
        echo "  ✗ $dir/ (MISSING)"
    fi
done
echo ""

# --- Required Files ---
echo "→ Required Files"
required_files=(
    "index.php"
    "composer.json"
    "bin/tackle.php"
    "bin/deploy.sh"
    "bin/prepare.sh"
    "vendor/autoload.php"
)
for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (MISSING)"
    fi
done
echo ""

# --- Database Files ---
echo "→ Database Files"
db_files=(
    "data/sqlite/products.sqlite"
    "data/sqlite/admin.sqlite"
)
for db in "${db_files[@]}"; do
    if [ -f "$db" ]; then
        size=$(du -h "$db" | cut -f1)
        echo "  ✓ $db ($size)"
    else
        echo "  ⚠️  $db (not created yet)"
    fi
done
echo ""

# --- Permissions Check ---
echo "→ File Permissions"
echo "  data/ directory: $(stat -c '%a' data/ 2>/dev/null || stat -f '%A' data/ 2>/dev/null || echo 'unknown')"
if [ -d "data/sqlite" ]; then
    echo "  data/sqlite/ directory: $(stat -c '%a' data/sqlite/ 2>/dev/null || stat -f '%A' data/sqlite/ 2>/dev/null || echo 'unknown')"
fi
if [ -f "data/sqlite/products.sqlite" ]; then
    echo "  products.sqlite: $(stat -c '%a' data/sqlite/products.sqlite 2>/dev/null || stat -f '%A' data/sqlite/products.sqlite 2>/dev/null || echo 'unknown')"
fi
echo ""

# --- Writable Directories ---
echo "→ Write Permissions Test"
test_dirs=("data" "data/sqlite" "data/cache" "data/logs" ".")
for dir in "${test_dirs[@]}"; do
    if [ -d "$dir" ]; then
        if touch "$dir/.write_test" 2>/dev/null; then
            rm "$dir/.write_test"
            echo "  ✓ $dir/ (writable)"
        else
            echo "  ✗ $dir/ (NOT writable)"
        fi
    else
        echo "  ⚠️  $dir/ (does not exist)"
    fi
done
echo ""

# --- Composer Check ---
echo "→ Composer Dependencies"
if [ -f "vendor/autoload.php" ]; then
    echo "  ✓ Composer dependencies installed"
    if [ -f "composer.lock" ]; then
        echo "  ✓ composer.lock present"
    else
        echo "  ⚠️  composer.lock missing"
    fi
else
    echo "  ✗ Composer dependencies NOT installed"
    echo "    Run: composer install"
fi
echo ""

# --- OpenAPI Check ---
echo "→ OpenAPI Generation"
if [ -f "vendor/bin/openapi" ]; then
    echo "  ✓ swagger-php installed"
else
    echo "  ✗ swagger-php NOT installed"
fi

if [ -f "openapi.json" ]; then
    size=$(du -h openapi.json | cut -f1)
    echo "  ✓ openapi.json exists ($size)"
    if [ "$size" = "0" ] || [ "$size" = "0B" ]; then
        echo "    ⚠️  File is empty - regenerate with: php bin/generate-openapi.php src -o openapi.json --format json"
    fi
else
    echo "  ⚠️  openapi.json not generated yet"
fi
echo ""

# --- Disk Space ---
echo "→ Disk Space"
df -h . | tail -n 1 | awk '{print "  Total: "$2", Used: "$3", Available: "$4", Use%: "$5}'
echo ""

# --- Process Limits ---
echo "→ Process Information"
if command -v ulimit &> /dev/null; then
    echo "  Max processes: $(ulimit -u)"
    echo "  Max open files: $(ulimit -n)"
else
    echo "  ⚠️  ulimit not available"
fi
echo ""

# --- Cron Jobs ---
echo "→ Cron Jobs"
if crontab -l &> /dev/null; then
    cron_count=$(crontab -l 2>/dev/null | grep -v '^#' | grep -v '^$' | wc -l)
    echo "  Configured cron jobs: $cron_count"
    if [ $cron_count -gt 0 ]; then
        echo "  Run 'crontab -l' to view"
    fi
else
    echo "  ⚠️  No cron jobs configured"
fi
echo ""

# --- Recent Logs ---
echo "→ Recent Logs"
if [ -f "logs/cron.log" ]; then
    lines=$(wc -l < logs/cron.log)
    echo "  logs/cron.log: $lines lines"
    if [ $lines -gt 0 ]; then
        echo "  Last entry: $(tail -n 1 logs/cron.log)"
    fi
else
    echo "  ⚠️  logs/cron.log not found"
fi
echo ""

# --- Common Issues Check ---
echo "→ Common Issues Check"
issues_found=0

# Check 1: Vendor directory
if [ ! -d "vendor" ]; then
    echo "  ✗ Vendor directory missing - run: composer install"
    issues_found=$((issues_found + 1))
fi

# Check 2: Data directory writable
if ! touch data/.write_test 2>/dev/null; then
    echo "  ✗ Data directory not writable - check permissions"
    issues_found=$((issues_found + 1))
else
    rm data/.write_test
fi

# Check 3: PHP version
php_version=$(php -r 'echo PHP_VERSION;')
if [[ "$php_version" < "8.2" ]]; then
    echo "  ✗ PHP version too old ($php_version) - requires 8.2+"
    issues_found=$((issues_found + 1))
fi

# Check 4: Required extensions
for ext in "${required_extensions[@]}"; do
    if ! php -m | grep -q "^${ext}$"; then
        echo "  ✗ Missing PHP extension: $ext"
        issues_found=$((issues_found + 1))
    fi
done

if [ $issues_found -eq 0 ]; then
    echo "  ✓ No common issues detected"
fi
echo ""

# --- Recommendations ---
echo "========================================="
echo "Recommendations"
echo "========================================="

if [ ! -f "vendor/autoload.php" ]; then
    echo "1. Install dependencies: composer install"
fi

if [ ! -f "data/sqlite/products.sqlite" ]; then
    echo "2. Run migrations to create databases"
fi

if [ ! -f "openapi.json" ]; then
    echo "3. Generate OpenAPI docs: php bin/generate-openapi.php src -o openapi.json --format json"
fi

if ! crontab -l &> /dev/null || [ $(crontab -l 2>/dev/null | grep -v '^#' | grep -v '^$' | wc -l) -eq 0 ]; then
    echo "4. Setup cron jobs: bash bin/setup-cron-jobs.sh"
fi

echo ""
echo "========================================="
echo "Diagnostic Complete"
echo "========================================="
echo ""
echo "To deploy, run: bash bin/deploy.sh -f"
echo ""

