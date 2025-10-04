Here are the full API endpoints and their parameters for your Slim PHP project, based on the final codebase, including authentication, MessagePack support, and the image reverse proxy.

The API base path is assumed to be `/cosmos`.

## 🔑 Authentication

All protected endpoints require the following header:

| Header Name | Value | Description |
| :--- | :--- | :--- |
| `X-API-KEY` | `0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539` | Must match the value defined in `config/app.php`. |

***

## 🛍️ Product Endpoints

These endpoints return product data, optionally including variants, images, and options (full data is only guaranteed for single product lookup).

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/cosmos/products/{key}` | Retrieves a **single product** by its **numerical ID** or its **string handle**. This endpoint is fully optimized to include **images, variants, and options**. |
| `GET` | `/cosmos/products` | Retrieves a **paginated list** of all products. Returns limited fields for speed. |
| `GET` | `/cosmos/products/search` | Performs a **Full-Text Search (FTS)** across product fields. |

### Parameters for Product Endpoints

| Parameter | Applies to | Type | Default | Description |
| :--- | :--- | :--- | :--- | :--- |
| `key` | `{key}` endpoint | ID (int) or Handle (string) | N/A | The product identifier. |
| `format` | All | string | `json` | Output format. Accepts `json` or **`msgpack`** (optimized binary format). |
| `limit` | `/products` | integer | `50` | The maximum number of items to return (max 100). |
| `page` | `/products` | integer | `1` | The pagination page number. |
| `q` | `/products/search` | string | N/A | The FTS query string. |

***

## 📦 Collection Endpoints

These endpoints retrieve lists of products based on defined collection logic (e.g., bestsellers, featured).

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/cosmos/collections/{handle}` | Retrieves a list of products belonging to a specified collection (e.g., `bestsellers`, `featured`, `all`). |

### Parameters for Collection Endpoints

| Parameter | Applies to | Type | Default | Description |
| :--- | :--- | :--- | :--- | :--- |
| `handle` | `{handle}` endpoint | string | N/A | The collection slug (e.g., `bestsellers`, `sale`). |
| `format` | All | string | `json` | Output format. Accepts `json` or **`msgpack`**. |
| `limit` | All | integer | `50` | The maximum number of items to return (max 100). |
| `page` | All | integer | `1` | The pagination page number. |

***

## 🖼️ Image Reverse Proxy

This unauthenticated endpoint acts as a reverse proxy, fetching images from the external CDN (`https://cdn.shopify.com`) and serving them from your domain to maintain branding and avoid mixed content issues.

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/cosmos/cdn/{path:.*}` | Fetches an image from the external CDN, streams it to the user, and uses the appropriate MIME type. |

### Usage

The `src` field returned by the product endpoints (e.g., `/products/{key}`) will be automatically modified to use this endpoint.

**Example:**

| Original CDN URL | API Returned `src` Value |
| :--- | :--- |
| `https://cdn.shopify.com/s/files/1/0000/products/blue_shirt.jpg` | `/cosmos/cdn/s/files/1/0000/products/blue_shirt.jpg` |
