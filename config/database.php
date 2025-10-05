<?php
// config/database.php

$defaultPath = __DIR__ . '/../data/sqlite/products.sqlite';
$dbFile = getenv('SQLITE_PATH') ?: $defaultPath;

return [
    'db_file' => $dbFile,
];
