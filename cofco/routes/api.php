<?php
$router->group([
    'prefix' => 'v1'
], function ($api) {
    require __DIR__ . '/api/v1.php';
});


$router->group([
    'prefix' => 'v2'
], function ($api) {
    require __DIR__ . '/api/v2.php';
});

$router->group([
    'prefix' => 'v3'
], function ($api) {
    require __DIR__ . '/api/v3.php';
});

$router->group([
    'prefix' => 'v4'
], function ($api) {
    require __DIR__ . '/api/v4.php';
});
