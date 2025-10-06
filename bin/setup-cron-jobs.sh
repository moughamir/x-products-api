#!/bin/bash
# Cron Jobs Setup Script
# This script helps set up automated tasks for the X-Products API

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CRON_FILE="$PROJECT_ROOT/tmp/x-products-cron.txt"

# Resolve crontab binary (handle shared hosting without CLI crontab)
CRONTAB_BIN="$(type -P crontab 2>/dev/null || true)"
if [ -z "$CRONTAB_BIN" ]; then
    for p in /usr/bin/crontab /bin/crontab /usr/sbin/crontab; do
        if [ -x "$p" ]; then
            CRONTAB_BIN="$p"
            break
        fi
    done
fi

HAS_CRONTAB=false
if [ -n "$CRONTAB_BIN" ] && [ -x "$CRONTAB_BIN" ]; then
    HAS_CRONTAB=true
fi

echo "========================================="
echo "X-Products API - Cron Jobs Setup"
echo "========================================="
echo "Project root: $PROJECT_ROOT"
echo "========================================="
echo ""

# Function to add a cron job
add_cron_job() {
    local schedule="$1"
    local command="$2"
    local description="$3"

    echo "# $description" >> "$CRON_FILE"
    echo "$schedule cd $PROJECT_ROOT && $command >> $PROJECT_ROOT/logs/cron.log 2>&1" >> "$CRON_FILE"
    echo "" >> "$CRON_FILE"
}

# Create logs directory if it doesn't exist
mkdir -p "$PROJECT_ROOT/logs"

# Start fresh cron file
echo "# X-Products API Automated Tasks" > "$CRON_FILE"
echo "# Generated on: $(date)" >> "$CRON_FILE"
echo "# Project: $PROJECT_ROOT" >> "$CRON_FILE"
echo "" >> "$CRON_FILE"

echo "→ Configuring cron jobs..."
echo ""

# 1. Featured Items Rotation (Daily at 2 AM)
echo "1. Featured Items Rotation"
echo "   Schedule: Daily at 2:00 AM"
echo "   Rotates featured products to keep content fresh"
add_cron_job "0 2 * * *" "php bin/rotate-featured-items.php --count 20" "Rotate featured items daily"

# 2. Database Optimization (Weekly on Sunday at 3 AM)
echo "2. Database Optimization"
echo "   Schedule: Weekly on Sunday at 3:00 AM"
echo "   Optimizes database performance and reclaims space"
add_cron_job "0 3 * * 0" "php bin/optimize-database.php --force" "Weekly database optimization"

# 3. Clean Old Sessions (Daily at 4 AM)
echo "3. Clean Old Sessions"
echo "   Schedule: Daily at 4:00 AM"
echo "   Removes expired admin sessions"
add_cron_job "0 4 * * *" "php bin/clean-sessions.php" "Clean expired sessions"

# 4. Generate OpenAPI Docs (Daily at 5 AM)
echo "4. Generate OpenAPI Documentation"
echo "   Schedule: Daily at 5:00 AM"
echo "   Updates API documentation"
add_cron_job "0 5 * * *" "php bin/generate-openapi.php src -o $PROJECT_ROOT/openapi.json --format json" "Generate OpenAPI documentation"

# 5. Backup Databases (Daily at 1 AM)
echo "5. Database Backup"
echo "   Schedule: Monthly on the first day of the month"
echo "   Creates database backups"
add_cron_job "0 1 1 * *" "bash bin/backup-databases.sh" "Monthly database backup"

echo ""
echo "========================================="
echo "Cron jobs configuration created!"
echo "========================================="
echo ""
echo "Review the cron jobs:"
cat "$CRON_FILE"
echo ""
echo "========================================="
echo "Installation Options:"
echo "========================================="
echo ""
if [ "$HAS_CRONTAB" = true ]; then
    echo "Option 1: Install for current user (auto)"
    echo "  $CRONTAB_BIN $CRON_FILE"
    echo ""
    echo "Option 2: Append to existing crontab (auto)"
    echo "  $CRONTAB_BIN -l > $PROJECT_ROOT/tmp/current-cron.txt"
    echo "  cat $CRON_FILE >> $PROJECT_ROOT/tmp/current-cron.txt"
    echo "  $CRONTAB_BIN $PROJECT_ROOT/tmp/current-cron.txt"
    echo ""
    echo "Option 3: Manual installation (editor)"
    echo "  $CRONTAB_BIN -e"
    echo "  # Then copy the contents from: $CRON_FILE"
else
    echo "CLI crontab is not available on this environment."
    echo "Use your hosting control panel (e.g., Hostinger hPanel  Advanced  Cron Jobs)"
    echo "and copy the entries from: $CRON_FILE"
    echo ""
    echo "Tip: Create each job with the schedules shown above."
fi

echo ""
echo "========================================="
echo "Verify Installation:"
echo "========================================="
if [ "$HAS_CRONTAB" = true ]; then
    echo "  $CRONTAB_BIN -l"
else
    echo "  In hPanel: Advanced  Cron Jobs  View configured jobs"
fi

echo ""
echo "========================================="
echo "Monitor Logs:"
echo "========================================="
echo "  tail -f $PROJECT_ROOT/logs/cron.log"
echo ""

# Install (auto) only when CLI crontab is available
if [ "$HAS_CRONTAB" = true ]; then
    read -p "Install cron jobs now? (yes/no): " -r
    echo
    if [[ $REPLY =~ ^[Yy]es$ ]]; then
        # Backup existing crontab
        if "$CRONTAB_BIN" -l > /dev/null 2>&1; then
            echo "→ Backing up existing crontab..."
            "$CRONTAB_BIN" -l > "$PROJECT_ROOT/logs/crontab-backup-$(date +%Y%m%d-%H%M%S).txt"

            # Append new jobs
            echo "→ Appending new cron jobs..."
            ("$CRONTAB_BIN" -l 2>/dev/null; cat "$CRON_FILE") | "$CRONTAB_BIN" -
        else
            # No existing crontab, install fresh
            echo "→ Installing cron jobs..."
            "$CRONTAB_BIN" "$CRON_FILE"
        fi

        echo "✓ Cron jobs installed successfully!"
        echo ""
        echo "Current crontab:"
        "$CRONTAB_BIN" -l
    else
        echo "Cron jobs not installed."
        echo "Configuration saved to: $CRON_FILE"
    fi
else
    echo "CLI crontab is not available. Skipping auto-install."
    echo "Use your hosting control panel (hPanel → Advanced → Cron Jobs) to add entries from:"
    echo "  $CRON_FILE"
fi

echo ""
echo "========================================="
echo "Setup Complete!"
echo "========================================="

