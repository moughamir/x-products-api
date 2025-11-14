# Shared Hosting Deployment Guide

## X-Products API - Hostinger & Shared Hosting Environments

**Date:** 2025-10-06 (Updated: 2025-10-06)
**Environment:** Shared Hosting (Hostinger, cPanel, etc.)
**PHP Version:** 8.2.27+
**Status:** ✅ Optimized for Shared Hosting

---

## Recent Updates

### 2025-10-06: PHP 8.2 Deprecation Warning Fix

✅ **Fixed:** Deprecation warning during product import

- **Issue:** `Deprecated: Implicit conversion from float to int loses precision`
- **Location:** `src/Services/ProductProcessor.php` line 175 (formatTime method)
- **Fix:** Added explicit `(int)` casts to all float-to-int conversions
- **Impact:** Eliminates deprecation warnings during `bin/tackle.php` execution
- **Status:** No action required - fix already applied

---

## Overview

This guide provides specific instructions for deploying the X-Products API on shared hosting environments like Hostinger, where certain operations (like `chown`, `sudo`) are restricted.

---

## Key Differences from Standard Deployment

### Shared Hosting Limitations

| Operation                    | Standard Server | Shared Hosting   | Solution                |
| ---------------------------- | --------------- | ---------------- | ----------------------- |
| `chown` (change ownership)   | ✅ Available    | ❌ Restricted    | Auto-skipped by scripts |
| `chmod` (change permissions) | ✅ Full access  | ⚠️ Limited       | Graceful fallback       |
| Root/sudo access             | ✅ Available    | ❌ Not available | Not required            |
| Process limits               | ✅ High         | ⚠️ Restricted    | Scripts optimized       |
| Memory limits                | ✅ Configurable | ⚠️ Fixed         | Scripts set to 1GB      |

### Script Adaptations

The deployment scripts have been updated to:

- ✅ Detect shared hosting environments automatically
- ✅ Skip operations that require elevated privileges
- ✅ Continue deployment despite permission warnings
- ✅ Provide clear feedback about what succeeded/failed
- ✅ Exit successfully if all critical operations complete

---

## Environment Detection

The scripts automatically detect shared hosting by checking:

```bash
# Hostinger pattern
if [[ "$HOME" == /home/u* ]]

# cPanel pattern
if [[ "$HOME" == /home/*/domains/* ]]
```

When detected, you'll see:

```
ℹ️  Shared hosting environment detected
```

---

## Deployment Steps

### 1. Upload Files

Upload your project to the server:

```bash
# Via FTP/SFTP to:
/home/u800171071/domains/moritotabi.com/public_html/cosmos/

# Or via SSH:
cd /home/u800171071/domains/moritotabi.com/public_html/cosmos
git pull origin main  # If using git
```

### 2. Verify PHP Version

```bash
php -v
```

**Expected Output:**

```
PHP 8.2.27 (cli) (built: ...) ( NTS )
```

### 3. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Run Deployment Script

```bash
bash bin/deploy.sh -f
```

**Expected Behavior:**

- ✅ Will complete successfully
- ⚠️ May show warnings about permissions (normal)
- ℹ️ Will skip `chown` operations automatically

---

## Understanding Warnings

### Normal Warnings on Shared Hosting

These warnings are **expected and safe to ignore**:

```
⚠️  Could not change ownership (may require sudo or running on shared hosting)
⚠️  Warning: Could not set all data directory permissions
⚠️  Warning: Could not set all script permissions
ℹ️  Skipping ownership change (shared hosting environment)
```

**Why?** Shared hosting restricts certain operations for security. Your files already have the correct owner (your user account).

### Critical Errors (Require Action)

These errors **need to be fixed**:

```
❌ ERROR: Cannot find required directories
❌ ERROR: Composer autoload not found
✗ Error: Output file was not created
```

**Action:** Check file paths, run `composer install`, verify directory structure.

---

## Manual Deployment (If Automated Fails)

If `bin/deploy.sh` fails, run steps manually:

### Step 1: Prepare Environment

```bash
# Create required directories
mkdir -p data/sqlite data/cache data/logs backups tmp

# Set basic permissions (what you can set)
chmod 755 bin/*.sh bin/*.php
chmod 644 index.php
```

### Step 2: Run Migrations

```bash
php migrations/001_create_admin_database.php --force
php migrations/002_extend_products_database.php --force
php migrations/003_add_api_keys_and_settings.php --force
php migrations/004_add_related_products.php --force
```

**Expected Output for Each:**

```
========================================
✓ MIGRATION SUCCESSFUL!
========================================
Migration: 001_create_admin_database
Status: COMPLETE
========================================
```

### Step 3: Import Products (Optional)

```bash
# Only if you have product data
php bin/tackle.php --skip-if-exists
```

### Step 4: Optimize Databases

```bash
php bin/optimize-database.php --force
```

### Step 5: Generate API Documentation

```bash
php bin/generate-openapi.php src -o openapi.json --format json
```

### Step 6: Verify Deployment

```bash
# Check databases exist
ls -lh data/sqlite/*.sqlite

# Check OpenAPI generated
ls -lh openapi.json

# Test API endpoint
curl http://moritotabi.com/cosmos/api/products?limit=5
```

---

## Troubleshooting

### Issue 1: "Permission denied" errors

**Symptom:**

```
bash: bin/deploy.sh: Permission denied
```

**Solution:**

```bash
chmod +x bin/deploy.sh
bash bin/deploy.sh -f
```

### Issue 2: OpenAPI generation fails

**Symptom:**

```
✗ Error: Output file was not created: openapi.json
```

**Possible Causes & Solutions:**

1. **Vendor directory missing:**

   ```bash
   composer install
   ```

2. **No write permission in project root:**

   ```bash
   # Check current directory permissions
   ls -la

   # Try generating in a writable directory
   php bin/generate-openapi.php src -o tmp/openapi.json --format json
   mv tmp/openapi.json openapi.json
   ```

3. **Memory limit too low:**
   ```bash
   php -d memory_limit=512M bin/generate-openapi.php src -o openapi.json --format json
   ```

### Issue 3: Database locked errors

**Symptom:**

```
PDOException: database is locked
```

**Solution:**

```bash
# Check for running processes
lsof data/sqlite/products.sqlite

# If found, wait for them to finish or kill them
# Then retry the operation
```

### Issue 4: Migration shows "already exists"

**Symptom:**

```
⚠️  Database already exists!
Use --force flag to drop and recreate all tables.
```

**Solution:**

```bash
# This is normal if migration was already run
# Use --force to re-run (WARNING: deletes data)
php migrations/001_create_admin_database.php --force
```

### Issue 5: Timeout during product import

**Symptom:**

```
Maximum execution time exceeded
```

**Solution:**

```bash
# Scripts already set unlimited time, but if still failing:
php -d max_execution_time=0 -d memory_limit=1G bin/tackle.php --force
```

---

## Cron Jobs on Shared Hosting

### Setup via cPanel

1. **Access cPanel** → Cron Jobs
2. **Add each job manually:**

```bash
# Database Backup (Monthly - 1st at 1 AM)
0 1 1 * * cd /home/u800171071/domains/moritotabi.com/public_html/cosmos && bash bin/backup-databases.sh >> logs/cron.log 2>&1

# Featured Items Rotation (Daily at 2 AM)
0 2 * * * cd /home/u800171071/domains/moritotabi.com/public_html/cosmos && php bin/rotate-featured-items.php --count 20 >> logs/cron.log 2>&1

# Database Optimization (Weekly Sunday at 3 AM)
0 3 * * 0 cd /home/u800171071/domains/moritotabi.com/public_html/cosmos && php bin/optimize-database.php --force >> logs/cron.log 2>&1

# Clean Sessions (Daily at 4 AM)
0 4 * * * cd /home/u800171071/domains/moritotabi.com/public_html/cosmos && php bin/clean-sessions.php >> logs/cron.log 2>&1

# Generate OpenAPI Docs (Daily at 5 AM)
0 5 * * * cd /home/u800171071/domains/moritotabi.com/public_html/cosmos && php bin/generate-openapi.php src -o openapi.json --format json >> logs/cron.log 2>&1
```

### Setup via SSH (If Available)

```bash
# Generate cron configuration (always generates tmp/x-products-cron.txt)
bash bin/setup-cron-jobs.sh

# Review entries to be added
cat tmp/x-products-cron.txt

# If CLI crontab is available (some hosts):
crontab tmp/x-products-cron.txt

# If 'crontab' is NOT available (Hostinger shared hosting typical):
# Open hPanel → Advanced → Cron Jobs and paste entries from tmp/x-products-cron.txt
```

---

## Performance Optimization for Shared Hosting

### 1. Enable OPcache (If Available)

Check if OPcache is enabled:

```bash
php -i | grep opcache
```

If available, it's likely already enabled by your host.

### 2. Optimize Composer Autoloader

```bash
composer dump-autoload --optimize --no-dev
```

### 3. Use SQLite WAL Mode

Already enabled by `bin/optimize-database.php`:

```bash
php bin/optimize-database.php --force
```

### 4. Minimize Cron Job Frequency

On shared hosting with resource limits:

- Keep featured rotation daily (low resource)
- Keep optimization weekly (moderate resource)
- Keep backups monthly (low resource)

---

## Monitoring on Shared Hosting

### Check Logs

```bash
# Cron job logs
tail -f logs/cron.log

# PHP error logs (location varies by host)
tail -f ~/logs/error_log
# or
tail -f /home/u800171071/logs/error_log
```

### Check Database Size

```bash
du -sh data/sqlite/*.sqlite
```

### Check Backups

```bash
ls -lh backups/
```

### Monitor Resource Usage

Most shared hosting provides a control panel showing:

- CPU usage
- Memory usage
- Disk space
- Inodes (file count)

---

## Best Practices for Shared Hosting

### 1. Regular Backups

✅ **Automated:** Monthly via cron (already configured)
✅ **Manual:** Before major changes

```bash
bash bin/backup-databases.sh
```

### 2. Monitor Disk Space

Shared hosting has disk quotas. Monitor:

```bash
# Check disk usage
du -sh *

# Check quota (if available)
quota -s
```

### 3. Optimize Database Regularly

```bash
# Weekly via cron (already configured)
# Or manually:
php bin/optimize-database.php --force
```

### 4. Clean Old Backups

Backups auto-delete after 30 days, but you can manually clean:

```bash
# Remove backups older than 30 days
find backups/ -name "*.sqlite.gz" -mtime +30 -delete
```

### 5. Monitor Error Logs

```bash
# Check for errors
grep -i error logs/cron.log
grep -i fatal logs/cron.log
```

---

## Security Considerations

### 1. File Permissions

On shared hosting, your files are already isolated from other users. Standard permissions:

- **Directories:** 755
- **PHP files:** 644 or 755 (for executables)
- **Database files:** 664
- **Config files:** 644

### 2. Database Security

- ✅ SQLite databases are in `data/sqlite/` (not web-accessible)
- ✅ Change default admin password immediately
- ✅ Use strong API keys

### 3. Sensitive Files

Ensure these are NOT web-accessible:

- `data/sqlite/*.sqlite`
- `config/*.php`
- `vendor/`
- `.env` (if used)

---

## Support & Resources

### Hostinger-Specific

- **Control Panel:** hPanel
- **PHP Version:** Can be changed in hPanel → PHP Configuration
- **Cron Jobs:** hPanel → Advanced → Cron Jobs
- **File Manager:** hPanel → Files → File Manager

### Common Shared Hosting Providers

| Provider    | Control Panel | PHP Config | Cron Jobs |
| ----------- | ------------- | ---------- | --------- |
| Hostinger   | hPanel        | ✅         | ✅        |
| cPanel      | cPanel        | ✅         | ✅        |
| Plesk       | Plesk         | ✅         | ✅        |
| DirectAdmin | DirectAdmin   | ✅         | ✅        |

---

## Conclusion

✅ **Deployment scripts are optimized for shared hosting**
✅ **Permission warnings are normal and expected**
✅ **All critical operations will complete successfully**
✅ **Cron jobs can be set up via control panel**

**Status:** READY FOR SHARED HOSTING DEPLOYMENT

---

## Quick Reference

```bash
# Full deployment
bash bin/deploy.sh -f

# Manual steps
mkdir -p data/sqlite data/cache data/logs
php migrations/001_create_admin_database.php --force
php migrations/002_extend_products_database.php --force
php migrations/003_add_api_keys_and_settings.php --force
php migrations/004_add_related_products.php --force
php bin/optimize-database.php --force
php bin/generate-openapi.php src -o openapi.json --format json

# Verify
ls -lh data/sqlite/*.sqlite
ls -lh openapi.json
curl http://your-domain.com/cosmos/api/products?limit=5
```

---

**Environment:** Shared Hosting (Hostinger, cPanel, etc.)
**PHP Version:** 8.2.27+
**Date:** 2025-10-06
**Status:** ✅ PRODUCTION READY
