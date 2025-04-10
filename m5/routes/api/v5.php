<?php

//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V5\Controllers\CalculateController@priceCalculate');
$api->post('Calculate/GetLog', 'App\Api\V5\Controllers\CalculateController@getLog');
//-----------------------------计算服务-end--------------------------//

//-----------------------------商品服务-start------------------------//
/** @var App\Api\V5\Controllers\Goods\GoodsController*/
$api->post('Goods/SearchAggsList', 'App\Api\V5\Controllers\Goods\GoodsController@SearchAggsList');
$api->post('Goods/SearchList', 'App\Api\V5\Controllers\Goods\GoodsController@SearchList');
$api->post('Goods/SearchCount', 'App\Api\V5\Controllers\Goods\GoodsController@SearchCount');
//-----------------------------商品服务-end--------------------------//
