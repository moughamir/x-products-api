<?php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Middleware\ApiKeyMiddleware;

// REMOVED: use App\Services\ImageProxy; // No longer needed
use App\Services\ProductService;
use App\Services\ImageService;
use Slim\Views\Twig; // ADDED: Need to use Twig for Swagger UI
use PDO;
use Slim\Routing\RouteCollectorProxy;
use DI\ContainerBuilder;
use Twig\Loader\FilesystemLoader; // ADDED: For setting up Twig

class App
{
    public static function bootstrap(): \Slim\App
    {
        // Path fix: Adjusted require to reflect the nested config files
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        // Create DI Container
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            'source_dir' => __DIR__ . '/Controllers', // ADDED: For OpenAPI Generator to scan

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
            ProductService::class => function($container) {
                return new ProductService($container->get(PDO::class));
            },
            ImageService::class => function($container) {
                return new ImageService($container->get(PDO::class));
            },
            Twig::class => function() { // ADDED: Twig setup for Swagger UI
                $loader = new FilesystemLoader(__DIR__ . '/../templates');
                return new Twig($loader);
            },
            ApiController::class => function($container) {
                // ADDED: Twig dependency to the controller's constructor
                return new ApiController(
                    $container->get(ProductService::class),
                    $container->get(ImageService::class),
                    $container->get(Twig::class)
                );
            },
            // REMOVED: ImageProxy::class definition (No more image proxy service)
        ]);

        $container = $containerBuilder->build();

        // Pass 'source_dir' to the container for access in ApiController::swaggerJson
        $container->set('source_dir', __DIR__ . '/Controllers');

        // Create app with the container
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->setBasePath('/cosmos'); // Set base path as per structure

        // Middleware
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        // API Routes (Grouped for Middleware)
        $apiKeyMiddleware = new ApiKeyMiddleware($config['api_key']);

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

        // REMOVED: $app->get('/image-proxy', [ImageProxy::class, 'output']); // Removed image-proxy route

        return $app;
    }
}
