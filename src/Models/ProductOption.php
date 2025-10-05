<?php
// src/Models/ProductOption.php

namespace App\Models;

class ProductOption
{
    public int $id;
    public int $product_id;
    public string $name;
    public int $position;
    public array $values = [];

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
            'name' => $this->name,
            'position' => $this->position,
            'values' => $this->values
        ];
    }
}
