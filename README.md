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

-   Docker
-   Docker Compose

### Installation

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/your-username/x-products-api.git
    cd x-products-api
    ```

2.  **Build and run the Docker containers:**

    ```bash
    docker-compose up -d --build
    ```

3.  **Process the product data:**

    The initial product data is provided as JSON files in the `data/json/products_by_id` directory. To process this data and populate the SQLite database, run the following command:

    ```bash
    docker-compose exec api composer process:products
    ```

    This command will read the JSON files, process the data, and insert it into the SQLite database located at `data/sqlite/database.sqlite`.

## API Documentation

The API documentation is available in two formats:

-   **Swagger UI**: `http://localhost:8080/cosmos/swagger-ui`
-   **OpenAPI JSON**: `http://localhost:8080/cosmos/openapi.json`

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
