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
    public ?string $body_html = null;
    public float $price;
    public ?float $compare_at_price = null;
    public ?string $product_type = null;  // Changed from category to product_type
    public bool $in_stock;
    public ?float $rating = null;
    public ?int $review_count = null;
    public ?string $tags = null;
    public ?string $vendor = null;
    public ?float $bestseller_score = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $raw_json = null;
    public ?int $quantity = null;
    public ?string $source_domain = null;
    public ?string $category = null;
    public ?string $variants_json = null;
    public ?string $options_json = null;

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
        // Convert tags from comma-separated string to array
        $tagsArray = [];
        if ($this->tags !== null && $this->tags !== '') {
            $tagsArray = array_map('trim', explode(',', $this->tags));
        }

        return [
            'id' => (string)$this->id,
            'title' => $this->title,
            'handle' => $this->handle,
            'body_html' => $this->body_html,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'images' => array_map(function($image) {
                return $image instanceof Image ? $image->toArray() : $image;
            }, $this->images),
            'product_type' => $this->product_type,
            'tags' => $tagsArray,
            'vendor' => $this->vendor,
            'variants' => $this->variants,
            'options' => $this->options,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
