<?php
// src/Services/ImageService.php

namespace App\Services;

use App\Models\Image;
use PDO;

class ImageService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getProductImages(int $productId): array
    {
        $sql = "SELECT id, product_id, position, alt, src, width, height, created_at, updated_at, variant_ids
                FROM product_images
                WHERE product_id = :product_id
                ORDER BY position ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert rows to Image objects and decode variant_ids
        $images = [];
        foreach ($rows as $row) {
            $image = new Image();
            $image->id = (int)$row['id'];
            $image->product_id = (int)$row['product_id'];
            $image->position = (int)$row['position'];
            $image->alt = $row['alt'];
            $image->src = $row['src'];
            $image->width = $row['width'] !== null ? (int)$row['width'] : null;
            $image->height = $row['height'] !== null ? (int)$row['height'] : null;
            $image->created_at = $row['created_at'];
            $image->updated_at = $row['updated_at'];

            // Decode variant_ids JSON
            if ($row['variant_ids'] !== null && $row['variant_ids'] !== '') {
                $image->variant_ids = json_decode($row['variant_ids'], true) ?? [];
            } else {
                $image->variant_ids = [];
            }

            $images[] = $image;
        }

        return $images;
    }

    public function getImagesForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $idString = implode(',', $productIds);

        // This is the N+1 optimization: one query to get all images for all products
        $sqlImages = "SELECT product_id, id, position, alt, src, width, height, created_at, updated_at, variant_ids
                      FROM product_images
                      WHERE product_id IN ({$idString})
                      ORDER BY product_id, position ASC";

        $stmtImages = $this->db->query($sqlImages);
        $rows = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

        // Convert rows to Image objects and decode variant_ids
        $images = [];
        foreach ($rows as $row) {
            $image = new Image();
            $image->id = (int)$row['id'];
            $image->product_id = (int)$row['product_id'];
            $image->position = (int)$row['position'];
            $image->alt = $row['alt'];
            $image->src = $row['src'];
            $image->width = $row['width'] !== null ? (int)$row['width'] : null;
            $image->height = $row['height'] !== null ? (int)$row['height'] : null;
            $image->created_at = $row['created_at'];
            $image->updated_at = $row['updated_at'];

            // Decode variant_ids JSON
            if ($row['variant_ids'] !== null && $row['variant_ids'] !== '') {
                $image->variant_ids = json_decode($row['variant_ids'], true) ?? [];
            } else {
                $image->variant_ids = [];
            }

            $images[] = $image;
        }

        return $images;
    }
}
