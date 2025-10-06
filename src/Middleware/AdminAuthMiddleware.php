<?php

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseFactoryInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(AuthService $authService, ResponseFactoryInterface $responseFactory)
    {
        $this->authService = $authService;
        $this->responseFactory = $responseFactory;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Check if user is authenticated
        $user = $this->authService->getUserFromSession();

        if (!$user) {
            // Not authenticated, redirect to login
            $response = $this->responseFactory->createResponse(302);
            return $response->withHeader('Location', '/cosmos/admin/login');
        }

        // Add user to request attributes for use in controllers
        $request = $request->withAttribute('admin_user', $user);

        return $handler->handle($request);
    }
}

