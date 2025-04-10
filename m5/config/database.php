<?php

@include_once '/data/service_router.php';

return [
    'fetch' => PDO::FETCH_CLASS,
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => PSR_DB_STOCK_HOST,
            'port' => PSR_DB_STOCK_PORT,
            'database' => PSR_DB_STOCK_NAME,
            'username' => PSR_DB_STOCK_USER,
            'password' => PSR_DB_STOCK_PASSWORD,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'timezone' => '+08:00',
            'strict' => false,
            'options' => [
                // 开启持久连接
                \PDO::ATTR_PERSISTENT => true,
            ],
        ],
        'neigou_store' => [
            'driver' => 'mysql',
            'host' => PSR_DB_ECSTORE_HOST,
            'port' => PSR_DB_ECSTORE_PORT,
            'database' => PSR_DB_ECSTORE_NAME,
            'username' => PSR_DB_ECSTORE_USER,
            'password' => PSR_DB_ECSTORE_PASSWORD,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'timezone' => '+08:00',
            'strict' => false,
            'options' => [
                // 开启持久连接
                \PDO::ATTR_PERSISTENT => true,
            ],
        ],
        'neigou_hd' => [
            'driver' => 'mysql',
            'host' => PSR_DB_HD_HOST,
            'port' => PSR_DB_HD_PORT,
            'database' => PSR_DB_HD_NAME,
            'username' => PSR_DB_HD_USER,
            'password' => PSR_DB_HD_PASSWORD,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'timezone' => '+08:00',
            'strict' => false,
            'options' => [
                // 开启持久连接
                \PDO::ATTR_PERSISTENT => true,
            ],
        ],
        'neigou_club' => [
            'driver' => 'mysql',
            'host' => PSR_DB_CLUB_HOST,
            'port' => PSR_DB_CLUB_PORT,
            'database' => PSR_DB_CLUB_NAME,
            'username' => PSR_DB_CLUB_USER,
            'password' => PSR_DB_CLUB_PASSWORD,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'timezone' => '+08:00',
            'strict' => false,
            'options' => [
                // 开启持久连接
                \PDO::ATTR_PERSISTENT => true,
            ],
        ],
    ],
    'migrations' => 'migrations',
];
