# Production Deployment Guide - X-Products API

Complete guide for deploying X-Products API to production with security, performance, and monitoring best practices.

## Table of Contents
1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Deployment Steps](#deployment-steps)
3. [Security Hardening](#security-hardening)
4. [Performance Optimization](#performance-optimization)
5. [Monitoring & Maintenance](#monitoring--maintenance)
6. [Troubleshooting](#troubleshooting)

---

## Pre-Deployment Checklist

### Database Backup
Before deploying to production, **always backup your databases**:

```bash
# Backup products database
cp data/sqlite/products.sqlite data/sqlite/products.sqlite.backup.$(date +%Y%m%d_%H%M%S)

# Backup admin database
cp data/sqlite/admin.sqlite data/sqlite/admin.sqlite.backup.$(date +%Y%m%d_%H%M%S)
```

### Environment Requirements

#### PHP Requirements
- **PHP Version**: 8.1 or higher (8.2+ recommended)
- **Required Extensions**:
  - `pdo_sqlite` - Database connectivity
  - `mbstring` - String handling
  - `json` - JSON processing
  - `openssl` - Security features
  - `curl` - HTTP requests
  - `fileinfo` - File type detection
  - `opcache` - Performance optimization

Check installed extensions:
```bash
php -m | grep -E 'pdo_sqlite|mbstring|json|openssl|curl|fileinfo|opcache'
```

#### System Requirements
- **Disk Space**: Minimum 500MB (more for product images)
- **Memory**: Minimum 256MB PHP memory_limit (512MB recommended)
- **Permissions**: Write access to `data/` directory

### File Permissions

Set appropriate permissions:
```bash
# Application files (read-only)
chmod -R 755 src/ templates/ vendor/ config/

# Data directory (read-write)
chmod -R 775 data/
chown -R www-data:www-data data/

# Database files
chmod 664 data/sqlite/*.sqlite
chown www-data:www-data data/sqlite/*.sqlite
```

---

## Deployment Steps

### Option 1: Apache Deployment

#### 1. Install Apache and PHP
```bash
sudo apt update
sudo apt install apache2 php8.2 php8.2-sqlite3 php8.2-mbstring php8.2-curl php8.2-opcache
```

#### 2. Configure Virtual Host
Create `/etc/apache2/sites-available/x-products-api.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAdmin admin@your-domain.com
    DocumentRoot /var/www/x-products-api

    <Directory /var/www/x-products-api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>

    <Directory /var/www/x-products-api/data>
        Require all denied
    </Directory>

    <Directory /var/www/x-products-api/config>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/x-products-api-error.log
    CustomLog ${APACHE_LOG_DIR}/x-products-api-access.log combined
</VirtualHost>
```

#### 3. Enable Site and Modules
```bash
sudo a2enmod rewrite
sudo a2ensite x-products-api
sudo systemctl restart apache2
```

### Option 2: Nginx Deployment

#### 1. Install Nginx and PHP-FPM
```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-sqlite3 php8.2-mbstring php8.2-curl php8.2-opcache
```

#### 2. Configure Nginx Server Block
Create `/etc/nginx/sites-available/x-products-api`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/x-products-api;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location ~ /\. { deny all; }
    location /data { deny all; }
    location /config { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    access_log /var/log/nginx/x-products-api-access.log;
    error_log /var/log/nginx/x-products-api-error.log;
}
```

#### 3. Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/x-products-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Database Migration

Run all migrations in order:
```bash
cd /var/www/x-products-api
php migrations/001_create_admin_database.php
php migrations/002_extend_products_database.php
php migrations/003_add_api_keys_and_settings.php
```

---

## Security Hardening

### 1. Change Default Admin Credentials

**CRITICAL**: Change immediately after deployment:
- Navigate to: `http://your-domain.com/cosmos/admin/login`
- Username: `admin` / Password: `admin123`
- Go to Profile → Change Password
- Use strong password (12+ chars, mixed case, numbers, symbols)

### 2. Configure HTTPS/SSL

Using Let's Encrypt:
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
sudo certbot renew --dry-run
```

### 3. Secure Session Settings

Update `config/app.php`:
```php
'session' => [
    'secure' => true,  // HTTPS only
    'httponly' => true,
    'samesite' => 'Strict',
],
```

### 4. File Upload Limits

Create `.user.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20
```

---

## Performance Optimization

### 1. Enable OPcache

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

### 2. Database Optimization

```bash
# Enable WAL mode
sqlite3 data/sqlite/products.sqlite "PRAGMA journal_mode=WAL;"
sqlite3 data/sqlite/admin.sqlite "PRAGMA journal_mode=WAL;"

# Monthly maintenance
sqlite3 data/sqlite/products.sqlite "VACUUM; ANALYZE;"
sqlite3 data/sqlite/admin.sqlite "VACUUM; ANALYZE;"
```

---

## Monitoring & Maintenance

### Log Locations
- Apache: `/var/log/apache2/x-products-api-*.log`
- Nginx: `/var/log/nginx/x-products-api-*.log`
- PHP: `/var/log/php8.2-fpm.log`

### Automated Backups

Create `/usr/local/bin/backup-x-products.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/x-products"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR
cp /var/www/x-products-api/data/sqlite/*.sqlite $BACKUP_DIR/
find $BACKUP_DIR -mtime +30 -delete
```

Add to crontab: `0 2 * * * /usr/local/bin/backup-x-products.sh`

---

## Troubleshooting

### Database Locked
```bash
sqlite3 data/sqlite/products.sqlite "PRAGMA journal_mode=WAL;"
```

### Permission Denied
```bash
sudo chown -R www-data:www-data data/
sudo chmod -R 775 data/
```

### 500 Errors
```bash
tail -f /var/log/nginx/x-products-api-error.log
```

---

## Production Checklist

- [ ] Databases backed up
- [ ] Default admin password changed
- [ ] HTTPS/SSL configured
- [ ] File permissions set
- [ ] OPcache enabled
- [ ] Error logging enabled
- [ ] Automated backups configured
- [ ] Database optimized (WAL mode)
- [ ] Security headers configured
- [ ] All migrations run
- [ ] Application tested

---

**Last Updated**: October 2025  
**Version**: 1.0.0
