<?php
defined("THRIFT_LIB_PATH") or define('THRIFT_LIB_PATH' , dirname(dirname(__DIR__)) . '/lib/php/lib') ;
if(!class_exists('Thrift\ClassLoader\ThriftClassLoader')) {
    require_once THRIFT_LIB_PATH  . '/Thrift/ClassLoader/ThriftClassLoader.php';
}

