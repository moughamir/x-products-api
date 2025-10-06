<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ProfileController
{
    private AuthService $authService;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        return $this->view->render($response, 'admin/profile/index.html.twig', [
            'user' => $user,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        // Check if user is authenticated
        if (!$user) {
            $_SESSION['error'] = 'Session expired. Please login again.';
            return $response->withHeader('Location', '/cosmos/admin/login')->withStatus(302);
        }

        $data = $request->getParsedBody();

        // Validate
        $errors = [];
        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        }
        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            return $response->withHeader('Location', '/cosmos/admin/profile')->withStatus(302);
        }

        try {
            // Update profile
            $result = $this->authService->updateProfile($user['id'], [
                'username' => $data['username'],
                'email' => $data['email'],
                'full_name' => $data['full_name'] ?? null,
            ]);

            if ($result) {
                $this->authService->logActivity($user['id'], 'update', 'profile', "Updated profile");
                $_SESSION['success'] = "Profile updated successfully";
            } else {
                $_SESSION['error'] = "Failed to update profile";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update profile: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/profile')->withStatus(302);
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        // Check if user is authenticated
        if (!$user) {
            $_SESSION['error'] = 'Session expired. Please login again.';
            return $response->withHeader('Location', '/cosmos/admin/login')->withStatus(302);
        }

        $data = $request->getParsedBody();

        // Validate
        $errors = [];
        if (empty($data['current_password'])) {
            $errors[] = 'Current password is required';
        }
        if (empty($data['new_password'])) {
            $errors[] = 'New password is required';
        }
        if ($data['new_password'] !== $data['confirm_password']) {
            $errors[] = 'Passwords do not match';
        }
        if (strlen($data['new_password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(', ', $errors);
            return $response->withHeader('Location', '/cosmos/admin/profile')->withStatus(302);
        }

        try {
            $result = $this->authService->changePassword(
                $user['id'],
                $data['current_password'],
                $data['new_password']
            );

            if ($result) {
                $this->authService->logActivity($user['id'], 'update', 'password', "Changed password");
                $_SESSION['success'] = "Password changed successfully";
            } else {
                $_SESSION['error'] = "Current password is incorrect";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to change password: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/profile')->withStatus(302);
    }
}

