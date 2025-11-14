# Production Deployment Checklist

## Pre-Deployment Verification (Local)

### 1. Code Quality Checks

- [ ] All changes committed to git
- [ ] No uncommitted changes: `git status`
- [ ] On correct branch: `git branch`
- [ ] Latest changes pulled: `git pull origin main`

### 2. Build Process Testing

- [ ] Test OpenAPI generation:
  ```bash
  composer docs:generate
  ls -lh openapi.json  # Should be ~23KB
  cat openapi.json | jq '.info.title'  # Should show "Cosmos Product API"
  ```

- [ ] Test database setup (safe mode):
  ```bash
  php bin/tackle.php --skip-if-exists
  # Should skip if database exists
  ```

- [ ] Test full build:
  ```bash
  composer build
  # Should complete without errors
  ```

- [ ] Test help documentation:
  ```bash
  php bin/tackle.php --help
  composer run-script --list
  ```

### 3. API Testing

- [ ] Start local server:
  ```bash
  php -S localhost:8080 -t public
  ```

- [ ] Test products endpoint:
  ```bash
  curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
    "http://localhost:8080/cosmos/products?limit=1" | jq .
  ```

- [ ] Test Swagger UI:
  ```bash
  open http://localhost:8080/cosmos/swagger-ui
  ```

- [ ] Test search:
  ```bash
  curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
    "http://localhost:8080/cosmos/products/search?q=lamp" | jq .
  ```

### 4. Documentation Review

- [ ] README.md updated
- [ ] PRODUCTION_DEPLOYMENT_GUIDE.md reviewed
- [ ] QUICK_REFERENCE.md reviewed
- [ ] All documentation files present:
  ```bash
  ls -lh *.md | grep -E "(PRODUCTION|BUILD|QUICK|IMPLEMENTATION)"
  ```

---

## Production Deployment

### 1. Backup (Critical!)

- [ ] Backup production database:
  ```bash
  ssh u800171071@us-imm-web469.main-hosting.eu
  cd ~/cosmos
  cp data/sqlite/products.sqlite data/sqlite/products.sqlite.backup.$(date +%Y%m%d_%H%M%S)
  ls -lh data/sqlite/*.backup.*
  ```

- [ ] Backup production code (optional):
  ```bash
  cd ~
  tar -czf cosmos_backup_$(date +%Y%m%d_%H%M%S).tar.gz cosmos/
  ls -lh cosmos_backup_*.tar.gz
  ```

### 2. Deploy Code

- [ ] SSH into production:
  ```bash
  ssh u800171071@us-imm-web469.main-hosting.eu
  ```

- [ ] Navigate to project:
  ```bash
  cd ~/cosmos
  pwd  # Should be /home/u800171071/cosmos
  ```

- [ ] Check current status:
  ```bash
  git status
  git log -1 --oneline
  ```

- [ ] Pull latest changes:
  ```bash
  git pull origin main
  ```

- [ ] Verify files changed:
  ```bash
  git log -1 --stat
  ```

### 3. Update Dependencies

- [ ] Run composer update (triggers automated build):
  ```bash
  composer update
  ```

- [ ] Verify build output:
  - Should show "OPcache reset..." or "not available"
  - Should show "Database already contains X products"
  - Should show "✓ Database already populated. Skipping setup"
  - Should NOT show any errors

### 4. Verify Deployment

- [ ] Check OpenAPI file:
  ```bash
  ls -lh openapi.json
  # Should be ~23KB
  
  cat openapi.json | head -20
  # Should show valid JSON starting with {"openapi": "3.0.0"...
  ```

- [ ] Check database:
  ```bash
  ls -lh data/sqlite/products.sqlite
  # Should be ~97MB
  
  sqlite3 data/sqlite/products.sqlite "SELECT COUNT(*) FROM products;"
  # Should show 10000
  ```

- [ ] Check error logs:
  ```bash
  tail -50 error_log
  # Should NOT show any new errors
  ```

- [ ] Check file permissions:
  ```bash
  ls -la bin/*.php
  # Should be executable (755 or rwxr-xr-x)
  ```

### 5. Test API Endpoints

- [ ] Test products endpoint:
  ```bash
  curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
    "https://your-domain.com/cosmos/products?limit=1" | jq .
  ```

- [ ] Test single product:
  ```bash
  curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
    "https://your-domain.com/cosmos/products/1059061125" | jq .
  ```

- [ ] Test search:
  ```bash
  curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
    "https://your-domain.com/cosmos/products/search?q=lamp" | jq .
  ```

- [ ] Test Swagger UI (in browser):
  ```
  https://your-domain.com/cosmos/swagger-ui
  ```

- [ ] Test OpenAPI JSON:
  ```bash
  curl "https://your-domain.com/cosmos/openapi.json" | jq '.info.title'
  # Should show "Cosmos Product API"
  ```

---

## Post-Deployment Monitoring

### Immediate (First 10 minutes)

- [ ] Monitor error logs:
  ```bash
  tail -f error_log
  # Watch for any new errors
  ```

- [ ] Test multiple API endpoints
- [ ] Check response times
- [ ] Verify data integrity (spot check products)

### First Hour

- [ ] Check error logs every 15 minutes:
  ```bash
  tail -50 error_log | grep -i error
  ```

- [ ] Monitor server resources:
  ```bash
  top
  df -h
  ```

- [ ] Test from different locations/devices

### First 24 Hours

- [ ] Check error logs periodically
- [ ] Monitor API usage/traffic
- [ ] Collect user feedback
- [ ] Watch for performance issues

---

## Rollback Procedure (If Needed)

### Quick Rollback (Code Only)

```bash
# SSH into production
ssh u800171071@us-imm-web469.main-hosting.eu
cd ~/cosmos

# Revert to previous commit
git log --oneline -5  # Find previous commit hash
git reset --hard <previous-commit-hash>

# Reinstall dependencies
composer install --no-scripts

# Run build manually
composer app:clear-cache
composer docs:generate
```

### Full Rollback (Code + Database)

```bash
# SSH into production
ssh u800171071@us-imm-web469.main-hosting.eu
cd ~/cosmos

# Revert code
git reset --hard <previous-commit-hash>

# Restore database backup
cp data/sqlite/products.sqlite.backup.YYYYMMDD_HHMMSS data/sqlite/products.sqlite

# Reinstall dependencies
composer install --no-scripts

# Verify
ls -lh data/sqlite/products.sqlite
tail -50 error_log
```

---

## Troubleshooting

### OpenAPI Generation Fails

**Symptoms:**
- `composer docs:generate` exits with code 1
- `openapi.json` is 26 bytes or empty

**Diagnosis:**
```bash
# Check PHP version
php -v

# Test directly
php bin/generate-openapi.php --output /tmp/test.json src/OpenApi.php src/Controllers/ 2>&1

# Check vendor binary
ls -la vendor/bin/openapi

# Check error log
tail -50 error_log
```

**Solutions:**
1. Reinstall dependencies: `rm -rf vendor && composer install`
2. Check file permissions: `chmod +x bin/generate-openapi.php`
3. Test original command: `php vendor/bin/openapi --output /tmp/test.json src/OpenApi.php src/Controllers/`

### Database Setup Fails

**Symptoms:**
- `composer build` fails at db:setup step
- Error: "FATAL ERROR: ..."

**Diagnosis:**
```bash
# Test directly
php bin/tackle.php --help

# Check database
ls -la data/sqlite/

# Check JSON files
ls -la data/json/products_by_id/ | head -20
```

**Solutions:**
1. Check permissions: `chmod 755 data/sqlite`
2. Increase memory: `php -d memory_limit=512M bin/tackle.php --force`
3. Check disk space: `df -h`

### API Returns Errors

**Symptoms:**
- API endpoints return 500 errors
- Empty responses

**Diagnosis:**
```bash
# Check error log
tail -50 error_log

# Test database
sqlite3 data/sqlite/products.sqlite "SELECT COUNT(*) FROM products;"

# Check file permissions
ls -la data/sqlite/products.sqlite
```

**Solutions:**
1. Check database permissions: `chmod 644 data/sqlite/products.sqlite`
2. Rebuild database: `composer db:rebuild`
3. Check PHP error log: `tail -50 /var/log/php-fpm/error.log`

---

## Success Criteria

### Deployment is successful if:

- ✅ `composer update` completes without errors
- ✅ `openapi.json` is ~23KB and valid JSON
- ✅ Database has 10,000 products
- ✅ No errors in error_log
- ✅ API endpoints return valid responses
- ✅ Swagger UI loads correctly
- ✅ Response times are normal (<500ms)
- ✅ No user-reported issues

### Deployment should be rolled back if:

- ❌ API endpoints return 500 errors
- ❌ Database is corrupted or empty
- ❌ Critical errors in error_log
- ❌ Response times > 2 seconds
- ❌ Data integrity issues
- ❌ Multiple user-reported issues

---

## Contact Information

### Support Resources

- **Documentation**: See `PRODUCTION_DEPLOYMENT_GUIDE.md`
- **Quick Reference**: See `QUICK_REFERENCE.md`
- **Technical Details**: See `BUILD_PROCESS_REFACTORING.md`

### Emergency Contacts

- **Developer**: [Your contact info]
- **Server Admin**: [Hostinger support]
- **Backup Contact**: [Team lead]

---

## Sign-Off

### Pre-Deployment

- [ ] Checklist reviewed by: _________________ Date: _______
- [ ] Backup verified by: _________________ Date: _______
- [ ] Deployment approved by: _________________ Date: _______

### Post-Deployment

- [ ] Deployment completed by: _________________ Date: _______
- [ ] Verification completed by: _________________ Date: _______
- [ ] Monitoring confirmed by: _________________ Date: _______

---

**Version**: 1.0  
**Last Updated**: October 6, 2025  
**Next Review**: After first production deployment

