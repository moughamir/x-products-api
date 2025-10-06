<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PlaceholderController
{
    private AuthService $authService;
    private Twig $view;

    public function __construct(AuthService $authService, Twig $view)
    {
        $this->authService = $authService;
        $this->view = $view;
    }

    /**
     * Generic placeholder page
     */
    public function placeholder(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $path = $request->getUri()->getPath();
        
        // Extract page name from path
        $pageName = ucfirst(str_replace(['/cosmos/admin/', '-'], ['', ' '], $path));

        return $this->view->render($response, 'admin/placeholder.html.twig', [
            'user' => $user,
            'page_name' => $pageName,
            'path' => $path,
        ]);
    }
}

