# Quick Start Guide - Enhanced Features

This guide helps you quickly get started with the newly enhanced features of the X-Products API.

## 🚀 Initial Setup (First Time)

### Step 1: Prepare for Deployment

```bash
# Run the preparation script
bash bin/prepare.sh
```

This will:

- Set proper file permissions
- Generate OpenAPI documentation
- Create required directories
- Validate project structure

### Step 2: Run Migrations

```bash
# Create admin database
php migrations/001_create_admin_database.php

# Extend products database
php migrations/002_extend_products_database.php

# Add API keys and settings
php migrations/003_add_api_keys_and_settings.php

# Add related products support
php migrations/004_add_related_products.php
```

### Step 3: Import Product Data

```bash
# Import products from JSON files
php bin/tackle.php --force
```

### Step 4: Optimize Database

```bash
# Create indexes and optimize
php bin/optimize-database.php --force
```

### Step 5: Setup Automation

```bash
# Configure cron jobs
bash bin/setup-cron-jobs.sh
```

---

## 📊 Using Data Analysis

### Analyze Product Data

```bash
# Export all products with default fields
./bin/analyze-data.sh

# Export specific fields
./bin/analyze-data.sh --fields id,title,price,vendor,rating

# Export to custom file
./bin/analyze-data.sh --fields id,title,tags --output tagged-products.csv

# Analyze pricing
./bin/analyze-data.sh --fields id,title,price,compare_at_price --output pricing.csv
```

### Available Fields

**Basic Information:**

- `id`, `title`, `handle`, `body_html`, `vendor`, `product_type`

**Dates:**

- `created_at`, `updated_at`

**Pricing:**

- `price`, `compare_at_price`

**Metadata:**

- `tags`, `has_variants`, `has_images`, `variant_count`, `image_count`

---

## 🎯 Featured Items Management

### Manual Rotation

```bash
# Rotate with default settings (20 items)
php bin/rotate-featured-items.php

# Custom number of featured items
php bin/rotate-featured-items.php --count 30

# Higher quality threshold
php bin/rotate-featured-items.php --min-rating 4.5 --min-reviews 50

# Preview changes without applying
php bin/rotate-featured-items.php --dry-run
```

### Automated Rotation

The cron job automatically rotates featured items daily at 2 AM.

**Automated Schedule:**

- **Featured Items Rotation:** Daily at 2:00 AM
- **Database Backup:** Monthly (1st of month) at 1:00 AM
- **Database Optimization:** Weekly (Sunday) at 3:00 AM
- **Session Cleanup:** Daily at 4:00 AM
- **API Documentation:** Daily at 5:00 AM

To check the schedule:

```bash
crontab -l
```

---

## 🔗 Related Products

### Using the Service

```php
use App\Services\RelatedProductsService;

// Initialize service
$relatedService = new RelatedProductsService($db);

// Get related products for a specific product
$relatedProducts = $relatedService->getRelatedProducts(
    productId: 123,
    limit: 12
);

// Get suggested products (trending)
$trending = $relatedService->getSuggestedProducts(
    limit: 12,
    options: ['strategy' => 'trending']
);

// Get bestsellers
$bestsellers = $relatedService->getSuggestedProducts(
    limit: 12,
    options: ['strategy' => 'bestsellers']
);

// Get mixed suggestions
$mixed = $relatedService->getSuggestedProducts(
    limit: 12,
    options: ['strategy' => 'mixed']
);
```

### Suggestion Strategies

- **`trending`** - High-rated products with many reviews (rating ≥ 4.5, reviews ≥ 50)
- **`bestsellers`** - Products with highest bestseller scores
- **`high_rated`** - Products with rating ≥ 4.0 and reviews ≥ 10
- **`new`** - Recently added products
- **`mixed`** - Combination of all strategies

### How Related Products Work

The service uses multiple factors to find related products:

1. **Same Collections** (weight: 3.0) - Products in the same collections
2. **Similar Tags** (weight: 2.0) - Products with matching tags
3. **Same Product Type** (weight: 2.5) - Products of the same type
4. **Same Vendor** (weight: 1.5) - Products from the same vendor
5. **Similar Price** (weight: 1.0) - Products within ±30% price range

**Bonus Scoring:**

- High rating (≥4.5): +0.5
- Many reviews (≥50): +0.3

---

## 🗄️ Database Optimization

### Manual Optimization

```bash
# Optimize all databases
php bin/optimize-database.php

# Optimize specific database
php bin/optimize-database.php --products
php bin/optimize-database.php --admin

# Skip confirmation
php bin/optimize-database.php --force
```

### What Gets Optimized

1. **Indexes Created:**

   - Products: handle, vendor, type, price, stock, rating, bestseller
   - Images: product_id, position
   - Collections, categories, tags: All lookup fields
   - Junction tables: All foreign keys

2. **FTS5 Tables:**

   - Optimized for faster full-text search

3. **Database Maintenance:**
   - VACUUM (reclaim space)
   - ANALYZE (update statistics)
   - WAL mode enabled

### Expected Results

- **Query Speed:** 2-5x faster
- **Database Size:** 10-30% smaller
- **Concurrency:** 3-10x more users

---

## 🔄 Automated Tasks

### View Cron Jobs

```bash
crontab -l
```

### Monitor Logs

```bash
# Watch cron log in real-time
tail -f logs/cron.log

# View recent entries
tail -n 50 logs/cron.log

# Search for errors
grep -i error logs/cron.log
```

### Manual Task Execution

```bash
# Run any automated task manually
php bin/rotate-featured-items.php
php bin/clean-sessions.php
php bin/optimize-database.php --force
bash bin/backup-databases.sh
```

---

## 💾 Backups

### View Backups

```bash
ls -lh backups/
```

### Restore from Backup

```bash
# Stop the application first
# Then restore:
gunzip -c backups/products_20251006_020000.sqlite.gz > data/sqlite/products.sqlite
gunzip -c backups/admin_20251006_020000.sqlite.gz > data/sqlite/admin.sqlite

# Restart the application
```

### Manual Backup

```bash
bash bin/backup-databases.sh
```

---

## 📚 API Documentation

### Generate Documentation

```bash
# Generate JSON format
php bin/generate-openapi.php src -o openapi.json --format json

# Generate YAML format
php bin/generate-openapi.php src -o openapi.yaml --format yaml
```

### View Documentation

Open in browser:

- JSON: `https://your-domain.com/openapi.json`
- Swagger UI: Use any OpenAPI viewer with the JSON URL

---

## 🧪 Testing

### Test Scripts Before Production

```bash
# Test with dry-run mode
php bin/rotate-featured-items.php --dry-run
php bin/clean-sessions.php --dry-run

# Test data analysis
./bin/analyze-data.sh --fields id,title --output test.csv

# Verify output
head test.csv
```

### Verify Database Optimization

```bash
# Before optimization
du -sh data/sqlite/products.sqlite

# Run optimization
php bin/optimize-database.php --force

# After optimization
du -sh data/sqlite/products.sqlite
```

---

## ✅ Success Checklist

- [ ] Ran `prepare.sh` successfully
- [ ] All migrations completed
- [ ] Products imported
- [ ] Database optimized
- [ ] Cron jobs configured
- [ ] Backups working
- [ ] Featured rotation working
- [ ] Related products working
- [ ] API documentation generated
- [ ] Logs being monitored

---

**You're all set! 🎉**

For detailed documentation, see:

- `bin/README.md` - Complete bin scripts documentation
- `BIN_SCRIPTS_ENHANCEMENT_SUMMARY.md` - Detailed enhancement summary
- `PRODUCTION_DEPLOYMENT_GUIDE.md` - Production deployment guide
