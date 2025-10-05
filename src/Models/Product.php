<?php
// src/Models/Product.php

namespace App\Models;

class Product
{
    // Fields available in the database (or accessible via SELECT queries)
    public const ALLOWED_PRODUCT_FIELDS = [
        'id', 'title', 'handle', 'body_html', 'price', 'compare_at_price',
        'product_type', 'in_stock', 'rating', 'review_count', 'tags', 'vendor',
        'bestseller_score', 'created_at', 'updated_at', 'raw_json'
    ];

    public int $id;
    public string $title;  // Changed from name to title
    public string $handle;
    public ?string $body_html;
    public float $price;
    public ?float $compare_at_price;
    public ?string $product_type;  // Changed from category to product_type
    public bool $in_stock;
    public ?float $rating;
    public ?int $review_count;
    public ?string $tags;
    public ?string $vendor;
    public ?float $bestseller_score;
    public ?string $created_at;
    public ?string $updated_at;
    public ?string $raw_json;
    public ?int $quantity;

    // Arrays for related data, populated by the API controller
    public array $images = [];
    public array $variants = [];
    public array $options = [];

    /**
     * Convert integer IDs to strings for API compatibility
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => (string)$this->id,
            'title' => $this->title,
            'handle' => $this->handle,
            'body_html' => $this->body_html,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'product_type' => $this->product_type,
            'in_stock' => $this->in_stock,
            'rating' => $this->rating,
            'review_count' => $this->review_count,
            'tags' => $this->tags,
            'vendor' => $this->vendor,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'quantity' => $this->quantity,
            'images' => array_map(function($image) {
                return $image instanceof Image ? $image->toArray() : $image;
            }, $this->images),
            'variants' => $this->variants,
            'options' => $this->options,
            'raw_json' => $this->raw_json
        ];
    }
}
