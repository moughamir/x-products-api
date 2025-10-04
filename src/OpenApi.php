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
 * @OA\Property(property="id", type="integer", example=9876),
 * @OA\Property(property="product_id", type="integer", example=12345),
 * @OA\Property(property="position", type="integer", example=1),
 * @OA\Property(property="src", type="string", format="url", example="http://example.com/image.jpg"),
 * @OA\Property(property="width", type="integer", example=1000),
 * @OA\Property(property="height", type="integer", example=1000)
 * )
 * @OA\Schema(
 * schema="Product",
 * type="object",
 * title="Product",
 * @OA\Property(property="id", type="integer", example=12345),
 * @OA\Property(property="title", type="string", example="Example Product"),
 * @OA\Property(property="handle", type="string", example="example-product"),
 * @OA\Property(property="body_html", type="string", example="<div>A full description.</div>"),
 * @OA\Property(property="vendor", type="string", example="Vendor Co."),
 * @OA\Property(property="product_type", type="string", example="T-Shirt"),
 * @OA\Property(property="tags", type="string", example="tag1,tag2"),
 * @OA\Property(property="price", type="number", format="float", example=19.99),
 * @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=25.00),
 * @OA\Property(property="variants", type="array", @OA\Items(type="object")),
 * @OA\Property(property="options", type="array", @OA\Items(type="object")),
 * @OA\Property(property="images", type="array", @OA\Items(ref="#/components/schemas/Image"))
 * )
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
    //
}
