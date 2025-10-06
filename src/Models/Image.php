<?php
// src/Models/Image.php

namespace App\Models;

class Image
{
    public int $id;
    public int $product_id;
    public int $position;
    public string $src;
    public ?int $width = null;
    public ?int $height = null;
    public ?string $alt = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?array $variant_ids = [];

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
            'position' => $this->position,
            'src' => $this->src,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'variant_ids' => $this->variant_ids ? array_map('strval', $this->variant_ids) : []
        ];
    }
}
