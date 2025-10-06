<?php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PlaceholderController;
use App\Controllers\Admin\UserController;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\AdminAuthMiddleware;
use App\Services\ImageProxy;
use App\Services\ProductService;
use App\Services\ImageService;
use App\Services\AuthService;
use Slim\Views\Twig;
use PDO;
use Slim\Routing\RouteCollectorProxy;
use DI\ContainerBuilder;
use Twig\Loader\FilesystemLoader;
use function DI\get;

class App
{
    public static function bootstrap(): \Slim\App
    {
        // Start session for admin
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Path fix: Adjusted require to reflect the nested config files
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';
        $adminConfig = require __DIR__ . '/../config/admin.php';

        // Merge configuration for full access in DI definitions
        $fullConfig = array_merge($config, $dbConfig, $adminConfig);

        // Create DI Container
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            'source_dir' => __DIR__ . '/Controllers',

            // Products Database (main PDO)
            PDO::class => function() use ($dbConfig) {
                // Path fix: Use the DB file path from the dedicated config file
                $dbFile = $dbConfig['db_file'];

                // Ensure the database directory exists
                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                $pdo = new PDO("sqlite:" . $dbFile);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;
            },

            // Admin Database (separate PDO instance)
            'AdminPDO' => function() use ($adminConfig) {
                $dbFile = $adminConfig['admin_db_file'];

                // Ensure the database directory exists
                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                $pdo = new PDO("sqlite:" . $dbFile);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;
            },

            // ADDED: ImageProxy dependency
            ImageProxy::class => function() use ($fullConfig) {
                return new ImageProxy($fullConfig);
            },

            // UPDATED: ProductService now requires ImageProxy
            ProductService::class => function(PDO $db, ImageProxy $imageProxy) {
                return new ProductService($db, $imageProxy);
            },

            ImageService::class => function(PDO $db) {
                return new ImageService($db);
            },

            Twig::class => function() {
                $loader = new FilesystemLoader(__DIR__ . '/../templates');
                // Disable cache for dev
                return new Twig($loader, ['cache' => false]);
            },

            // Final ApiController Definition
            ApiController::class => function(ProductService $productService, ImageService $imageService, Twig $view) {
                // Pass 'source_dir' directly to the controller for OpenAPI scanning
                return new ApiController($productService, $imageService, $view, __DIR__ . '/Controllers');
            },

            // Admin Services
            AuthService::class => function($container) use ($fullConfig) {
                return new AuthService($container->get('AdminPDO'), $fullConfig);
            },

            // Admin Middleware
            AdminAuthMiddleware::class => function(AuthService $authService) {
                return new AdminAuthMiddleware($authService);
            },

            // Admin Controllers
            AuthController::class => function(AuthService $authService, Twig $view) {
                return new AuthController($authService, $view);
            },

            DashboardController::class => \DI\autowire()
                ->constructorParameter('productsDb', get(PDO::class))
                ->constructorParameter('adminDb', get('AdminPDO')),

            PlaceholderController::class => function(AuthService $authService, Twig $view) {
                return new PlaceholderController($authService, $view);
            },

            UserController::class => \DI\autowire()
                ->constructorParameter('adminDb', get('AdminPDO')),
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);

        $app = AppFactory::create();

        $app->setBasePath('/cosmos');

        // Middleware
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        // API Routes (Grouped for Middleware)
        $apiKeyMiddleware = new ApiKeyMiddleware($config['api_key']);
        $app->get('/', function ($request, $response, $args) {
            // This is the route that handles the base URL: /cosmos/
            $response->getBody()->write("Welcome to the X-Products API!");
            return $response;
        });

        // Image Proxy Route (No API Key Required)
        $app->get('/cdn/{path:.*}', [ApiController::class, 'imageProxy']); // ADDED: Image Proxy Route

        $app->group('/products', function (RouteCollectorProxy $group) {
            $group->get('[/]', [ApiController::class, 'getProducts']);
            $group->get('/search', [ApiController::class, 'searchProducts']);
            $group->get('/{key}', [ApiController::class, 'getProductOrHandle']);
        })->add($apiKeyMiddleware);

        $app->group('/collections', function (RouteCollectorProxy $group) {
            $group->get('/{handle}', [ApiController::class, 'getCollectionProducts']);
        })->add($apiKeyMiddleware);

        // Documentation Routes (No API Key Required)
        $app->get('/swagger-ui', [ApiController::class, 'swaggerUi']);
        $app->get('/openapi.json', [ApiController::class, 'swaggerJson']);

        // Admin Routes
        $app->group('/admin', function (RouteCollectorProxy $group) {
            // Public routes (no auth required)
            $group->get('/login', [AuthController::class, 'showLogin']);
            $group->post('/login', [AuthController::class, 'login']);

            // Protected routes (require auth)
            $group->get('', [DashboardController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/', [DashboardController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/logout', [AuthController::class, 'logout'])->add(AdminAuthMiddleware::class);

            // Product Management Routes (Placeholder)
            $group->get('/products', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/products/new', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/collections', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/collections/new', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/categories', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/tags', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);

            // System Routes
            // User Management (Full CRUD)
            $group->get('/users', [UserController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/users/new', [UserController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/users', [UserController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->get('/users/{id}/edit', [UserController::class, 'edit'])->add(AdminAuthMiddleware::class);
            $group->post('/users/{id}', [UserController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/users/{id}/delete', [UserController::class, 'delete'])->add(AdminAuthMiddleware::class);

            // Activity Log & API Keys (Placeholder)
            $group->get('/activity', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/api-keys', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);

            // User Profile Routes (Placeholder)
            $group->get('/profile', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
            $group->get('/settings', [PlaceholderController::class, 'placeholder'])->add(AdminAuthMiddleware::class);
        });

        return $app;
    }
}
