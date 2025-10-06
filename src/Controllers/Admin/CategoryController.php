<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\CategoryService;
use App\Models\Category;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class CategoryController
{
    private AuthService $authService;
    private CategoryService $categoryService;
    private PDO $db;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        CategoryService $categoryService,
        PDO $db,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->categoryService = $categoryService;
        $this->db = $db;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $categories = $this->categoryService->getTreeWithCounts();

        return $this->view->render($response, 'admin/categories/index.html.twig', [
            'user' => $user,
            'categories' => $categories,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $categories = $this->categoryService->getFlattenedTree();

        return $this->view->render($response, 'admin/categories/create.html.twig', [
            'user' => $user,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Name is required';
            return $response->withHeader('Location', '/cosmos/admin/categories/new')->withStatus(302);
        }

        try {
            $category = new Category();
            $category->name = $data['name'];
            $category->slug = $this->categoryService->generateUniqueSlug($data['name']);
            $category->description = $data['description'] ?? null;
            $category->parent_id = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            $category->position = 0;
            $category->save($this->db);

            $this->authService->logActivity($user['id'], 'create', 'category', "Created category: {$category->name}");
            $_SESSION['success'] = "Category '{$category->name}' created successfully";
            return $response->withHeader('Location', '/cosmos/admin/categories')->withStatus(302);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create category: ' . $e->getMessage();
            return $response->withHeader('Location', '/cosmos/admin/categories/new')->withStatus(302);
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $categoryId = (int)$args['id'];

        $category = Category::find($this->db, $categoryId);
        if (!$category) {
            $_SESSION['error'] = 'Category not found';
            return $response->withHeader('Location', '/cosmos/admin/categories')->withStatus(302);
        }

        $categories = $this->categoryService->getFlattenedTree();

        return $this->view->render($response, 'admin/categories/edit.html.twig', [
            'user' => $user,
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $categoryId = (int)$args['id'];
        $data = $request->getParsedBody();

        $category = Category::find($this->db, $categoryId);
        if (!$category) {
            $_SESSION['error'] = 'Category not found';
            return $response->withHeader('Location', '/cosmos/admin/categories')->withStatus(302);
        }

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Name is required';
            return $response->withHeader('Location', "/cosmos/admin/categories/{$categoryId}/edit")->withStatus(302);
        }

        try {
            $category->name = $data['name'];
            $category->slug = $data['slug'];
            $category->description = $data['description'] ?? null;
            $category->parent_id = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            $category->save($this->db);

            $this->authService->logActivity($user['id'], 'update', 'category', "Updated category: {$category->name}");
            $_SESSION['success'] = "Category '{$category->name}' updated successfully";
            return $response->withHeader('Location', '/cosmos/admin/categories')->withStatus(302);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update category: ' . $e->getMessage();
            return $response->withHeader('Location', "/cosmos/admin/categories/{$categoryId}/edit")->withStatus(302);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $categoryId = (int)$args['id'];

        $category = Category::find($this->db, $categoryId);
        if (!$category) {
            $_SESSION['error'] = 'Category not found';
            return $response->withHeader('Location', '/cosmos/admin/categories')->withStatus(302);
        }

        try {
            $name = $category->name;
            $this->categoryService->deleteCategory($category, false);
            $this->authService->logActivity($user['id'], 'delete', 'category', "Deleted category: {$name}");
            $_SESSION['success'] = "Category deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete category: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/categories')->withStatus(302);
    }
}

