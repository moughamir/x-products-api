<?php
// index.php

require __DIR__ . '/vendor/autoload.php';

use App\App;

$app = App::bootstrap();
$app->add(function ($request, $handler) {
    error_log("Request URI: " . $request->getUri()->getPath());
    return $handler->handle($request);
});

$app->run();
