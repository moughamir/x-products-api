#!/bin/bash
# Database Backup Script
# Creates timestamped backups of SQLite databases

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$PROJECT_ROOT/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

echo "========================================="
echo "Database Backup Script"
echo "========================================="
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Backup directory: $BACKUP_DIR"
echo "========================================="
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Function to backup a database
backup_database() {
    local db_file="$1"
    local db_name="$2"
    
    if [ ! -f "$db_file" ]; then
        echo "⚠️  Database not found: $db_file"
        return 1
    fi
    
    local backup_file="$BACKUP_DIR/${db_name}_${TIMESTAMP}.sqlite"
    local backup_size
    
    echo "→ Backing up $db_name..."
    
    # Create backup using SQLite's backup command for consistency
    sqlite3 "$db_file" ".backup '$backup_file'"
    
    # Compress the backup
    gzip "$backup_file"
    backup_file="${backup_file}.gz"
    
    backup_size=$(du -h "$backup_file" | cut -f1)
    echo "  ✓ Backup created: $(basename "$backup_file") ($backup_size)"
    
    return 0
}

# Backup products database
if [ -f "$PROJECT_ROOT/data/sqlite/products.sqlite" ]; then
    backup_database "$PROJECT_ROOT/data/sqlite/products.sqlite" "products"
fi

# Backup admin database
if [ -f "$PROJECT_ROOT/data/sqlite/admin.sqlite" ]; then
    backup_database "$PROJECT_ROOT/data/sqlite/admin.sqlite" "admin"
fi

# Clean old backups
echo ""
echo "→ Cleaning old backups (older than $RETENTION_DAYS days)..."
deleted_count=0

if [ -d "$BACKUP_DIR" ]; then
    while IFS= read -r -d '' file; do
        rm "$file"
        deleted_count=$((deleted_count + 1))
    done < <(find "$BACKUP_DIR" -name "*.sqlite.gz" -type f -mtime +$RETENTION_DAYS -print0)
fi

if [ $deleted_count -gt 0 ]; then
    echo "  ✓ Deleted $deleted_count old backup(s)"
else
    echo "  ✓ No old backups to delete"
fi

# Show backup statistics
echo ""
echo "========================================="
echo "Backup Statistics"
echo "========================================="
echo "Total backups: $(find "$BACKUP_DIR" -name "*.sqlite.gz" -type f | wc -l)"
echo "Total size: $(du -sh "$BACKUP_DIR" | cut -f1)"
echo "Latest backups:"
find "$BACKUP_DIR" -name "*_${TIMESTAMP}.sqlite.gz" -type f -exec basename {} \;
echo "========================================="
echo "✓ Backup Complete!"
echo "========================================="
echo ""

