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
use Psr\Http\Message\ResponseFactoryInterface;
use Nyholm\Psr7\Response;
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

            // PSR-7 Response Factory
            ResponseFactoryInterface::class => function() {
                return new class implements ResponseFactoryInterface {
                    public function createResponse(int $code = 200, string $reasonPhrase = ''): \Psr\Http\Message\ResponseInterface {
                        return new Response($code);
                    }
                };
            },

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
            AdminAuthMiddleware::class => \DI\autowire(),

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

            // Product Management Controllers
            \App\Controllers\Admin\ProductController::class => \DI\autowire()
                ->constructorParameter('productsDb', get(PDO::class)),

            \App\Controllers\Admin\CollectionController::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Controllers\Admin\CategoryController::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Controllers\Admin\TagController::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Controllers\Admin\ApiKeyController::class => \DI\autowire(),

            \App\Controllers\Admin\ActivityController::class => \DI\autowire()
                ->constructorParameter('adminDb', get('AdminPDO')),

            \App\Controllers\Admin\ProfileController::class => \DI\autowire(),

            \App\Controllers\Admin\SettingsController::class => \DI\autowire()
                ->constructorParameter('adminDb', get('AdminPDO')),

            // Services
            \App\Services\ProductManagementService::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Services\CollectionService::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Services\CategoryService::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Services\TagService::class => \DI\autowire()
                ->constructorParameter('db', get(PDO::class)),

            \App\Services\ApiKeyService::class => \DI\autowire()
                ->constructorParameter('db', get('AdminPDO')),
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

            // Product Management Routes
            $group->get('/products', [\App\Controllers\Admin\ProductController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/products/new', [\App\Controllers\Admin\ProductController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/products', [\App\Controllers\Admin\ProductController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->post('/products/bulk-delete', [\App\Controllers\Admin\ProductController::class, 'bulkDelete'])->add(AdminAuthMiddleware::class);
            $group->get('/products/{id}/edit', [\App\Controllers\Admin\ProductController::class, 'edit'])->add(AdminAuthMiddleware::class);
            $group->post('/products/{id}', [\App\Controllers\Admin\ProductController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/products/{id}/delete', [\App\Controllers\Admin\ProductController::class, 'delete'])->add(AdminAuthMiddleware::class);

            // Collections Management
            $group->get('/collections', [\App\Controllers\Admin\CollectionController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/collections/new', [\App\Controllers\Admin\CollectionController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/collections', [\App\Controllers\Admin\CollectionController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->get('/collections/{id}/edit', [\App\Controllers\Admin\CollectionController::class, 'edit'])->add(AdminAuthMiddleware::class);
            $group->post('/collections/{id}', [\App\Controllers\Admin\CollectionController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/collections/{id}/delete', [\App\Controllers\Admin\CollectionController::class, 'delete'])->add(AdminAuthMiddleware::class);
            $group->post('/collections/{id}/sync', [\App\Controllers\Admin\CollectionController::class, 'sync'])->add(AdminAuthMiddleware::class);

            // Categories Management
            $group->get('/categories', [\App\Controllers\Admin\CategoryController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/categories/new', [\App\Controllers\Admin\CategoryController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/categories', [\App\Controllers\Admin\CategoryController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->get('/categories/{id}/edit', [\App\Controllers\Admin\CategoryController::class, 'edit'])->add(AdminAuthMiddleware::class);
            $group->post('/categories/{id}', [\App\Controllers\Admin\CategoryController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/categories/{id}/delete', [\App\Controllers\Admin\CategoryController::class, 'delete'])->add(AdminAuthMiddleware::class);

            // Tags Management
            $group->get('/tags', [\App\Controllers\Admin\TagController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/tags/new', [\App\Controllers\Admin\TagController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/tags', [\App\Controllers\Admin\TagController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->post('/tags/bulk-delete', [\App\Controllers\Admin\TagController::class, 'bulkDelete'])->add(AdminAuthMiddleware::class);
            $group->post('/tags/cleanup-unused', [\App\Controllers\Admin\TagController::class, 'cleanupUnused'])->add(AdminAuthMiddleware::class);
            $group->get('/tags/{id}/edit', [\App\Controllers\Admin\TagController::class, 'edit'])->add(AdminAuthMiddleware::class);
            $group->post('/tags/{id}', [\App\Controllers\Admin\TagController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/tags/{id}/delete', [\App\Controllers\Admin\TagController::class, 'delete'])->add(AdminAuthMiddleware::class);

            // System Routes
            // User Management (Full CRUD)
            $group->get('/users', [UserController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/users/new', [UserController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/users', [UserController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->get('/users/{id}/edit', [UserController::class, 'edit'])->add(AdminAuthMiddleware::class);
            $group->post('/users/{id}', [UserController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/users/{id}/delete', [UserController::class, 'delete'])->add(AdminAuthMiddleware::class);

            // Activity Log
            $group->get('/activity', [\App\Controllers\Admin\ActivityController::class, 'index'])->add(AdminAuthMiddleware::class);

            // API Keys Management
            $group->get('/api-keys', [\App\Controllers\Admin\ApiKeyController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->get('/api-keys/new', [\App\Controllers\Admin\ApiKeyController::class, 'create'])->add(AdminAuthMiddleware::class);
            $group->post('/api-keys', [\App\Controllers\Admin\ApiKeyController::class, 'store'])->add(AdminAuthMiddleware::class);
            $group->post('/api-keys/{id}/delete', [\App\Controllers\Admin\ApiKeyController::class, 'delete'])->add(AdminAuthMiddleware::class);

            // User Profile Routes
            $group->get('/profile', [\App\Controllers\Admin\ProfileController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->post('/profile', [\App\Controllers\Admin\ProfileController::class, 'update'])->add(AdminAuthMiddleware::class);
            $group->post('/profile/change-password', [\App\Controllers\Admin\ProfileController::class, 'changePassword'])->add(AdminAuthMiddleware::class);

            // Settings
            $group->get('/settings', [\App\Controllers\Admin\SettingsController::class, 'index'])->add(AdminAuthMiddleware::class);
            $group->post('/settings', [\App\Controllers\Admin\SettingsController::class, 'update'])->add(AdminAuthMiddleware::class);
        });

        return $app;
    }
}
