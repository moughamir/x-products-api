<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Models\Setting;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class SettingsController
{
    private AuthService $authService;
    private PDO $adminDb;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        PDO $adminDb,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->adminDb = $adminDb;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        // Initialize defaults if needed
        Setting::initializeDefaults($this->adminDb);

        // Get all settings
        $settings = Setting::all($this->adminDb);

        // Group settings by category
        $grouped = [
            'general' => [],
            'email' => [],
            'display' => [],
        ];

        foreach ($settings as $key => $value) {
            if (str_starts_with($key, 'smtp_') || str_starts_with($key, 'from_')) {
                $grouped['email'][$key] = $value;
            } elseif (in_array($key, ['items_per_page', 'max_image_size', 'allowed_image_types'])) {
                $grouped['display'][$key] = $value;
            } else {
                $grouped['general'][$key] = $value;
            }
        }

        return $this->view->render($response, 'admin/settings/index.html.twig', [
            'user' => $user,
            'settings' => $grouped,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();

        try {
            // Remove CSRF token and submit button from data
            unset($data['csrf_token'], $data['submit']);

            // Update settings
            $count = Setting::bulkUpdate($this->adminDb, $data);

            $this->authService->logActivity($user['id'], 'update', 'settings', "Updated {$count} settings");
            $_SESSION['success'] = "Settings updated successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update settings: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/settings')->withStatus(302);
    }
}

