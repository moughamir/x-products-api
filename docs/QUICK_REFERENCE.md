# Quick Reference Guide

## Common Commands

### Installation & Setup

```bash
# Fresh installation (automated)
composer install

# Update dependencies (automated)
composer update

# Manual installation (no automation)
composer install --no-scripts
```

### Build Commands

```bash
# Full build (cache + docs + database)
composer build

# Generate OpenAPI documentation
composer docs:generate

# Setup database (safe - skips if exists)
composer db:setup

# Force rebuild database (WARNING: deletes data)
composer db:rebuild

# Clear PHP opcache
composer app:clear-cache

# List all available commands
composer run-script --list
```

### Database Management

```bash
# Interactive mode (asks for confirmation)
php bin/tackle.php

# Skip if database already has data (SAFE)
php bin/tackle.php --skip-if-exists

# Force rebuild (WARNING: deletes all data)
php bin/tackle.php --force

# Show help
php bin/tackle.php --help
```

### Development Server

```bash
# Start PHP built-in server
php -S localhost:8080 -t public

# Access API
curl -H "X-API-KEY: your-key" "http://localhost:8080/cosmos/products?limit=5"

# Access Swagger UI
open http://localhost:8080/cosmos/swagger-ui
```

### Docker

```bash
# Build and start containers
docker-compose up -d --build

# View logs
docker-compose logs -f api

# Execute commands in container
docker-compose exec api composer build
docker-compose exec api php bin/tackle.php --help

# Stop containers
docker-compose down
```

---

## Environment Variables

```bash
# Development environment
export APP_ENV=development

# Production environment (default)
export APP_ENV=production
```

---

## Production Deployment

### Hostinger SSH

```bash
# Connect to server
ssh u800171071@us-imm-web469.main-hosting.eu

# Navigate to project
cd ~/cosmos
```

### Deploy Updates

```bash
# Pull latest changes
git pull origin main

# Update dependencies (runs build automatically)
composer update

# Verify
ls -lh openapi.json
tail -50 error_log
```

### Force Database Rebuild (Rare)

```bash
# WARNING: This deletes all product data!
composer db:rebuild

# Or with environment variable
APP_ENV=production php bin/tackle.php --force
```

---

## Troubleshooting

### OpenAPI Generation Fails

```bash
# Check PHP version
php -v

# Test directly
php bin/generate-openapi.php --output /tmp/test.json src/OpenApi.php src/Controllers/ 2>&1

# Check vendor binary
ls -la vendor/bin/openapi

# Reinstall dependencies
rm -rf vendor
composer install
```

### Database Setup Fails

```bash
# Check database directory
ls -la data/sqlite/

# Check JSON source files
ls -la data/json/products_by_id/ | head -20

# Test with verbose output
php bin/tackle.php --force 2>&1 | tee setup.log

# Increase memory limit
php -d memory_limit=512M bin/tackle.php --force
```

### Build Process Hangs

```bash
# Run steps manually
composer install --no-scripts
composer app:clear-cache
composer docs:generate
composer db:setup
```

### Check Logs

```bash
# Production error log
tail -50 error_log

# Follow logs in real-time
tail -f error_log

# Search for errors
grep -i error error_log | tail -20
```

---

## API Endpoints

### Base URL

- **Local**: `http://localhost:8080/cosmos`
- **Production**: `https://your-domain.com/cosmos`

### Authentication

All endpoints require `X-API-KEY` header:

```bash
curl -H "X-API-KEY: your-key-here" "http://localhost:8080/cosmos/products"
```

### Key Endpoints

```bash
# List products
GET /cosmos/products?page=1&limit=50

# Get single product
GET /cosmos/products/{id}
GET /cosmos/products/{handle}

# Search products
GET /cosmos/products/search?q=keyword

# Get collection
GET /cosmos/collections/{handle}

# Documentation
GET /cosmos/swagger-ui
GET /cosmos/openapi.json
```

---

## File Locations

### Configuration

- `config/app.php` - Application configuration
- `config/database.php` - Database configuration
- `composer.json` - Dependencies and scripts

### Data

- `data/json/products_by_id/` - Source JSON files
- `data/sqlite/products.sqlite` - SQLite database
- `openapi.json` - Generated API documentation

### Scripts

- `bin/tackle.php` - Database setup tool
- `bin/generate-openapi.php` - OpenAPI generator wrapper
- `clear_opcache.php` - Cache clearing script

### Source Code

- `src/Controllers/` - API controllers
- `src/Services/` - Business logic
- `src/Models/` - Data models
- `src/OpenApi.php` - OpenAPI annotations

---

## Useful Checks

### Verify Installation

```bash
# Check PHP version
php -v

# Check Composer version
composer --version

# Check SQLite extension
php -m | grep sqlite

# Check database
ls -lh data/sqlite/products.sqlite

# Check OpenAPI docs
ls -lh openapi.json
cat openapi.json | head -20

# Count products in database
sqlite3 data/sqlite/products.sqlite "SELECT COUNT(*) FROM products;"
```

### Test API

```bash
# Test products endpoint
curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
  "http://localhost:8080/cosmos/products?limit=1" | jq .

# Test search
curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
  "http://localhost:8080/cosmos/products/search?q=lamp" | jq .

# Test single product
curl -H "X-API-KEY: 0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539" \
  "http://localhost:8080/cosmos/products/1059061125" | jq .
```

---

## Safety Checklist

### Before Deploying to Production

- [ ] Test `composer build` locally
- [ ] Verify `openapi.json` is valid JSON
- [ ] Verify database has products
- [ ] Test API endpoints locally
- [ ] Backup production database (if exists)
- [ ] Set `APP_ENV=production` on server
- [ ] Test on staging first (if available)

### After Deploying to Production

- [ ] Check error logs: `tail -50 error_log`
- [ ] Verify OpenAPI docs: `ls -lh openapi.json`
- [ ] Test API endpoints
- [ ] Test Swagger UI: `/cosmos/swagger-ui`
- [ ] Monitor for 24 hours

---

## Getting Help

### Documentation

1. **`README.md`** - Quick start and overview
2. **`PRODUCTION_DEPLOYMENT_GUIDE.md`** - Deployment and troubleshooting
3. **`BUILD_PROCESS_REFACTORING.md`** - Technical details
4. **`QUICK_REFERENCE.md`** - This file

### Command Help

```bash
# Composer scripts
composer run-script --list

# Database setup
php bin/tackle.php --help

# OpenAPI generation
php bin/generate-openapi.php --help
```

### Logs

```bash
# Production error log
tail -50 error_log

# PHP error log (if different)
tail -50 /var/log/php-fpm/error.log

# Apache/Nginx error log
tail -50 /var/log/apache2/error.log
tail -50 /var/log/nginx/error.log
```

---

## Version Information

- **API Version**: 1.0.0
- **PHP Version**: 8.2+ (tested on 8.2.27 and 8.4.13)
- **Framework**: Slim 4.15
- **Database**: SQLite 3
- **Documentation**: OpenAPI 3.0

---

**Last Updated**: October 6, 2025

