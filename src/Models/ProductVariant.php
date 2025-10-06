<?php
// src/Models/ProductVariant.php

namespace App\Models;

class ProductVariant
{
    public int $id;
    public int $product_id;
    public string $title;
    public ?string $option1;
    public ?string $option2;
    public ?string $option3;
    public ?string $sku;
    public bool $requires_shipping;
    public bool $taxable;
    public ?string $featured_image;
    public bool $available;
    public float $price;
    public int $grams;
    public ?float $compare_at_price;
    public int $position;
    public ?string $created_at;
    public ?string $updated_at;

    /**
     * Convert to array with string IDs for API compatibility
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => (string)$this->id,
            'product_id' => (string)$this->product_id,
            'title' => $this->title,
            'option1' => $this->option1,
            'option2' => $this->option2,
            'option3' => $this->option3,
            'sku' => $this->sku,
            'requires_shipping' => $this->requires_shipping,
            'taxable' => $this->taxable,
            'featured_image' => $this->featured_image,
            'available' => $this->available,
            'price' => $this->price,
            'grams' => $this->grams,
            'compare_at_price' => $this->compare_at_price,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
