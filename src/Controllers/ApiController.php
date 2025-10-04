<?php
// src/Controllers/ApiController.php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\MsgPackResponse;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\ProductService;
use PDO;

class ApiController
{
    private ProductService $productService;
    private ImageService $imageService;
    private array $config;

    public function __construct(ProductService $productService, ImageService $imageService, array $config)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
        $this->config = $config;
    }

    // --- Helper to output response in JSON or MessagePack ---
    private function outputResponse(Response $response, array $data, string $format = 'json'): Response
    {
        if ($format === 'msgpack' && extension_loaded('msgpack')) {
            // HIGH OPTIMIZATION: Output binary MessagePack data
            try {
                return MsgPackResponse::withMsgPack($response, $data);
            } catch (\Exception $e) {
                // Fallback to JSON on MsgPack error (e.g., if extension is loaded but broken)
                error_log("MsgPack serialization failed: " . $e->getMessage());
            }
        }

        // Default to JSON
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Helper to modify image URLs for the internal proxy route.
     * @param array $images Array of App\Models\Image objects.
     */
    private function processImageUrls(array $images): array
    {
        $newBaseUrl = '/cosmos/cdn'; // Matches the base path and image proxy route
        $externalBaseUrl = $this->config['image_proxy']['base_url'];

        foreach ($images as $image) {
            // Replace the external base URL with the internal proxy URL
            $image->src = str_replace($externalBaseUrl, $newBaseUrl, $image->src);
        }
        return $images;
    }

    // --- ENDPOINTS ---

    /**
     * Optimized for single product lookup (ID or Handle).
     * Performs eager loading for all related data (images, variants, options).
     */
    public function getProductOrHandle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';

        $product = $this->productService->getProductOrHandle($key);

        if (!$product) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $productId = $product->id;

        // Eager load related data
        $images = $this->imageService->getProductImages($productId);
        // $variants = $this->productService->getProductVariants($productId); // Assuming ProductService has this
        // $options = $this->productService->getProductOptions($productId); // Assuming ProductService has this

        // Apply URL transformation
        $product->images = $this->processImageUrls($images);
        // $product->variants = $variants;
        // $product->options = $options;

        $data = ['product' => $product];
        return $this->outputResponse($response, $data, $format);
    }

    /**
     * Get a paginated list of all products.
     */
    public function getProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));

        $products = $this->productService->getProducts($page, $limit);

        // --- N+1 Optimization for Images in Bulk List ---
        if (!empty($products)) {
            $productIds = array_map(fn($p) => $p->id, $products);
            $allImages = $this->imageService->getImagesForProducts($productIds);

            $imagesByProductId = [];
            foreach ($allImages as $img) {
                // Apply URL transformation immediately
                $img = $this->processImageUrls([$img])[0];
                $imagesByProductId[$img->product_id][] = $img;
            }

            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }

        // --- Metadata ---
        $total = $this->productService->getTotalProducts();
        $data = [
            'products' => $products,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        return $this->outputResponse($response, $data, $format);
    }

    /**
     * Get products belonging to a collection handle.
     */
    public function getCollectionProducts(Request $request, Response $response, array $args): Response
    {
        $collectionHandle = $args['handle'];
        $params = $request->getQueryParams();
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));

        $products = $this->productService->getCollectionProducts($collectionHandle, $page, $limit, $fieldsParam);

        if (!empty($products)) {
            $productIds = array_map(fn($p) => $p->id, $products);
            $allImages = $this->imageService->getImagesForProducts($productIds);

            // Map images back to their respective products
            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $img = $this->processImageUrls([$img])[0];
                $imagesByProductId[$img->product_id][] = $img;
            }

            // Final Assembly
            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }

        // --- Handle Metadata ---
        $data = ['products' => $products];
        // Only calculate total/pagination if it's a paginated collection
        if ($collectionHandle !== 'featured') {
            $total = $this->productService->getTotalCollectionProducts($collectionHandle);
            $data['meta'] = [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];
        }

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * Search products using FTS.
     */
    public function searchProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';
        $query = $params['q'] ?? '';
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $fieldsParam = $params['fields'] ?? '*';

        if (empty($query)) {
            return $this->outputResponse($response, ['products' => [], 'meta' => ['message' => 'Query parameter "q" is required.']], $format);
        }

        $products = $this->productService->searchProducts($query, $limit, $fieldsParam);

        // N+1 Optimization for Images
        if (!empty($products)) {
            $productIds = array_map(fn($p) => $p->id, $products);
            $allImages = $this->imageService->getImagesForProducts($productIds);

            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $img = $this->processImageUrls([$img])[0];
                $imagesByProductId[$img->product_id][] = $img;
            }

            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }

        $data = ['products' => $products, 'meta' => ['query' => $query, 'limit' => $limit]];
        return $this->outputResponse($response, $data, $format);
    }
}
