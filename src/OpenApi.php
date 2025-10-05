<?php
// src/OpenApi.php

namespace App;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 * @OA\Info(
 * version="1.0.0",
 * title="Cosmos Product API",
 * description="The final source for product data, search, and images. Authentication requires an **X-API-KEY** header.",
 * @OA\License(name="MIT")
 * ),
 * @OA\Server(
 * url="/cosmos",
 * description="API Base Path"
 * )
 * )
 * @OA\SecurityScheme(
 * securityScheme="ApiKeyAuth",
 * type="apiKey",
 * in="header",
 * name="X-API-KEY"
 * )
 * * * --- OpenAPI Schemas for Data Models ---
 * @OA\Schema(
 * schema="Image",
 * type="object",
 * title="Image",
 * @OA\Property(property="id", type="string", example="9876"),
 * @OA\Property(property="product_id", type="string", example="12345"),
 * @OA\Property(property="position", type="integer", example=1),
 * @OA\Property(property="src", type="string", format="url", example="http://example.com/image.jpg"),
 * @OA\Property(property="width", type="integer", example=1000),
 * @OA\Property(property="height", type="integer", example=1000),
 * @OA\Property(property="alt", type="string", nullable=true, example="Blue t-shirt"),
 * @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 * @OA\Property(property="updated_at", type="string", format="date-time", nullable=true),
 * @OA\Property(property="variant_ids", type="array", @OA\Items(type="string"))
 * )
 * @OA\Schema(
 * schema="Product",
 * type="object",
 * title="Product",
 * @OA\Property(property="id", type="string", example="12345"),
 * @OA\Property(property="title", type="string", example="Example Product"),
 * @OA\Property(property="handle", type="string", example="example-product"),
 * @OA\Property(property="body_html", type="string", example="<div>A full description.</div>"),
 * @OA\Property(property="vendor", type="string", example="Vendor Co."),
 * @OA\Property(property="product_type", type="string", example="T-Shirt"),
 * @OA\Property(property="tags", type="string", example="tag1,tag2"),
 * @OA\Property(property="price", type="number", format="float", example=19.99),
 * @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=25.00),
 * @OA\Property(property="in_stock", type="boolean", example=true),
 * @OA\Property(property="rating", type="number", format="float", nullable=true, example=4.5),
 * @OA\Property(property="review_count", type="integer", nullable=true, example=42),
 * @OA\Property(property="quantity", type="integer", nullable=true, example=10),
 * @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 * @OA\Property(property="updated_at", type="string", format="date-time", nullable=true),
 * @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/ProductVariant")),
 * @OA\Property(property="options", type="array", @OA\Items(ref="#/components/schemas/ProductOption")),
 * @OA\Property(property="images", type="array", @OA\Items(ref="#/components/schemas/Image"))
 * )
 * @OA\Schema(
 * schema="ProductVariant",
 * type="object",
 * title="ProductVariant",
 * @OA\Property(property="id", type="string", example="98765"),
 * @OA\Property(property="product_id", type="string", example="12345"),
 * @OA\Property(property="title", type="string", example="Small / Blue"),
 * @OA\Property(property="option1", type="string", nullable=true, example="Small"),
 * @OA\Property(property="option2", type="string", nullable=true, example="Blue"),
 * @OA\Property(property="option3", type="string", nullable=true),
 * @OA\Property(property="sku", type="string", nullable=true, example="PROD-SM-BLU"),
 * @OA\Property(property="requires_shipping", type="boolean", example=true),
 * @OA\Property(property="taxable", type="boolean", example=true),
 * @OA\Property(property="featured_image", type="string", nullable=true, example="http://example.com/variant-image.jpg"),
 * @OA\Property(property="available", type="boolean", example=true),
 * @OA\Property(property="price", type="number", format="float", example=19.99),
 * @OA\Property(property="grams", type="integer", example=200),
 * @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=25.00),
 * @OA\Property(property="position", type="integer", example=1),
 * @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 * @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 * schema="ProductOption",
 * type="object",
 * title="ProductOption",
 * @OA\Property(property="id", type="string", example="54321"),
 * @OA\Property(property="product_id", type="string", example="12345"),
 * @OA\Property(property="name", type="string", example="Size"),
 * @OA\Property(property="position", type="integer", example=1),
 * @OA\Property(property="values", type="array", @OA\Items(type="string"))
 * )
 *
 * @OA\Schema(
 * schema="ProductList",
 * type="object",
 * title="ProductList",
 * @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product")),
 * @OA\Property(property="meta", type="object",
 * @OA\Property(property="total", type="integer", example=100),
 * @OA\Property(property="page", type="integer", example=1),
 * @OA\Property(property="limit", type="integer", example=50),
 * @OA\Property(property="total_pages", type="integer", example=2)
 * )
 * )
 */
class OpenApi
{
    // This class is a container for the OpenAPI annotations.
}
