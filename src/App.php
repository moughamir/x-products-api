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
use DI\Container;
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

        // Create DI Container
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            // PSR-7 Factories for Middleware/Response creation
            ResponseFactoryInterface::class => DI\create(Psr17Factory::class),
            StreamFactoryInterface::class => DI\create(Psr17Factory::class),

            // PDO Database Connection
            PDO::class => function() use ($dbConfig) {
                $dbFile = $dbConfig['db_file'];
                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                $pdo = new PDO("sqlite:" . $dbFile);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Enable foreign key support if necessary
                //$pdo->exec('PRAGMA foreign_keys = ON;');
                return $pdo;
            },

            // Services and Dependencies
            ProductService::class => function($container) {
                return new ProductService($container->get(PDO::class));
            },
            ImageService::class => function($container) {
                return new ImageService($container->get(PDO::class));
            },
            ApiController::class => function($container) {
                return new ApiController(
                    $container->get(ProductService::class),
                    $container->get(ImageService::class),
                    $container->get('config')
                );
            },

            // Pass configuration array explicitly to classes that need it
            ImageProxy::class => function() use ($config) {
                return new ImageProxy($config);
            },

            // Register config for use in other classes (e.g., ApiController)
            'config' => $config
        ]);

        $container = $containerBuilder->build();

        // Create app with the container
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->setBasePath('/cosmos');

        // Middleware
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
        // Route: /cosmos/cdn/{path:.*}
        $app->get('/cdn/{path:.*}', [ImageProxy::class, 'proxy']);

        return $app;
    }
}
