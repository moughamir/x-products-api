<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Models\AdminUser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class UserController
{
    private AuthService $authService;
    private Twig $view;
    private PDO $adminDb;
    private AdminUser $userModel;

    public function __construct(AuthService $authService, Twig $view, PDO $adminDb)
    {
        $this->authService = $authService;
        $this->view = $view;
        $this->adminDb = $adminDb;
        $this->userModel = new AdminUser($adminDb);
    }

    /**
     * List all users with pagination and search
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $params = $request->getQueryParams();

        // Pagination
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Search and filters
        $search = $params['search'] ?? '';
        $roleFilter = $params['role'] ?? '';
        $statusFilter = $params['status'] ?? '';

        // Build query
        $sql = "
            SELECT u.*, r.name as role_name, r.permissions
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role_id = r.id
            WHERE 1=1
        ";
        $countSql = "SELECT COUNT(*) FROM admin_users u WHERE 1=1";
        $bindings = [];

        if (!empty($search)) {
            $searchCondition = " AND (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search)";
            $sql .= $searchCondition;
            $countSql .= $searchCondition;
            $bindings['search'] = "%{$search}%";
        }

        if (!empty($roleFilter)) {
            $roleCondition = " AND u.role_id = :role_id";
            $sql .= $roleCondition;
            $countSql .= $roleCondition;
            $bindings['role_id'] = $roleFilter;
        }

        if ($statusFilter !== '') {
            $statusCondition = " AND u.is_active = :is_active";
            $sql .= $statusCondition;
            $countSql .= $statusCondition;
            $bindings['is_active'] = (int)$statusFilter;
        }

        $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

        // Get total count
        $stmtCount = $this->adminDb->prepare($countSql);
        foreach ($bindings as $key => $value) {
            $stmtCount->bindValue(":{$key}", $value);
        }
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();

        // Get users
        $stmt = $this->adminDb->prepare($sql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all roles for filter dropdown
        $roles = $this->adminDb->query("SELECT * FROM admin_roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        // Calculate pagination
        $totalPages = ceil($total / $limit);

        return $this->view->render($response, 'admin/users/index.html.twig', [
            'user' => $user,
            'users' => $users,
            'roles' => $roles,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'limit' => $limit,
        ]);
    }

    /**
     * Show create user form
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $roles = $this->adminDb->query("SELECT * FROM admin_roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->view->render($response, 'admin/users/create.html.twig', [
            'user' => $user,
            'roles' => $roles,
            'csrf_token' => $this->authService->generateCsrfToken(),
        ]);
    }

    /**
     * Store new user
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $this->authService->getUserFromSession();

        // Validate CSRF token
        if (!$this->authService->validateCsrfToken($data['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Invalid CSRF token';
            return $response->withHeader('Location', '/cosmos/admin/users/new')->withStatus(302);
        }

        // Validate input
        $errors = $this->validateUser($data);
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $data;
            return $response->withHeader('Location', '/cosmos/admin/users/new')->withStatus(302);
        }

        try {
            // Create user
            $userId = $this->userModel->create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                'full_name' => $data['full_name'],
                'role_id' => (int)$data['role_id'],
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            // Log activity
            $this->authService->logActivity(
                $currentUser['id'],
                'create',
                'user',
                "Created user: {$data['username']} (ID: {$userId})"
            );

            $_SESSION['success'] = "User '{$data['username']}' created successfully";
            return $response->withHeader('Location', '/cosmos/admin/users')->withStatus(302);

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create user: ' . $e->getMessage();
            $_SESSION['old'] = $data;
            return $response->withHeader('Location', '/cosmos/admin/users/new')->withStatus(302);
        }
    }

    /**
     * Show edit user form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->authService->getUserFromSession();
        $userId = (int)$args['id'];

        $editUser = $this->userModel->findById($userId);
        if (!$editUser) {
            $_SESSION['error'] = 'User not found';
            return $response->withHeader('Location', '/cosmos/admin/users')->withStatus(302);
        }

        $roles = $this->adminDb->query("SELECT * FROM admin_roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->view->render($response, 'admin/users/edit.html.twig', [
            'user' => $currentUser,
            'editUser' => $editUser,
            'roles' => $roles,
            'csrf_token' => $this->authService->generateCsrfToken(),
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $this->authService->getUserFromSession();
        $userId = (int)$args['id'];

        // Validate CSRF token
        if (!$this->authService->validateCsrfToken($data['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Invalid CSRF token';
            return $response->withHeader('Location', "/cosmos/admin/users/{$userId}/edit")->withStatus(302);
        }

        // Validate input
        $errors = $this->validateUser($data, $userId);
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $data;
            return $response->withHeader('Location', "/cosmos/admin/users/{$userId}/edit")->withStatus(302);
        }

        try {
            $updateData = [
                'email' => $data['email'],
                'full_name' => $data['full_name'],
                'role_id' => (int)$data['role_id'],
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ];

            // Only update password if provided
            if (!empty($data['password'])) {
                $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $this->userModel->update($userId, $updateData);

            // Log activity
            $this->authService->logActivity(
                $currentUser['id'],
                'update',
                'user',
                "Updated user: {$data['username']} (ID: {$userId})"
            );

            $_SESSION['success'] = "User updated successfully";
            return $response->withHeader('Location', '/cosmos/admin/users')->withStatus(302);

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update user: ' . $e->getMessage();
            $_SESSION['old'] = $data;
            return $response->withHeader('Location', "/cosmos/admin/users/{$userId}/edit")->withStatus(302);
        }
    }

    /**
     * Delete user
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->authService->getUserFromSession();
        $userId = (int)$args['id'];

        // Prevent self-deletion
        if ($userId === $currentUser['id']) {
            $_SESSION['error'] = 'You cannot delete your own account';
            return $response->withHeader('Location', '/cosmos/admin/users')->withStatus(302);
        }

        $deleteUser = $this->userModel->findById($userId);
        if (!$deleteUser) {
            $_SESSION['error'] = 'User not found';
            return $response->withHeader('Location', '/cosmos/admin/users')->withStatus(302);
        }

        try {
            $this->userModel->delete($userId);

            // Log activity
            $this->authService->logActivity(
                $currentUser['id'],
                'delete',
                'user',
                "Deleted user: {$deleteUser['username']} (ID: {$userId})"
            );

            $_SESSION['success'] = "User '{$deleteUser['username']}' deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete user: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/users')->withStatus(302);
    }

    /**
     * Validate user data
     */
    private function validateUser(array $data, ?int $userId = null): array
    {
        $errors = [];

        // Username (only for new users)
        if ($userId === null) {
            if (empty($data['username'])) {
                $errors['username'] = 'Username is required';
            } elseif (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
                $errors['username'] = 'Username must be between 3 and 50 characters';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $errors['username'] = 'Username can only contain letters, numbers, and underscores';
            } elseif ($this->userModel->findByUsername($data['username'])) {
                $errors['username'] = 'Username already exists';
            }
        }

        // Email
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } else {
            $existingUser = $this->userModel->findByEmail($data['email']);
            if ($existingUser && (!$userId || $existingUser['id'] != $userId)) {
                $errors['email'] = 'Email already exists';
            }
        }

        // Password (required for new users, optional for updates)
        if ($userId === null && empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        // Full name
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required';
        } elseif (strlen($data['full_name']) < 2 || strlen($data['full_name']) > 100) {
            $errors['full_name'] = 'Full name must be between 2 and 100 characters';
        }

        // Role
        if (empty($data['role_id'])) {
            $errors['role_id'] = 'Role is required';
        }

        return $errors;
    }
}

