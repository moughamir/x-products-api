<?php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Middleware\ApiKeyMiddleware;

use App\Services\ImageProxy; // <-- ADDED: Import for ImageProxy DI setup
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

                // Use the db file path from the dedicated config file
                return new PDO("sqlite:" . $dbFile);
            },

            // Twig View setup
            Twig::class => function(FilesystemLoader $loader) {
                $loader->addPath(__DIR__ . '/../templates', 'default');
                return Twig::create(__DIR__ . '/../templates', [
                    'cache' => false,
                    'debug' => true,
                ]);
            },

            FilesystemLoader::class => \DI\create(FilesystemLoader::class),

            // The ImageProxy class needs an explicit configuration array passed to its constructor
            ImageProxy::class => \DI\autowire(ImageProxy::class)
                ->constructorParameter('config', array_merge($config, $dbConfig)),

            // Services using autowire (ProductService and ImageService require PDO, which is defined)
            ProductService::class => \DI\autowire(ProductService::class),
            ImageService::class => \DI\autowire(ImageService::class),

            // 🐛 FIX: Explicitly inject the 'source_dir' string parameter for ApiController
            ApiController::class => \DI\autowire(ApiController::class)
                ->constructorParameter('sourceDir', \DI\get('source_dir')), // <-- ARGUMENT COUNT ERROR FIX
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);

        $app = AppFactory::create();
        $app->setBasePath('/cosmos'); // Set base path as per structure

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

        // Image Proxy Route
        $app->get('/cdn/{path:.*}', [ImageProxy::class, 'proxy']); // Correct use of ImageProxy

        return $app;
    }
}
