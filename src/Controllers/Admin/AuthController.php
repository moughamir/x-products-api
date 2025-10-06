<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AuthController
{
    private AuthService $authService;
    private Twig $view;

    public function __construct(AuthService $authService, Twig $view)
    {
        $this->authService = $authService;
        $this->view = $view;
    }

    /**
     * Show login form
     */
    public function showLogin(Request $request, Response $response): Response
    {
        // If already authenticated, redirect to dashboard
        if ($this->authService->check()) {
            return $response
                ->withHeader('Location', '/cosmos/admin')
                ->withStatus(302);
        }

        // Generate CSRF token
        $csrfToken = $this->authService->generateCsrfToken();

        return $this->view->render($response, 'admin/auth/login.html.twig', [
            'csrf_token' => $csrfToken,
            'error' => $_SESSION['login_error'] ?? null,
        ]);
    }

    /**
     * Process login
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate CSRF token
        if (!isset($data['csrf_token']) || !$this->authService->validateCsrfToken($data['csrf_token'])) {
            $_SESSION['login_error'] = 'Invalid security token. Please try again.';
            return $response
                ->withHeader('Location', '/cosmos/admin/login')
                ->withStatus(302);
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Please enter both username and password.';
            return $response
                ->withHeader('Location', '/cosmos/admin/login')
                ->withStatus(302);
        }

        // Attempt authentication
        $user = $this->authService->attempt($username, $password);

        if (!$user) {
            $_SESSION['login_error'] = 'Invalid username or password.';
            return $response
                ->withHeader('Location', '/cosmos/admin/login')
                ->withStatus(302);
        }

        // Store session
        $_SESSION['admin_session_id'] = $user['session_id'];
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        
        // Clear login error
        unset($_SESSION['login_error']);

        // Log activity
        $this->authService->logActivity($user['id'], 'login', 'auth', 'User logged in');

        // Redirect to dashboard
        return $response
            ->withHeader('Location', '/cosmos/admin')
            ->withStatus(302);
    }

    /**
     * Logout
     */
    public function logout(Request $request, Response $response): Response
    {
        if (isset($_SESSION['admin_session_id'])) {
            // Log activity before destroying session
            $user = $this->authService->getUserFromSession();
            if ($user) {
                $this->authService->logActivity($user['user_id'], 'logout', 'auth', 'User logged out');
            }

            // Destroy session in database
            $this->authService->destroySession($_SESSION['admin_session_id']);
        }

        // Clear session
        $_SESSION = [];
        session_destroy();

        // Redirect to login
        return $response
            ->withHeader('Location', '/cosmos/admin/login')
            ->withStatus(302);
    }
}

