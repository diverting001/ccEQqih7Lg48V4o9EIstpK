<?php

//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V4\Controllers\CalculateController@priceCalculate');
$api->post('Calculate/GetLog', 'App\Api\V4\Controllers\CalculateController@getLog');
//-----------------------------计算服务-end--------------------------//
