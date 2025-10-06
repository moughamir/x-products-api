# X-Products API

This is a RESTful API for managing and retrieving product information. The API is built with Slim Framework and uses a SQLite database for data storage.

## Features

-   **Product Management**: CRUD operations for products.
-   **Collection Management**: Group products into collections.
-   **Search**: Full-text search for products.
-   **Image Proxy**: Reverse proxy for serving images from an external CDN.
-   **API Key Authentication**: Secure your API with an API key.
-   **Dockerized**: Easy to set up and run with Docker.

## Getting Started

### Prerequisites

-   **Docker** (for containerized deployment)
-   **Docker Compose**
-   **OR** PHP 8.2+ with SQLite extension (for local development)
-   **Composer** (for dependency management)

### Quick Start (Automated)

The easiest way to get started is to use the automated build process:

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/your-username/x-products-api.git
    cd x-products-api
    ```

2.  **Install dependencies:**

    ```bash
    composer install
    ```

    This automatically:
    - Installs vendor dependencies
    - Clears PHP opcache
    - Generates OpenAPI documentation
    - Sets up the database (if empty)

3.  **Start the development server:**

    ```bash
    php -S localhost:8080 -t public
    ```

4.  **Access the API:**

    - API Base: `http://localhost:8080/cosmos`
    - Swagger UI: `http://localhost:8080/cosmos/swagger-ui`
    - OpenAPI Spec: `http://localhost:8080/cosmos/openapi.json`

### Docker Installation

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/your-username/x-products-api.git
    cd x-products-api
    ```

2.  **Build and run the Docker containers:**

    ```bash
    docker-compose up -d --build
    ```

3.  **The build process runs automatically inside the container.**

    The database will be populated automatically if it's empty. To force a rebuild:

    ```bash
    docker-compose exec api composer db:rebuild
    ```

### Manual Setup (Advanced)

If you need more control over the setup process:

1.  **Install dependencies (without running build):**

    ```bash
    composer install --no-scripts
    ```

2.  **Generate OpenAPI documentation:**

    ```bash
    composer docs:generate
    ```

3.  **Setup database:**

    ```bash
    # Interactive mode (asks for confirmation)
    php bin/tackle.php

    # Skip if database already has data
    php bin/tackle.php --skip-if-exists

    # Force rebuild (WARNING: deletes all data)
    php bin/tackle.php --force
    ```

4.  **Clear cache:**

    ```bash
    composer app:clear-cache
    ```

## Available Commands

### Composer Scripts

```bash
# Full build process (runs automatically after composer install/update)
composer build

# Generate OpenAPI documentation
composer docs:generate

# Setup database (skips if already populated - safe for production)
composer db:setup

# Force rebuild database (WARNING: deletes all data)
composer db:rebuild

# Clear PHP opcache
composer app:clear-cache

# List all available scripts
composer run-script --list
```

### Database Setup Tool

The `bin/tackle.php` script provides flexible database management:

```bash
# Interactive mode (asks for confirmation)
php bin/tackle.php

# Skip if database already has data (safe for production)
php bin/tackle.php --skip-if-exists

# Force rebuild (WARNING: deletes all data)
php bin/tackle.php --force

# Show help
php bin/tackle.php --help
```

**Environment Variables:**

```bash
# Set environment (default: production)
export APP_ENV=development
php bin/tackle.php

# Production requires --force flag
export APP_ENV=production
php bin/tackle.php --force
```

## API Documentation

The API documentation is available in two formats:

-   **Swagger UI**: `http://localhost:8080/cosmos/swagger-ui`
-   **OpenAPI JSON**: `http://localhost:8080/cosmos/openapi.json`

The OpenAPI documentation is automatically generated from PHP annotations using `swagger-php`. To regenerate:

```bash
composer docs:generate
```

### API Key

All API endpoints (except for the documentation and image proxy) require an API key. The API key must be included in the `X-API-Key` header of your request.

**API Key**: `0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539`

### Endpoints

#### Products

-   `GET /cosmos/products`: Get a paginated list of all products.
    -   **Query Parameters**:
        -   `page` (int, optional, default: 1): The page number.
        -   `limit` (int, optional, default: 50, max: 100): The number of products per page.
        -   `fields` (string, optional, default: \*): A comma-separated list of fields to return.
        -   `format` (string, optional, enum: json, msgpack, default: json): The response format.
-   `GET /cosmos/products/search`: Search for products.
    -   **Query Parameters**:
        -   `q` (string, required): The search query.
        -   `page` (int, optional, default: 1): The page number.
        -   `limit` (int, optional, default: 50, max: 100): The number of products per page.
        -   `fields` (string, optional, default: \*): A comma-separated list of fields to return.
        -   `format` (string, optional, enum: json, msgpack, default: json): The response format.
-   `GET /cosmos/products/{key}`: Get a single product by ID or handle.
    -   **Path Parameters**:
        -   `key` (string, required): The product ID or handle.
    -   **Query Parameters**:
        -   `format` (string, optional, enum: json, msgpack, default: json): The response format.

#### Collections

-   `GET /cosmos/collections/{handle}`: Get a list of products in a collection.
    -   **Path Parameters**:
        -   `handle` (string, required): The collection handle. Supported handles: `all`, `featured`, `sale`, `new`, `bestsellers`, `trending`.
    -   **Query Parameters**:
        -   `page` (int, optional, default: 1): The page number.
        -   `limit` (int, optional, default: 50, max: 100): The number of products per page.
        -   `fields` (string, optional, default: \*): A comma-separated list of fields to return.
        -   `format` (string, optional, enum: json, msgpack, default: json): The response format.

#### Image Proxy

-   `GET /cosmos/cdn/{path:.*}`: Reverse proxy for serving images from an external CDN.
    -   **Path Parameters**:
        -   `path` (string, required): The path to the image on the external CDN.

## Product Processing

The product processing is handled by the `bin/tackle.php` script, which uses the `ProductProcessor` service. The process involves the following steps:

1.  **Reading JSON data**: The script reads the product data from the JSON files located in `data/json/products_by_id`.
2.  **Database Schema**: It creates the necessary database schema in the SQLite database.
3.  **Data Insertion**: The product data is then inserted into the `products` table.
4.  **FTS5 Indexing**: A full-text search index is created on the `products` table to enable fast and efficient searching.

## Composer Optimization

The `composer.json` file has been optimized for better performance and clarity. The following changes have been made:

-   **Autoloader Optimization**: The `dump-autoload -o` command has been added to the `post-autoload-dump` script to create a classmap for faster class loading.
-   **Script Descriptions**: Descriptions have been added to the scripts to make them more understandable.

## Docker Optimization

The `Dockerfile` and `docker-compose.yml` files have been updated for better performance and security. The following changes have been made:

-   **Multi-stage builds**: The `Dockerfile` now uses a multi-stage build to create a smaller final image.
-   **Non-root user**: The application now runs as a non-root user for improved security.
-   **Optimized dependencies**: The `composer install` command now uses the `--no-dev` and `--optimize-autoloader` flags to install only the necessary dependencies and optimize the autoloader.
