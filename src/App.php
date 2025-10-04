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
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            'source_dir' => __DIR__ . '/Controllers',

            PDO::class => function() use ($dbConfig) {
                $dbFile = $dbConfig['db_file'];

                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                return new PDO("sqlite:" . $dbFile);
            },

            Twig::class => function(FilesystemLoader $loader) {
                $loader->addPath(__DIR__ . '/../templates', 'default');
                return Twig::create(__DIR__ . '/../templates', [
                    'cache' => false,
                    'debug' => true,
                ]);
            },

            FilesystemLoader::class => \DI\create(FilesystemLoader::class),

            ImageProxy::class => \DI\autowire(ImageProxy::class)
                ->constructorParameter('config', array_merge($config, $dbConfig)),

            ProductService::class => \DI\autowire(ProductService::class),
            ImageService::class => \DI\autowire(ImageService::class),

            ApiController::class => \DI\autowire(ApiController::class)
                ->constructorParameter('sourceDir', \DI\get('source_dir')),
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);

        $app = AppFactory::create();
        $app->setBasePath('/cosmos');

        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        $apiKeyMiddleware = new ApiKeyMiddleware($config['api_key']);
        $app->get('/', function ($request, $response, $args) {
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

        $app->get('/swagger-ui', [ApiController::class, 'swaggerUi']);
        $app->get('/openapi.json', [ApiController::class, 'swaggerJson']);

        $app->get('/cdn/{path:.*}', [ImageProxy::class, 'proxy']);

        return $app;
    }
}
