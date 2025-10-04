<?php
// src/App.php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Middleware\ApiKeyMiddleware;

use App\Services\ImageProxy;
use App\Services\ProductService;
use App\Services\ImageService;
use PDO;
use Slim\Routing\RouteCollectorProxy;
use DI\ContainerBuilder;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

class App
{
    public static function bootstrap(): \Slim\App
    {
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            // --- Twig View Configuration (for Swagger UI) ---
            Twig::class => function() {
                // Point to the directory where your template files are located
                return Twig::create(__DIR__ . '/../templates', ['cache' => false]);
            },

            PDO::class => function() use ($dbConfig) {
                $dbFile = $dbConfig['db_file'];
                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                $pdo = new PDO("sqlite:" . $dbFile);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;
            },

            ProductService::class => fn($container) => new ProductService($container->get(PDO::class)),
            ImageService::class => fn($container) => new ImageService($container->get(PDO::class)),

            ApiController::class => fn($container) => new ApiController(
                $container->get(ProductService::class),
                $container->get(ImageService::class),
                $container->get(Twig::class) // Inject Twig for documentation controller methods
            ),

            ImageProxy::class => fn() => new ImageProxy($config),

            'config' => $config,
            'source_dir' => __DIR__ . '/Controllers' // Source directory for Swagger annotation scanning
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->setBasePath('/cosmos');

        // Middleware
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);
        $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

        // --- PUBLIC DOCUMENTATION ROUTES ---
        // 1. Interactive Swagger UI
        $app->get('/swagger-ui', [ApiController::class, 'swaggerUi']);
        // 2. Raw OpenAPI Specification JSON file (required by the UI)
        $app->get('/openapi.json', [ApiController::class, 'swaggerJson']);

        // --- API Routes (Authenticated) ---
        $app->group('/products', function (RouteCollectorProxy $group) {
            $group->get('[/]', [ApiController::class, 'getProducts']);
            $group->get('/search', [ApiController::class, 'searchProducts']);
            $group->get('/{key}', [ApiController::class, 'getProductOrHandle']);
        })->add(new ApiKeyMiddleware($config['api_key']));

        $app->group('/collections', function (RouteCollectorProxy $group) {
            $group->get('/{handle}', [ApiController::class, 'getCollectionProducts']);
        })->add(new ApiKeyMiddleware($config['api_key']));

        // --- Image Proxy Route (Unauthenticated) ---
        $app->get('/cdn/{path:.*}', [ImageProxy::class, 'proxy']);

        return $app;
    }
}
