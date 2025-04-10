<?php

//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V6\Controllers\CalculateController@priceCalculate');
$api->post('Calculate/GetLog', 'App\Api\V6\Controllers\CalculateController@getLog');

$api->post('CalculateV2/Get', 'App\Api\V6\Controllers\CalculateV2Controller@get');
$api->post('CalculateV2/Put', 'App\Api\V6\Controllers\CalculateV2Controller@orderCalculate');
//-----------------------------计算服务-end--------------------------//

// 积分测试
$api->post('PointTest/get_user_point', 'App\Api\V6\Controllers\PointTestController@get_user_point');
$api->post('PointTest/lock_point', 'App\Api\V6\Controllers\PointTestController@lock_point');
$api->post('PointTest/unlock_point', 'App\Api\V6\Controllers\PointTestController@unlock_point');
$api->post('PointTest/deduct_point', 'App\Api\V6\Controllers\PointTestController@deduct_point');
$api->post('PointTest/refund_point', 'App\Api\V6\Controllers\PointTestController@refund_point');

//-----------------------------商品服务-start------------------------//
/** @var App\Api\V6\Controllers\Goods\GoodsController*/
$api->post('Goods/SearchAggsList', 'App\Api\V6\Controllers\Goods\GoodsController@SearchAggsList');
$api->post('Goods/SearchList', 'App\Api\V6\Controllers\Goods\GoodsController@SearchList');
$api->post('Goods/SearchCount', 'App\Api\V6\Controllers\Goods\GoodsController@SearchCount');
//-----------------------------商品服务-end--------------------------//
