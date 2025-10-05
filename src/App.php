<?php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Middleware\ApiKeyMiddleware;
use App\Services\ImageProxy;
use App\Services\ProductService;
use App\Services\ImageService;
use Slim\Views\Twig;
use PDO;
use Slim\Routing\RouteCollectorProxy;
use DI\ContainerBuilder;
use Twig\Loader\FilesystemLoader;

class App
{
    public static function bootstrap(): \Slim\App
    {
        // Path fix: Adjusted require to reflect the nested config files
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        // Merge configuration for full access in DI definitions
        $fullConfig = array_merge($config, $dbConfig);

        // Create DI Container
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            'source_dir' => __DIR__ . '/Controllers',

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
            }
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

        return $app;
    }
}
