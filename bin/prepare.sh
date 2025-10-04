#!/bin/bash
# X-Products API Permission Setup Script - CORRECTED for Hostinger

# --- Configuration Variables ---
CLI_USER=$(whoami)
DB_FILE="data/sqlite/products.sqlite"
# We will NOT explicitly define the group, as Hostinger's setup rejects "user:user".
# We'll rely on the default group membership for 775/664 to work.
# -------------------------------

echo "--- X-Products API Permission Setup (CORRECTED Hostinger) ---"
echo "CLI User (Owner) is: ${CLI_USER}"

# --- 1. Validate Project Structure ---
if [ ! -d "data" ] || [ ! -d "src" ] || [ ! -f "bin/tackle.php" ]; then
    echo "❌ ERROR: Cannot find required directories (data/, src/) or file (bin/tackle.php)."
    echo "Please run this script from the root directory of your project."
    exit 1
fi


## 2. Set Ownership and Writable Permissions for Data Directory

echo -e "\n2. Setting ownership and permissions for the 'data/' directory..."

# FIX: Change ownership to only the CLI user.
# The web process runs as this user, so this ensures write access.
chown -R "${CLI_USER}" data/

# Give owner and group Read/Write/Execute access (775).
# The web server will run as the owner and automatically have R/W access to this directory.
chmod -R 775 data/

echo "   Set ownership of data/ recursively to: ${CLI_USER} (Owner only)."
echo "   Set directory permissions to 775."


## 3. Set Permissions for Critical Files

# 3a. Ensure the SQLite file has the correct file permissions if it exists
if [ -f "$DB_FILE" ]; then
    echo "   Setting permissions for database file ${DB_FILE}..."
    # Grant Read/Write access (664) to the owner and the group.
    chmod 664 "$DB_FILE"
    echo "   Set file permissions for ${DB_FILE} to 664 (R/W for owner/group)."
fi

# 3b. Set Execute Permission for the CLI Script
echo "   Setting execute permission for bin/tackle.php..."
# Grant Read/Execute (755) permission for the CLI user.
chmod 755 bin/tackle.php
echo "   Set permissions for bin/tackle.php to 755."

echo ---
## 4. General Permissions for Source Code

echo -e "\n4. Setting secure general permissions for source code (src/ and config/)..."

# Set directories to 755 (rwx for owner, rx for group/other)
find src/ config/ -type d -exec chmod 755 {} \;
echo "   Set directories (src/, config/) to 755."

# Set all other source files to 644 (rw for owner, r for group/other)
find src/ config/ -type f -exec chmod 644 {} \;
find index.php -type f -exec chmod 644 {} \;
echo "   Set files to 644."

echo -e "\n--- Setup Complete ---"
echo "The required permissions are set using Owner-Only ownership for stability on Hostinger. Please test your CLI and API now! 🚀"
