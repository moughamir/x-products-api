<?php
// src/Models/Product.php

namespace App\Models;

class Product
{
    // Fields available in the database (or accessible via SELECT queries)
    public const ALLOWED_PRODUCT_FIELDS = [
        'id', 'name', 'handle', 'body_html', 'price', 'compare_at_price',
        'category', 'in_stock', 'rating', 'review_count', 'tags', 'vendor',
        'bestseller_score', // New calculated score
        'raw_json'
    ];

    public int $id;
    public string $name;
    public string $handle;
    public ?string $body_html;
    public float $price;
    public ?float $compare_at_price;
    public ?string $category;
    public bool $in_stock;
    public ?float $rating;
    public ?int $review_count;
    public ?string $tags;
    public ?string $vendor;
    public ?float $bestseller_score;
    public ?string $raw_json;

    // Arrays for related data, populated by the API controller
    public array $images = [];
    public array $variants = [];
    public array $options = [];
}
