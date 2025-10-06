<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\ApiKeyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ApiKeyController
{
    private AuthService $authService;
    private ApiKeyService $apiKeyService;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        ApiKeyService $apiKeyService,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->apiKeyService = $apiKeyService;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = 50;

        $apiKeys = $this->apiKeyService->getAllWithStatistics($page, $limit);

        return $this->view->render($response, 'admin/api-keys/index.html.twig', [
            'user' => $user,
            'api_keys' => $apiKeys,
            'page' => $page,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
            'new_key' => $_SESSION['new_key'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        return $this->view->render($response, 'admin/api-keys/create.html.twig', [
            'user' => $user,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Name is required';
            return $response->withHeader('Location', '/cosmos/admin/api-keys/new')->withStatus(302);
        }

        try {
            $options = [
                'rate_limit' => (int)($data['rate_limit'] ?? 60),
                'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            ];

            $result = $this->apiKeyService->generateApiKey($data['name'], $user['id'], $options);

            if ($result['success']) {
                $this->authService->logActivity($user['id'], 'create', 'api_key', "Created API key: {$data['name']}");
                $_SESSION['success'] = "API key created successfully";
                $_SESSION['new_key'] = $result['key']; // Show once
                return $response->withHeader('Location', '/cosmos/admin/api-keys')->withStatus(302);
            } else {
                $_SESSION['error'] = $result['error'];
                return $response->withHeader('Location', '/cosmos/admin/api-keys/new')->withStatus(302);
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create API key: ' . $e->getMessage();
            return $response->withHeader('Location', '/cosmos/admin/api-keys/new')->withStatus(302);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $keyId = (int)$args['id'];

        try {
            if ($this->apiKeyService->revokeKey($keyId)) {
                $this->authService->logActivity($user['id'], 'delete', 'api_key', "Revoked API key ID: {$keyId}");
                $_SESSION['success'] = "API key revoked successfully";
            } else {
                $_SESSION['error'] = "API key not found";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to revoke API key: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/api-keys')->withStatus(302);
    }
}

