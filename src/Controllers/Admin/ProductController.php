<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\ProductManagementService;
use App\Services\TagService;
use App\Models\Collection;
use App\Models\Category;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class ProductController
{
    private AuthService $authService;
    private ProductManagementService $productService;
    private TagService $tagService;
    private PDO $productsDb;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        ProductManagementService $productService,
        TagService $tagService,
        PDO $productsDb,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->productService = $productService;
        $this->tagService = $tagService;
        $this->productsDb = $productsDb;
        $this->view = $view;
    }

    /**
     * List all products
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $params = $request->getQueryParams();

        // Pagination
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = 50;

        // Filters
        $filters = [
            'search' => $params['search'] ?? '',
            'category_id' => $params['category_id'] ?? null,
            'collection_id' => $params['collection_id'] ?? null,
            'tag_id' => $params['tag_id'] ?? null,
            'in_stock' => isset($params['in_stock']) ? (int)$params['in_stock'] : null,
            'product_type' => $params['product_type'] ?? null,
            'vendor' => $params['vendor'] ?? null,
            'order_by' => $params['order_by'] ?? 'id DESC',
        ];

        // Get products
        $products = $this->productService->getProductsForAdmin($page, $limit, $filters);
        $totalProducts = $this->productService->countProducts($filters);
        $totalPages = ceil($totalProducts / $limit);

        // Get filter options
        $collections = Collection::all($this->productsDb, 1, 100);
        $categories = Category::all($this->productsDb);
        
        // Get unique product types and vendors
        $stmt = $this->productsDb->query("SELECT DISTINCT product_type FROM products WHERE product_type IS NOT NULL ORDER BY product_type");
        $productTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->productsDb->query("SELECT DISTINCT vendor FROM products WHERE vendor IS NOT NULL ORDER BY vendor");
        $vendors = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $this->view->render($response, 'admin/products/index.html.twig', [
            'user' => $user,
            'products' => $products,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $totalProducts,
            'filters' => $filters,
            'collections' => $collections,
            'categories' => $categories,
            'product_types' => $productTypes,
            'vendors' => $vendors,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
    }

    /**
     * Show create product form
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        // Get collections and categories for form
        $collections = Collection::all($this->productsDb, 1, 100);
        $categories = Category::all($this->productsDb);

        return $this->view->render($response, 'admin/products/create.html.twig', [
            'user' => $user,
            'collections' => $collections,
            'categories' => $categories,
        ]);
    }

    /**
     * Store new product
     */
    public function store(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();

        // Validation
        $errors = [];
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }
        if (empty($data['price']) || !is_numeric($data['price'])) {
            $errors[] = 'Valid price is required';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            return $response->withHeader('Location', '/cosmos/admin/products/new')->withStatus(302);
        }

        try {
            // Generate handle if not provided
            $handle = !empty($data['handle']) 
                ? $data['handle'] 
                : $this->productService->generateUniqueHandle($data['title']);

            // Insert product
            $stmt = $this->productsDb->prepare("
                INSERT INTO products (
                    title, handle, body_html, vendor, product_type, price, compare_at_price,
                    in_stock, tags, created_at, updated_at
                ) VALUES (
                    :title, :handle, :body_html, :vendor, :product_type, :price, :compare_at_price,
                    :in_stock, :tags, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                'title' => $data['title'],
                'handle' => $handle,
                'body_html' => $data['body_html'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'product_type' => $data['product_type'] ?? null,
                'price' => (float)$data['price'],
                'compare_at_price' => !empty($data['compare_at_price']) ? (float)$data['compare_at_price'] : null,
                'in_stock' => isset($data['in_stock']) ? 1 : 0,
                'tags' => $data['tags'] ?? null,
            ]);

            $productId = (int)$this->productsDb->lastInsertId();

            // Assign to collections
            if (!empty($data['collections'])) {
                foreach ($data['collections'] as $collectionId) {
                    $stmt = $this->productsDb->prepare("
                        INSERT OR IGNORE INTO product_collections (product_id, collection_id)
                        VALUES (:product_id, :collection_id)
                    ");
                    $stmt->execute(['product_id' => $productId, 'collection_id' => $collectionId]);
                }
            }

            // Assign to categories
            if (!empty($data['categories'])) {
                foreach ($data['categories'] as $categoryId) {
                    $stmt = $this->productsDb->prepare("
                        INSERT OR IGNORE INTO product_categories (product_id, category_id)
                        VALUES (:product_id, :category_id)
                    ");
                    $stmt->execute(['product_id' => $productId, 'category_id' => $categoryId]);
                }
            }

            // Sync tags
            if (!empty($data['tags'])) {
                $this->tagService->syncProductTags($productId, $data['tags']);
            }

            // Log activity
            $this->authService->logActivity(
                $user['id'],
                'create',
                'product',
                "Created product: {$data['title']} (ID: {$productId})"
            );

            $_SESSION['success'] = "Product '{$data['title']}' created successfully";
            return $response->withHeader('Location', '/cosmos/admin/products')->withStatus(302);

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create product: ' . $e->getMessage();
            return $response->withHeader('Location', '/cosmos/admin/products/new')->withStatus(302);
        }
    }

    /**
     * Show edit product form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $productId = (int)$args['id'];

        // Get product
        $stmt = $this->productsDb->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $_SESSION['error'] = 'Product not found';
            return $response->withHeader('Location', '/cosmos/admin/products')->withStatus(302);
        }

        // Get assigned collections
        $stmt = $this->productsDb->prepare("SELECT collection_id FROM product_collections WHERE product_id = :id");
        $stmt->execute(['id' => $productId]);
        $assignedCollections = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get assigned categories
        $stmt = $this->productsDb->prepare("SELECT category_id FROM product_categories WHERE product_id = :id");
        $stmt->execute(['id' => $productId]);
        $assignedCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get all collections and categories
        $collections = Collection::all($this->productsDb, 1, 100);
        $categories = Category::all($this->productsDb);

        return $this->view->render($response, 'admin/products/edit.html.twig', [
            'user' => $user,
            'product' => $product,
            'collections' => $collections,
            'categories' => $categories,
            'assigned_collections' => $assignedCollections,
            'assigned_categories' => $assignedCategories,
        ]);
    }

    /**
     * Update product
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $productId = (int)$args['id'];
        $data = $request->getParsedBody();

        // Validation
        $errors = [];
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }
        if (empty($data['price']) || !is_numeric($data['price'])) {
            $errors[] = 'Valid price is required';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            return $response->withHeader('Location', "/cosmos/admin/products/{$productId}/edit")->withStatus(302);
        }

        try {
            // Update product
            $stmt = $this->productsDb->prepare("
                UPDATE products SET
                    title = :title,
                    handle = :handle,
                    body_html = :body_html,
                    vendor = :vendor,
                    product_type = :product_type,
                    price = :price,
                    compare_at_price = :compare_at_price,
                    in_stock = :in_stock,
                    tags = :tags,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $productId,
                'title' => $data['title'],
                'handle' => $data['handle'],
                'body_html' => $data['body_html'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'product_type' => $data['product_type'] ?? null,
                'price' => (float)$data['price'],
                'compare_at_price' => !empty($data['compare_at_price']) ? (float)$data['compare_at_price'] : null,
                'in_stock' => isset($data['in_stock']) ? 1 : 0,
                'tags' => $data['tags'] ?? null,
            ]);

            // Update collections
            $this->productsDb->exec("DELETE FROM product_collections WHERE product_id = {$productId}");
            if (!empty($data['collections'])) {
                foreach ($data['collections'] as $collectionId) {
                    $stmt = $this->productsDb->prepare("
                        INSERT INTO product_collections (product_id, collection_id)
                        VALUES (:product_id, :collection_id)
                    ");
                    $stmt->execute(['product_id' => $productId, 'collection_id' => $collectionId]);
                }
            }

            // Update categories
            $this->productsDb->exec("DELETE FROM product_categories WHERE product_id = {$productId}");
            if (!empty($data['categories'])) {
                foreach ($data['categories'] as $categoryId) {
                    $stmt = $this->productsDb->prepare("
                        INSERT INTO product_categories (product_id, category_id)
                        VALUES (:product_id, :category_id)
                    ");
                    $stmt->execute(['product_id' => $productId, 'category_id' => $categoryId]);
                }
            }

            // Sync tags
            if (isset($data['tags'])) {
                $this->tagService->syncProductTags($productId, $data['tags']);
            }

            // Log activity
            $this->authService->logActivity(
                $user['id'],
                'update',
                'product',
                "Updated product: {$data['title']} (ID: {$productId})"
            );

            $_SESSION['success'] = "Product '{$data['title']}' updated successfully";
            return $response->withHeader('Location', '/cosmos/admin/products')->withStatus(302);

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update product: ' . $e->getMessage();
            return $response->withHeader('Location', "/cosmos/admin/products/{$productId}/edit")->withStatus(302);
        }
    }

    /**
     * Delete product
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $productId = (int)$args['id'];

        try {
            // Get product title for logging
            $stmt = $this->productsDb->prepare("SELECT title FROM products WHERE id = :id");
            $stmt->execute(['id' => $productId]);
            $title = $stmt->fetchColumn();

            // Delete product
            $stmt = $this->productsDb->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute(['id' => $productId]);

            // Log activity
            $this->authService->logActivity(
                $user['id'],
                'delete',
                'product',
                "Deleted product: {$title} (ID: {$productId})"
            );

            $_SESSION['success'] = "Product deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete product: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/products')->withStatus(302);
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();
        $productIds = $data['product_ids'] ?? [];

        if (empty($productIds)) {
            $_SESSION['error'] = 'No products selected';
            return $response->withHeader('Location', '/cosmos/admin/products')->withStatus(302);
        }

        try {
            $count = $this->productService->bulkDelete($productIds);

            // Log activity
            $this->authService->logActivity(
                $user['id'],
                'delete',
                'product',
                "Bulk deleted {$count} products"
            );

            $_SESSION['success'] = "Successfully deleted {$count} products";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete products: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/products')->withStatus(302);
    }
}

