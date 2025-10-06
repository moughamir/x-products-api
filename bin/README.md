# Bin Scripts Documentation

This directory contains all command-line scripts for managing the X-Products API.

## Quick Reference

| Script | Purpose | Frequency |
|--------|---------|-----------|
| `prepare.sh` | Deployment preparation | Once per deployment |
| `tackle.php` | Product data import | As needed |
| `optimize-database.php` | Database optimization | Weekly |
| `rotate-featured-items.php` | Featured items rotation | Daily |
| `clean-sessions.php` | Session cleanup | Daily |
| `backup-databases.sh` | Database backup | Daily |
| `analyze-data.sh` | Data analysis | As needed |
| `generate-openapi.php` | API documentation | Daily |
| `setup-cron-jobs.sh` | Cron configuration | Once |

---

## Deployment & Setup

### prepare.sh

Prepares the application for deployment by setting permissions, generating documentation, and creating required directories.

```bash
# Run from project root
bash bin/prepare.sh
```

**What it does:**
- ✅ Validates project structure
- ✅ Generates OpenAPI specification
- ✅ Sets ownership and permissions
- ✅ Creates required directories
- ✅ Provides deployment checklist

**When to use:** Before every deployment

---

## Data Management

### tackle.php

Imports product data from JSON files into the SQLite database.

```bash
# Interactive mode
php bin/tackle.php

# Force mode (no confirmation)
php bin/tackle.php --force

# Skip if database already has data
php bin/tackle.php --skip-if-exists
```

**Features:**
- ✅ Unlimited execution time
- ✅ 1GB memory limit
- ✅ Batch processing (500 products per batch)
- ✅ Progress reporting with ETA
- ✅ FTS5 index creation
- ✅ Production safety checks

**When to use:** Initial setup or data refresh

---

### analyze-data.sh

Analyzes product JSON files and exports selected fields to CSV.

```bash
# Default fields
./bin/analyze-data.sh

# Custom fields
./bin/analyze-data.sh --fields id,title,price,vendor

# Custom output file
./bin/analyze-data.sh --output custom.csv

# Help
./bin/analyze-data.sh --help
```

**Available fields:**
- Basic: `id`, `title`, `handle`, `body_html`, `vendor`, `product_type`
- Dates: `created_at`, `updated_at`
- Pricing: `price`, `compare_at_price`
- Metadata: `tags`, `has_variants`, `has_images`, `variant_count`, `image_count`

**When to use:** Data analysis, reporting, debugging

---

## Database Optimization

### optimize-database.php

Comprehensive database optimization including indexes, VACUUM, ANALYZE, and WAL mode.

```bash
# Optimize all databases
php bin/optimize-database.php

# Optimize specific database
php bin/optimize-database.php --products
php bin/optimize-database.php --admin

# Skip confirmation
php bin/optimize-database.php --force
```

**What it does:**
- ✅ Creates missing indexes
- ✅ Enables WAL mode
- ✅ Optimizes FTS5 tables
- ✅ Runs VACUUM (reclaim space)
- ✅ Runs ANALYZE (update statistics)
- ✅ Shows database statistics

**Expected results:**
- 2-5x faster queries
- 10-30% smaller database size
- Better concurrency

**When to use:** Weekly (automated via cron)

---

## Content Management

### rotate-featured-items.php

Automatically rotates featured products to keep content fresh.

```bash
# Default: 20 featured items
php bin/rotate-featured-items.php

# Custom count
php bin/rotate-featured-items.php --count 30

# Custom thresholds
php bin/rotate-featured-items.php --min-rating 4.5 --min-reviews 20

# Preview changes (dry run)
php bin/rotate-featured-items.php --dry-run
```

**Rotation strategies:**
1. **ADD:** When current < target (adds new items)
2. **REMOVE:** When current > target (removes lowest rated)
3. **ROTATE:** Replaces bottom 25% with new candidates

**Selection criteria:**
- Minimum rating (default: 4.0)
- Minimum reviews (default: 10)
- In stock only
- Not currently featured

**When to use:** Daily (automated via cron)

---

## Maintenance

### clean-sessions.php

Removes expired admin sessions from the database.

```bash
# Clean expired sessions
php bin/clean-sessions.php

# Preview what would be deleted
php bin/clean-sessions.php --dry-run
```

**What it does:**
- ✅ Removes expired sessions
- ✅ Shows statistics
- ✅ Dry-run mode available

**When to use:** Daily (automated via cron)

---

### backup-databases.sh

Creates compressed backups of SQLite databases.

```bash
bash bin/backup-databases.sh
```

**What it does:**
- ✅ Creates timestamped backups
- ✅ Compresses with gzip
- ✅ Removes backups older than 30 days
- ✅ Shows backup statistics

**Backup location:** `backups/`
**Retention:** 30 days
**When to use:** Daily (automated via cron)

---

## Documentation

### generate-openapi.php

Generates OpenAPI specification from code annotations.

```bash
# Generate JSON
php bin/generate-openapi.php src -o openapi.json --format json

# Generate YAML
php bin/generate-openapi.php src -o openapi.yaml --format yaml
```

**Features:**
- ✅ PHP 8.4+ compatibility
- ✅ Production environment support
- ✅ Output file verification
- ✅ Error filtering

**When to use:** After API changes (automated via cron)

---

## Automation

### setup-cron-jobs.sh

Configures automated tasks via cron.

```bash
bash bin/setup-cron-jobs.sh
```

**Cron jobs configured:**

| Time | Task | Purpose |
|------|------|---------|
| 1:00 AM | Database Backup | Daily backups |
| 2:00 AM | Featured Rotation | Keep content fresh |
| 3:00 AM (Sun) | Database Optimization | Weekly maintenance |
| 4:00 AM | Clean Sessions | Remove expired sessions |
| 5:00 AM | Generate OpenAPI | Update documentation |

**What it does:**
- ✅ Generates cron configuration
- ✅ Backs up existing crontab
- ✅ Interactive installation
- ✅ Provides verification commands

**When to use:** Once during initial setup

---

## Utilities

### common.php

Shared utilities library for CLI scripts (not directly executable).

**Functions provided:**
- Display formatting (`displayHeader`, `displayStep`, `displayError`)
- User interaction (`confirmAction`)
- Option parsing (`parseOptions`, `displayHelp`)
- Database helpers (`connectDatabase`, `optimizeDatabase`)
- Formatting (`formatBytes`, `formatTime`)
- Logging (`logMessage`)

**Usage in scripts:**
```php
require __DIR__ . '/common.php';

displayHeader('My Script', ['Version' => '1.0']);
$db = connectDatabase($dbPath, 'Products');
displayStep('Processing...', true);
```

---

## Troubleshooting

### Script won't execute

```bash
# Make scripts executable
chmod +x bin/*.sh bin/*.php
```

### Permission denied

```bash
# Run prepare script
bash bin/prepare.sh
```

### Database locked

```bash
# Enable WAL mode
php bin/optimize-database.php --force
```

### Out of memory

```bash
# Memory limit already set to 1GB in scripts
# If still insufficient, edit php.ini:
memory_limit = 2G
```

### Timeout errors

```bash
# Timeout constraints removed in all scripts
# Should not occur anymore
```

---

## Monitoring

### Check cron jobs

```bash
crontab -l
```

### Monitor logs

```bash
# Cron log
tail -f logs/cron.log

# CLI log
tail -f logs/cli.log
```

### Check database size

```bash
du -sh data/sqlite/*.sqlite
```

### Check backups

```bash
ls -lh backups/
```

---

## Best Practices

1. **Always run prepare.sh before deployment**
2. **Test scripts with --dry-run when available**
3. **Monitor logs regularly**
4. **Keep backups for at least 30 days**
5. **Run optimize-database.php weekly**
6. **Rotate featured items daily**
7. **Review cron job output**

---

## Development

### Adding new scripts

1. Create script in `bin/` directory
2. Add shebang line (`#!/usr/bin/env php` or `#!/bin/bash`)
3. Use `common.php` utilities
4. Add help documentation
5. Make executable: `chmod +x bin/your-script.php`
6. Update this README
7. Add to cron if needed

### Testing scripts

```bash
# Always test with dry-run first
php bin/your-script.php --dry-run

# Test in development environment
APP_ENV=development php bin/your-script.php

# Check exit codes
php bin/your-script.php
echo $?  # Should be 0 for success
```

---

## Support

For issues or questions:
1. Check this README
2. Review `BIN_SCRIPTS_ENHANCEMENT_SUMMARY.md`
3. Check logs in `logs/` directory
4. Review script help: `--help` or `-h`

---

## Version History

- **v2.0** (2025-10-06): Major enhancement with automation, optimization, and related products
- **v1.0** (2024): Initial release

---

## Related Documentation

- `BIN_SCRIPTS_ENHANCEMENT_SUMMARY.md` - Detailed enhancement summary
- `PRODUCTION_DEPLOYMENT_GUIDE.md` - Production deployment guide
- `README.md` - Main project README

