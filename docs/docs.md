# Cosmos Product API Documentation

**Version:** 1.0.0
**Base URL:** `/cosmos`
**Auth:** Requires `X-API-KEY` header for most endpoints.
**License:** MIT

The Cosmos Product API provides access to product data, search capabilities, and image delivery.

---

## Authentication

Most endpoints require an API key:

```
X-API-KEY: your_api_key_here
```

---

## Endpoints

### 1. **List Products**

**GET** `/products`
Retrieves a paginated list of all products (limited fields).

#### Query Parameters

| Name     | Type    | Default      | Description                                             |
| -------- | ------- | ------------ | ------------------------------------------------------- |
| `page`   | integer | 1            | Page number                                             |
| `limit`  | integer | 50 (max 100) | Number of results per page                              |
| `fields` | string  | `*`          | Comma-separated list of fields (e.g., `id,title,price`) |
| `format` | string  | `json`       | Response format (`json` or `msgpack`)                   |

#### Responses

- **200 OK**: Returns `ProductList` object.
- **default**: Error response.

---

### 2. **Search Products**

**GET** `/products/search`
Performs a Full-Text Search (FTS) for products.

#### Query Parameters

| Name     | Type    | Required | Default      | Description         |
| -------- | ------- | -------- | ------------ | ------------------- |
| `q`      | string  | Yes      | -            | Search query string |
| `page`   | integer | No       | 1            | Page number         |
| `limit`  | integer | No       | 50 (max 100) | Number of results   |
| `fields` | string  | No       | `*`          | Fields to return    |
| `format` | string  | No       | `json`       | Response format     |

#### Responses

- **200 OK**: Returns matching `ProductList`.
- **default**: Error response.

🔒 Requires API Key.

---

### 3. **Get Product by ID/Handle**

**GET** `/products/{key}`
Retrieves a single product by its ID or handle (full data).

#### Path Parameters

| Name  | Type   | Required | Example      |
| ----- | ------ | -------- | ------------ |
| `key` | string | Yes      | `1234567890` |

#### Query Parameters

| Name     | Type   | Default | Options           |
| -------- | ------ | ------- | ----------------- |
| `format` | string | `json`  | `json`, `msgpack` |

#### Responses

- **200 OK**: Returns `Product`.
- **404 Not Found**: Product does not exist.
- **default**: Error response.

🔒 Requires API Key.

---

### 4. **Get Products from Collection**

**GET** `/collections/{handle}`
Retrieves products from a specific collection.

#### Path Parameters

| Name     | Type   | Required | Example    |
| -------- | ------ | -------- | ---------- |
| `handle` | string | Yes      | `featured` |

#### Query Parameters

Same as `/products`.

#### Responses

- **200 OK**: Returns `ProductList`.
- **404 Not Found**: Invalid collection handle.

🔒 Requires API Key.

---

### 5. **Image Proxy**

**GET** `/cdn/{path}`
Fetch and serve images from an external CDN.

#### Path Parameters

| Name   | Type   | Required | Description       |
| ------ | ------ | -------- | ----------------- |
| `path` | string | Yes      | Image path on CDN |

#### Responses

- **200 OK**: Returns image stream.
- **404 Not Found**: Image not available.

---

## Schemas

### Product

```json
{
  "id": 12345,
  "title": "Example Product",
  "handle": "example-product",
  "body_html": "<div>A full description.</div>",
  "vendor": "Vendor Co.",
  "product_type": "T-Shirt",
  "tags": "tag1,tag2",
  "price": 19.99,
  "compare_at_price": 25,
  "variants": [],
  "options": [],
  "images": [
    {
      "id": 9876,
      "product_id": 12345,
      "position": 1,
      "src": "http://example.com/image.jpg",
      "width": 1000,
      "height": 1000
    }
  ]
}
```

### ProductList

```json
{
  "products": [ Product, ... ],
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 50,
    "total_pages": 2
  }
}
```
