<?php

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AdminAuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Check if user is authenticated
        $user = $this->authService->getUserFromSession();

        if (!$user) {
            // Not authenticated, redirect to login
            $response = new SlimResponse();
            return $response
                ->withHeader('Location', '/cosmos/admin/login')
                ->withStatus(302);
        }

        // Add user to request attributes for use in controllers
        $request = $request->withAttribute('admin_user', $user);

        return $handler->handle($request);
    }
}

