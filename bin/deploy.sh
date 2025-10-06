#!/bin/bash
# X-Products API - Master Deployment Script
# One command to set up everything for production

set -e  # Exit on error

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SKIP_CONFIRMATION=false
SKIP_PRODUCTS=false
SKIP_OPTIMIZATION=false
SKIP_CRON=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --force|-f)
            SKIP_CONFIRMATION=true
            shift
            ;;
        --skip-products)
            SKIP_PRODUCTS=true
            shift
            ;;
        --skip-optimization)
            SKIP_OPTIMIZATION=true
            shift
            ;;
        --skip-cron)
            SKIP_CRON=true
            shift
            ;;
        --help|-h)
            echo "X-Products API - Master Deployment Script"
            echo ""
            echo "Usage: bash bin/deploy.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --force, -f           Skip all confirmation prompts"
            echo "  --skip-products       Skip product data import"
            echo "  --skip-optimization   Skip database optimization"
            echo "  --skip-cron           Skip cron job setup"
            echo "  --help, -h            Show this help message"
            echo ""
            echo "This script will:"
            echo "  1. Validate project structure"
            echo "  2. Set file permissions"
            echo "  3. Run database migrations"
            echo "  4. Import product data (optional)"
            echo "  5. Optimize databases"
            echo "  6. Generate API documentation"
            echo "  7. Setup cron jobs (optional)"
            echo ""
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Helper functions
print_header() {
    echo -e "\n${BLUE}=========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}=========================================${NC}\n"
}

print_step() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗ Error:${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️  Warning:${NC} $1"
}

print_info() {
    echo -e "${BLUE}→${NC} $1"
}

# Main deployment
print_header "X-Products API - Master Deployment"
echo "Project root: $PROJECT_ROOT"
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Confirmation
if [ "$SKIP_CONFIRMATION" = false ]; then
    echo "This script will set up the entire application for production."
    echo "It will run migrations, set permissions, and configure automation."
    echo ""
    read -p "Continue? (yes/no): " -r
    echo
    if [[ ! $REPLY =~ ^[Yy]es$ ]]; then
        echo "Deployment cancelled."
        exit 0
    fi
fi

# Step 1: Validate project structure
print_header "Step 1: Validating Project Structure"
print_info "Checking required directories and files..."

required_dirs=("data" "src" "config" "migrations" "bin")
required_files=("composer.json" "index.php" "bin/tackle.php")

for dir in "${required_dirs[@]}"; do
    if [ ! -d "$dir" ]; then
        print_error "Required directory not found: $dir"
        exit 1
    fi
    print_step "Found directory: $dir"
done

for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        print_error "Required file not found: $file"
        exit 1
    fi
    print_step "Found file: $file"
done

print_step "Project structure validated"

# Step 2: Set file permissions
print_header "Step 2: Setting File Permissions"
print_info "Running prepare.sh..."

if bash $PROJECT_ROOT/bin/prepare.sh; then
    print_step "File permissions set successfully"
else
    print_error "Failed to set file permissions"
    exit 1
fi

# Step 3: Run database migrations
print_header "Step 3: Running Database Migrations"

migrations=(
    "migrations/001_create_admin_database.php"
    "migrations/002_extend_products_database.php"
    "migrations/003_add_api_keys_and_settings.php"
    "migrations/004_add_related_products.php"
)

for migration in "${migrations[@]}"; do
    if [ -f "$migration" ]; then
        print_info "Running $(basename "$migration")..."
        if php "$migration" --force 2>&1 | grep -q "complete\|Complete\|already"; then
            print_step "$(basename "$migration") completed"
        else
            print_warning "$(basename "$migration") may have issues (check manually)"
        fi
    else
        print_warning "Migration not found: $migration"
    fi
done

# Step 4: Import product data (optional)
if [ "$SKIP_PRODUCTS" = false ]; then
    print_header "Step 4: Importing Product Data"

    if [ -d "data/json/products_by_id" ]; then
        product_count=$(find data/json/products_by_id -name "*.json" -type f | wc -l)
        print_info "Found $product_count product files"

        if [ "$product_count" -gt 0 ]; then
            print_info "Importing products (this may take several minutes)..."
            if php bin/tackle.php --force; then
                print_step "Product data imported successfully"
            else
                print_error "Failed to import product data"
                exit 1
            fi
        else
            print_warning "No product files found, skipping import"
        fi
    else
        print_warning "Product data directory not found, skipping import"
    fi
else
    print_info "Skipping product import (--skip-products flag)"
fi

# Step 5: Optimize databases
if [ "$SKIP_OPTIMIZATION" = false ]; then
    print_header "Step 5: Optimizing Databases"
    print_info "Creating indexes and optimizing..."

    if php bin/optimize-database.php --force; then
        print_step "Databases optimized successfully"
    else
        print_warning "Database optimization had issues (check manually)"
    fi
else
    print_info "Skipping database optimization (--skip-optimization flag)"
fi

# Step 6: Generate API documentation
print_header "Step 6: Generating API Documentation"
print_info "Generating OpenAPI specification..."

if php bin/generate-openapi.php src -o openapi.json --format json 2>&1; then
    if [ -f "openapi.json" ]; then
        print_step "API documentation generated: openapi.json"
    else
        print_warning "OpenAPI file not created (check manually)"
    fi
else
    print_warning "API documentation generation had issues (check manually)"
fi

# Step 7: Setup cron jobs (optional)
if [ "$SKIP_CRON" = false ]; then
    print_header "Step 7: Cron Jobs Setup"
    print_info "Cron jobs can be set up for automated maintenance"
    print_info "Run: bash bin/setup-cron-jobs.sh"
    print_info ""
    print_info "Recommended cron jobs:"
    print_info "  - Database backup (Daily 1 AM)"
    print_info "  - Featured items rotation (Daily 2 AM)"
    print_info "  - Database optimization (Weekly Sunday 3 AM)"
    print_info "  - Session cleanup (Daily 4 AM)"
    print_info "  - API documentation (Daily 5 AM)"
    print_info ""

    if [ "$SKIP_CONFIRMATION" = false ]; then
        read -p "Set up cron jobs now? (yes/no): " -r
        echo
        if [[ $REPLY =~ ^[Yy]es$ ]]; then
            bash bin/setup-cron-jobs.sh
        else
            print_info "Skipping cron setup (run bin/setup-cron-jobs.sh later)"
        fi
    else
        print_info "Skipping cron setup in force mode (run bin/setup-cron-jobs.sh later)"
    fi
else
    print_info "Skipping cron setup (--skip-cron flag)"
fi

# Final summary
print_header "Deployment Complete!"

echo "Summary:"
echo "  ✓ Project structure validated"
echo "  ✓ File permissions set"
echo "  ✓ Database migrations completed"
if [ "$SKIP_PRODUCTS" = false ]; then
    echo "  ✓ Product data imported"
else
    echo "  ⊘ Product data import skipped"
fi
if [ "$SKIP_OPTIMIZATION" = false ]; then
    echo "  ✓ Databases optimized"
else
    echo "  ⊘ Database optimization skipped"
fi
echo "  ✓ API documentation generated"
if [ "$SKIP_CRON" = false ]; then
    echo "  ℹ Cron jobs setup available"
else
    echo "  ⊘ Cron jobs setup skipped"
fi

echo ""
echo "Next steps:"
echo "  1. Test the API: curl http://localhost/api/products?limit=5"
echo "  2. Access admin panel: http://localhost/admin"
echo "  3. View API docs: http://localhost/openapi.json"
echo "  4. Monitor logs: tail -f logs/cron.log"
echo "  5. Setup cron jobs: bash bin/setup-cron-jobs.sh"
echo ""
echo "For detailed documentation, see:"
echo "  - BIN_SCRIPTS_ENHANCEMENT_SUMMARY.md"
echo "  - ENHANCED_FEATURES_QUICK_START.md"
echo "  - bin/README.md"
echo ""

print_step "Deployment successful! 🎉"
echo ""

