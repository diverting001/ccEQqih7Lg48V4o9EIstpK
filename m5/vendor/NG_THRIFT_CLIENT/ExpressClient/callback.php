<?php
/**
 * Created by PhpStorm.
 * User: liyunlong
 * Date: 2015/7/8
 * Time: 13:44
 */
header("Content-Type:text/html;charset=utf8");
$param = $_POST['param'];
include __DIR__ . '/client/ExpressClient.php';
$res = new ThriftExpressClient();
echo $res->callBackFromKuaidi($param);
