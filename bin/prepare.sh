#!/bin/bash
# X-Products API Permission Setup Script

# --- Configuration Variables ---
CLI_USER=$(whoami)
DB_FILE="data/sqlite/products.sqlite"
WEB_USER=""
# -------------------------------

echo "--- X-Products API Permission Setup ---"

# --- 1. Attempt to Auto-Detect the Web Server User ---
# The web server user is typically the account under which PHP-FPM or the web server process runs.
echo "1. Attempting to auto-detect Web Server User..."

# Try to find the user running Nginx or Apache, excluding 'root' processes
WEB_USER=$(ps aux | grep -E '[n]ginx|[a]pache|[h]ttpd|[f]pm' | grep -v 'root' | awk '{print $1}' | sort -u | head -n 1)

if [ -z "$WEB_USER" ]; then
    echo "   ⚠️ Auto-detection failed or returned an empty value."
    echo "   Common users: www-data (Debian/Ubuntu), nginx (RHEL/CentOS), _www (macOS)."
    read -p "   Please manually enter the Web Server User (e.g., www-data): " MANUAL_USER
    WEB_USER="$MANUAL_USER"
else
    echo "   ✅ Detected User: ${WEB_USER}"
fi

# --- 2. Validation ---
if [ -z "$WEB_USER" ]; then
    echo "   ❌ ERROR: Web Server User cannot be empty. Script aborted."
    exit 1
fi

echo "   CLI User (Owner): ${CLI_USER}"
echo "   Web Server User/Group: ${WEB_USER}"

# --- 3. Execute Permission Changes ---

echo -e "\n2. Setting permissions and ownership for the 'data/' directory..."

# 3a. Set Group Ownership and Writable Permissions for Data Directory
# Change ownership to the CLI user and the web server group
# This ensures the CLI user has control and the web group has necessary write access for SQLite
chown -R "${CLI_USER}:${WEB_USER}" data/

# Give owner and group Read/Write/Execute access (775)
chmod -R 775 data/

echo "   Set ownership of data/ to ${CLI_USER}:${WEB_USER} and permissions to 775."

# 3b. Ensure the SQLite file has the correct file permissions if it exists
if [ -f "$DB_FILE" ]; then
    echo "   Setting permissions for database file ${DB_FILE}..."
    # Grant Read/Write access (664) to the CLI user (owner) and the Web Server group
    chmod 664 "$DB_FILE"
    echo "   Set file permissions for ${DB_FILE} to 664."
fi

# 3c. Set Execute Permission for the CLI Script
echo -e "\n3. Setting execute permission for bin/tackle.php..."
# Grant Read/Execute (755) permission to the owner (CLI user)
chmod 755 bin/tackle.php
echo "   Set permissions for bin/tackle.php to 755."

# 3d. General Permissions for Source Code
echo -e "\n4. Setting general permissions for source code (src/ and config/)..."

# Set directories to 755 (rwx for owner, rx for group/other)
find src/ -type d -exec chmod 755 {} \;
find config/ -type d -exec chmod 755 {} \;
echo "   Set directories to 755."

# Set files to 644 (rw for owner, r for group/other)
find src/ -type f -exec chmod 644 {} \;
find config/ -type f -exec chmod 644 {} \;
echo "   Set files to 644."

echo -e "\n--- Setup Complete ---"
echo "All required file permissions have been set."
