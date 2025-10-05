---
description: Repository Information Overview
alwaysApply: true
---

# Cosmos Product API Information

## Summary
A RESTful API for Shopify products that provides product data, search functionality, and image serving capabilities. The API requires authentication via an API key header and supports JSON and MessagePack response formats.

## Structure
- **bin/**: Contains CLI tools for processing product data and setup scripts
- **config/**: Configuration files for the application and database
- **data/**: Storage for SQLite database and JSON product data
- **src/**: Core application code including controllers, models, and services
- **templates/**: Twig templates for the Swagger UI documentation
- **vendor/**: Composer dependencies

## Language & Runtime
**Language**: PHP
**Version**: Requires PHP with PDO SQLite extension
**Build System**: Composer
**Package Manager**: Composer

## Dependencies
**Main Dependencies**:
- slim/slim (^4.15): PHP micro-framework
- slim/twig-view (^3.3): Twig template integration
- php-di/php-di (^7.1): Dependency injection container
- guzzlehttp/guzzle (^7.10): HTTP client
- zircote/swagger-php (^4.8): OpenAPI documentation generator
- salsify/json-streaming-parser (^8.3): JSON parser
- nyholm/psr7 (^1.8): PSR-7 implementation

## Build & Installation
```bash
# Install dependencies
composer install

# Process product data
composer run process:products

# Generate OpenAPI documentation
composer run docs:generate

# Set up permissions
chmod +x bin/prepare.sh
./bin/prepare.sh
```

## Database
**Type**: SQLite
**Location**: data/sqlite/products.sqlite
**Schema**: data/sqlite/database_schema.sql

## API Endpoints
**Base Path**: /cosmos
- GET /products: List all products with pagination
- GET /products/{key}: Get product by ID or handle
- GET /products/search: Search products by query
- GET /collections/{handle}: Get products by collection
- GET /cdn/{path}: Image proxy for product images
- GET /swagger-ui: API documentation UI
- GET /openapi.json: OpenAPI specification

## Authentication
**Method**: API Key
**Header**: X-API-KEY
**Configuration**: config/app.php

## Data Processing
The application processes product data from JSON files stored in data/json/products_by_id/ using the ProductProcessor service, which is executed via the bin/tackle.php CLI script.
