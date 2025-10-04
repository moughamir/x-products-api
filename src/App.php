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
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class App
{
    public static function bootstrap(): \Slim\App
    {
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            ResponseFactoryInterface::class => DI\create(Psr17Factory::class),
            StreamFactoryInterface::class => DI\create(Psr17Factory::class),

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

            // ApiController now receives config to process Image URLs
            ApiController::class => fn($container) => new ApiController(
                $container->get(ProductService::class),
                $container->get(ImageService::class),
                $container->get('config')
            ),

            // ImageProxy does NOT need cache config anymore
            ImageProxy::class => fn() => new ImageProxy($config),

            'config' => $config
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->setBasePath('/cosmos');

        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

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
