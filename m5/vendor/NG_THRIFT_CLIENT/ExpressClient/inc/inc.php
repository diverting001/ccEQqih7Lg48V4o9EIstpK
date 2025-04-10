<?php
/**
 * Created by PhpStorm.
 * User: liyunlong
 * Date: 2015/7/8
 * Time: 15:13
 */
namespace ExpressServer;
header("Content-Type:text/html;charset=utf8");
define('ROOT', __DIR__ . '/../');
include_once ROOT.'inc/thrift.inc.php';
$gen = ROOT . 'gen-php';
$loader = new \Thrift\ClassLoader\ThriftClassLoader();
$loader->registerNamespace('Thrift', ROOT . '../lib/php/lib');
$loader->registerDefinition('ExpressServer', $gen);
$loader->register();
if(!class_exists('\\Neigou\\Logger'))
    die("please include the NG_PHP_LIB/Logger.php Plugin");
