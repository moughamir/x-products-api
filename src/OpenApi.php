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
 * description="Product object matching the ApiProduct TypeScript interface",
 * @OA\Property(property="id", type="string", example="1059061125", description="Product ID as string"),
 * @OA\Property(property="title", type="string", example="Task Floor Lamp"),
 * @OA\Property(property="handle", type="string", example="task-floor-lamp"),
 * @OA\Property(property="body_html", type="string", example="<p>Product description with HTML</p>"),
 * @OA\Property(property="price", type="number", format="float", example=629.4, description="Minimum variant price"),
 * @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=null),
 * @OA\Property(property="images", type="array", @OA\Items(ref="#/components/schemas/Image")),
 * @OA\Property(property="product_type", type="string", example="Lighting/Lamps/Floor Lamps"),
 * @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"Brand: Original BTC", "Collection: Task", "Color: Black"}),
 * @OA\Property(property="vendor", type="string", example="MyStore"),
 * @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/ProductVariant")),
 * @OA\Property(property="options", type="array", @OA\Items(ref="#/components/schemas/ProductOption")),
 * @OA\Property(property="created_at", type="string", format="date-time", example="2015-06-15T23:22:45-07:00"),
 * @OA\Property(property="updated_at", type="string", format="date-time", example="2025-09-25T10:06:55-07:00")
 * )
 * @OA\Schema(
 * schema="ProductVariant",
 * type="object",
 * title="ProductVariant",
 * description="Product variant matching the ApiProductVariant TypeScript interface",
 * @OA\Property(property="id", type="string", example="3290425605", description="Variant ID as string"),
 * @OA\Property(property="product_id", type="string", example="1059061125", description="Product ID as string"),
 * @OA\Property(property="title", type="string", example="Black"),
 * @OA\Property(property="option1", type="string", nullable=true, example="Black"),
 * @OA\Property(property="option2", type="string", nullable=true, example=null),
 * @OA\Property(property="option3", type="string", nullable=true, example=null),
 * @OA\Property(property="sku", type="string", nullable=true, example="SSBP-54-139"),
 * @OA\Property(property="requires_shipping", type="boolean", example=true),
 * @OA\Property(property="taxable", type="boolean", example=true),
 * @OA\Property(property="featured_image", ref="#/components/schemas/Image", nullable=true, description="Featured image object (not URL)"),
 * @OA\Property(property="available", type="boolean", example=true),
 * @OA\Property(property="price", type="number", format="float", example=959.4),
 * @OA\Property(property="grams", type="integer", example=5488),
 * @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=null),
 * @OA\Property(property="position", type="integer", example=1),
 * @OA\Property(property="created_at", type="string", format="date-time", example="2015-06-15T23:22:45-07:00"),
 * @OA\Property(property="updated_at", type="string", format="date-time", example="2025-09-25T10:06:55-07:00")
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
