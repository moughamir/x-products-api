# X-Products API Project Structure

The structure is based on a standard PHP application with a dependency injection container (PHP-DI) and a micro-framework (Slim). The directories follow the recommended PSR-4 autoloading standard.

```shell
.
├── .gitignore
├── .htaccess
├── bin
│   └── tackle.php
├── clear_opcache.php
├── composer.json
├── config
│   ├── app.php
│   └── database.php
├── data
│   ├── .gitkeep
│   ├── json
│   │   └── products_by_id
│   │       └── 911984.json
│   └── sqlite
│       ├── database_schema.sql
│       └── products.sqlite
├── docs
│   ├── api.md
│   └── struct.md
├── index.php
├── src
│   ├── App.php
│   ├── Controllers
│   │   └── ApiController.php
│   ├── Middleware
│   │   └── ApiKeyMiddleware.php
│   ├── Models
│   │   ├── Image.php
│   │   ├── MsgPackResponse.php
│   │   └── Product.php
│   └── Services
│       ├── ImageProxy.php
│       ├── ImageService.php
│       ├── ProductProcessor.php
│       └── ProductService.php
└── templates
    └── swagger.html
```

## File Permissions and Roles

This table details the minimum required file system permissions for the API to initialize (via CLI) and serve requests (via Web Server, e.g., Apache/Nginx running as `www-data`).

| Path | Required Access | Owner/Group | Numeric Mode (Example) | Purpose |
 | ----- | ----- | ----- | ----- | ----- |
| **`data/`** | **Read, Write, Execute** | Web Server & CLI User | `775` or `777` | Allows file creation and directory traversal inside the data folder. |
| **`data/sqlite/`** | **Read, Write, Execute** | Web Server & CLI User | `775` or `777` | Allows CLI to create the SQLite file and the web server to access the directory. |
| **`data/sqlite/products.sqlite`** | **Read, Write** | Web Server & CLI User | `664` or `666` | **CRITICAL:** Must be writable by the user running `bin/tackle.php` (for setup) and readable by the Web Server (for API lookups). |
| **`bin/tackle.php`** | **Read, Execute** | CLI User | `755` | Allows the command-line utility to be executed. |
| **`src/` and sub-folders** | **Read, Execute** | Web Server & CLI User | `755` | Standard read/execute for directories. |
| **All PHP files (`.php`)** | **Read** | Web Server & CLI User | `644` | Standard read-only access for source code.




## CLI Commands for Setting Permissions

The commands below use `chmod` and `chown`. You'll need to replace `{WEB_SERVER_USER}` with the actual user your web server runs as (e.g., `www-data`, `nginx`, `_www`).

### 1\. Set Permissions for the Database Directory (`data/sqlite/`)

The CLI user needs to **create** the SQLite file, and the Web Server needs **read/write** access to the entire directory to handle file locks/updates.

```bash
# 1. Ensure the directory has Read/Write/Execute for owner and group (775)
chmod -R 775 data/sqlite/

# 2. Change the group ownership to the web server user/group
# This is often 'www-data' on Debian/Ubuntu systems, or 'nginx' on others.
# Use the CLI user as the owner, and the web group as the group.
chown -R $(whoami):{WEB_SERVER_USER} data/sqlite/
```

### 2\. Set Permissions for the Database File (`products.sqlite`)

The database file is the most critical asset for read/write access. If the file already exists, run this after step 1.

```bash
# 1. Grant Read/Write access (664) to the CLI user (owner) and the Web Server group.
chmod 664 data/sqlite/products.sqlite

# 2. Ensure the file belongs to the CLI user and the Web Server group.
chown $(whoami):{WEB_SERVER_USER} data/sqlite/products.sqlite
```

-----

### 3\. Set Permissions for the CLI Executable (`bin/tackle.php`)

This file needs **execute permission** so you can run the setup script from the command line.

```bash
# Grant Read/Execute (755) permission to the owner (CLI user).
chmod 755 bin/tackle.php
```

### 4\. General Source Code Permissions

All other PHP files and directories should be readable and not generally writable by the web server for security.

```bash
# Set directories to 755 (rwx for owner, rx for group/other)
find src/ -type d -exec chmod 755 {} \;
find config/ -type d -exec chmod 755 {} \;

# Set files to 644 (rw for owner, r for group/other)
find src/ -type f -exec chmod 644 {} \;
find config/ -type f -exec chmod 644 {} \;
```


## Command Line Checks for Web Server User

The web server user is typically the account under which PHP-FPM or the web server process (Apache, Nginx) is running.

### 1\. Using `ps` (Check Active Processes)

This is the most reliable method. It lists running processes and their owners.

| Web Server | Command | Expected Output (User) |
| :--- | :--- | :--- |
| **Apache** | `ps aux | grep -E '[a]pache|[h]ttpd' | grep -v root` | `www-data`, `daemon`, or `_www` |
| **Nginx** | `ps aux | grep -E '[n]ginx' | grep -v root` | `www-data` or `nginx` |
| **PHP-FPM** | `ps aux | grep -E '[f]pm'` | `www-data`, `nginx`, or a custom user (e.g., `php-user`) |

Look at the first column of the output from these commands—it will be the **Web Server User** you need to use for the `chown` commands.

### 2\. Check Configuration Files

If the server is using Nginx, the user is often specified directly in the main configuration file.

```bash
# For Nginx
grep "user " /etc/nginx/nginx.conf
```

### 3\. Using PHP

If you can create a temporary PHP file and access it via a browser, you can use the following command inside that file:

```php
<?php echo exec('whoami'); ?>
```

The output displayed in the browser is your **Web Server User**.

-----

## Final Steps to Set Permissions

Once you determine the user (e.g., let's assume it's `www-data`), you can execute the final, definitive commands (as `root` or using `sudo`):

1.  **Set Group Ownership and Writable Permissions for Data Directory:**

    ```bash
    # Change ownership to your CLI user and the web server group
    chown -R $(whoami):www-data data/

    # Give owner and group R/W/X access (775)
    chmod -R 775 data/
    ```

2.  **Set Execute Permission for the CLI Script:**

    ```bash
    chmod 755 bin/tackle.php
    ```

This ensures the user you SSH in as has control over the files, and the `www-data` group has the necessary write access for the SQLite file.



That's a great idea to automate the permission setting\! You're right that setting permissions requires knowing the **Web Server User** (e.g., `www-data`, `nginx`), which depends on your OS and server setup.

I can't run commands to detect your server environment (OS, LAMP stack versions, etc.). However, I can provide a **dynamic shell script** that first attempts to **auto-detect** the Web Server User and then executes the required `chown` and `chmod` commands using that detected user.

-----

## 🚀 Permission Configuration Shell Script

This script is designed to be run from the root directory of your project (where `data/` and `bin/` are located). It uses the most common detection methods and falls back to manual entry if detection fails.

Save this content as a file named `set\_perms.sh` and run it with `sudo ./set\_perms.sh`.

```bash
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
```


total 52
drwxr-xr-x 8 u800171071 o1007194376 4096 Oct  4 16:39 .
drwxr-xr-x 9 u800171071 o1007194376 4096 Oct  4 16:39 ..
drwxr-xr-x 2 u800171071 o1007194376 4096 Oct  4 16:45 bin
-rw-r--r-- 1 u800171071 o1007194376  208 Oct  4 16:39 clear_opcache.php
-rw-r--r-- 1 u800171071 o1007194376  616 Oct  4 16:39 composer.json
drwxr-xr-x 2 u800171071 o1007194376 4096 Oct  4 16:39 config
drwxrwxr-x 3 u800171071 o1007194376 4096 Oct  4 16:39 data
drwxr-xr-x 2 u800171071 o1007194376 4096 Oct  4 16:39 docs
-rw-r--r-- 1 u800171071 o1007194376   22 Oct  4 16:39 .gitignore
-rw-r--r-- 1 u800171071 o1007194376  414 Oct  4 16:39 .htaccess
-rw-r--r-- 1 u800171071 o1007194376  115 Oct  4 16:39 index.php
drwxr-xr-x 6 u800171071 o1007194376 4096 Oct  4 16:39 src
drwxr-xr-x 2 u800171071 o1007194376 4096 Oct  4 16:39 templates
