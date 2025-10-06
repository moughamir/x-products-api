<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\CollectionService;
use App\Models\Collection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class CollectionController
{
    private AuthService $authService;
    private CollectionService $collectionService;
    private PDO $db;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        CollectionService $collectionService,
        PDO $db,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->collectionService = $collectionService;
        $this->db = $db;
        $this->view = $view;
    }

    /**
     * List all collections
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = 50;

        $filters = [
            'search' => $params['search'] ?? '',
            'is_smart' => isset($params['is_smart']) ? (int)$params['is_smart'] : null,
            'is_featured' => isset($params['is_featured']) ? (int)$params['is_featured'] : null,
        ];

        $collections = Collection::all($this->db, $page, $limit, $filters);
        $totalCollections = Collection::count($this->db, $filters);
        $totalPages = ceil($totalCollections / $limit);

        // Add product counts
        foreach ($collections as &$collection) {
            $collection->product_count = $collection->getProductCount($this->db);
        }

        return $this->view->render($response, 'admin/collections/index.html.twig', [
            'user' => $user,
            'collections' => $collections,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_collections' => $totalCollections,
            'filters' => $filters,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
    }

    /**
     * Show create collection form
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        // Get available options for smart collections
        $productTypes = $this->collectionService->getAvailableProductTypes();
        $vendors = $this->collectionService->getAvailableVendors();

        return $this->view->render($response, 'admin/collections/create.html.twig', [
            'user' => $user,
            'product_types' => $productTypes,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Store new collection
     */
    public function store(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();

        if (empty($data['title'])) {
            $_SESSION['error'] = 'Title is required';
            return $response->withHeader('Location', '/cosmos/admin/collections/new')->withStatus(302);
        }

        try {
            $collection = new Collection();
            $collection->title = $data['title'];
            $collection->handle = $data['handle'] ?? strtolower(preg_replace('/[^a-z0-9]+/', '-', $data['title']));
            $collection->description = $data['description'] ?? null;
            $collection->is_smart = isset($data['is_smart']) ? 1 : 0;
            $collection->is_featured = isset($data['is_featured']) ? 1 : 0;
            $collection->sort_order = $data['sort_order'] ?? 'manual';

            // Handle smart collection rules
            if ($collection->is_smart && !empty($data['rule_type'])) {
                $rules = [
                    'type' => $data['rule_type'],
                    'value' => $data['rule_value'] ?? null,
                ];
                
                if ($data['rule_type'] === 'price_range') {
                    $rules['min_price'] = $data['min_price'] ?? null;
                    $rules['max_price'] = $data['max_price'] ?? null;
                }
                
                $collection->rules = json_encode($rules);
            }

            $collection->save($this->db);

            // Sync smart collection products
            if ($collection->is_smart) {
                $this->collectionService->syncSmartCollection($collection);
            }

            $this->authService->logActivity(
                $user['id'],
                'create',
                'collection',
                "Created collection: {$collection->title}"
            );

            $_SESSION['success'] = "Collection '{$collection->title}' created successfully";
            return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create collection: ' . $e->getMessage();
            return $response->withHeader('Location', '/cosmos/admin/collections/new')->withStatus(302);
        }
    }

    /**
     * Show edit collection form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $collectionId = (int)$args['id'];

        $collection = Collection::find($this->db, $collectionId);
        if (!$collection) {
            $_SESSION['error'] = 'Collection not found';
            return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);
        }

        $productTypes = $this->collectionService->getAvailableProductTypes();
        $vendors = $this->collectionService->getAvailableVendors();

        return $this->view->render($response, 'admin/collections/edit.html.twig', [
            'user' => $user,
            'collection' => $collection,
            'product_types' => $productTypes,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Update collection
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $collectionId = (int)$args['id'];
        $data = $request->getParsedBody();

        $collection = Collection::find($this->db, $collectionId);
        if (!$collection) {
            $_SESSION['error'] = 'Collection not found';
            return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);
        }

        if (empty($data['title'])) {
            $_SESSION['error'] = 'Title is required';
            return $response->withHeader('Location', "/cosmos/admin/collections/{$collectionId}/edit")->withStatus(302);
        }

        try {
            $collection->title = $data['title'];
            $collection->handle = $data['handle'];
            $collection->description = $data['description'] ?? null;
            $collection->is_smart = isset($data['is_smart']) ? 1 : 0;
            $collection->is_featured = isset($data['is_featured']) ? 1 : 0;
            $collection->sort_order = $data['sort_order'] ?? 'manual';

            if ($collection->is_smart && !empty($data['rule_type'])) {
                $rules = [
                    'type' => $data['rule_type'],
                    'value' => $data['rule_value'] ?? null,
                ];
                
                if ($data['rule_type'] === 'price_range') {
                    $rules['min_price'] = $data['min_price'] ?? null;
                    $rules['max_price'] = $data['max_price'] ?? null;
                }
                
                $collection->rules = json_encode($rules);
            }

            $collection->save($this->db);

            if ($collection->is_smart) {
                $this->collectionService->syncSmartCollection($collection);
            }

            $this->authService->logActivity(
                $user['id'],
                'update',
                'collection',
                "Updated collection: {$collection->title}"
            );

            $_SESSION['success'] = "Collection '{$collection->title}' updated successfully";
            return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update collection: ' . $e->getMessage();
            return $response->withHeader('Location', "/cosmos/admin/collections/{$collectionId}/edit")->withStatus(302);
        }
    }

    /**
     * Delete collection
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $collectionId = (int)$args['id'];

        $collection = Collection::find($this->db, $collectionId);
        if (!$collection) {
            $_SESSION['error'] = 'Collection not found';
            return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);
        }

        try {
            $title = $collection->title;
            $collection->delete($this->db);

            $this->authService->logActivity(
                $user['id'],
                'delete',
                'collection',
                "Deleted collection: {$title}"
            );

            $_SESSION['success'] = "Collection deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete collection: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);
    }

    /**
     * Sync smart collection
     */
    public function sync(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $collectionId = (int)$args['id'];

        $collection = Collection::find($this->db, $collectionId);
        if (!$collection || !$collection->is_smart) {
            $_SESSION['error'] = 'Collection not found or not a smart collection';
            return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);
        }

        try {
            $count = $this->collectionService->syncSmartCollection($collection);

            $this->authService->logActivity(
                $user['id'],
                'update',
                'collection',
                "Synced smart collection: {$collection->title} ({$count} products)"
            );

            $_SESSION['success'] = "Synced {$count} products to collection '{$collection->title}'";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to sync collection: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/collections')->withStatus(302);
    }
}

