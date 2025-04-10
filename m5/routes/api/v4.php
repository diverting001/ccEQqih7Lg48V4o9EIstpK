<?php

//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V4\Controllers\CalculateController@priceCalculate');
$api->post('Calculate/GetLog', 'App\Api\V4\Controllers\CalculateController@getLog');
//-----------------------------计算服务-end--------------------------//

//-----------------------------物流服务-start------------------------//
$api->post('Express/Get', 'App\Api\V4\Controllers\Express\ExpressController@GetExpressInfo');
$api->post('Express/Save', 'App\Api\V4\Controllers\Express\ExpressController@SaveExpress');
$api->post('Express/GetByCode', 'App\Api\V4\Controllers\Express\ExpressController@GetExpressInfoByCode');
$api->post('Express/Callback', 'App\Api\V4\Controllers\Express\ExpressController@ExpressCallback');
$api->post('Express/GetCompanyList', 'App\Api\V4\Controllers\Express\ExpressController@GetExpressCompanyList');
$api->post('Express/GetAutonumber', 'App\Api\V4\Controllers\Express\ExpressController@GetAutonumber');
$api->post('Express/GetChannelCompanyList', 'App\Api\V4\Controllers\Express\ExpressController@GetExpressChannelCompanyList');
//-----------------------------物流服务-end------------------------//


//-----------------------------上门取件服务-start------------------------//
$api->post('Pickup/Get', 'App\Api\V4\Controllers\Express\PickupController@GetPickupOrderInfo');
$api->post('Pickup/Save', 'App\Api\V4\Controllers\Express\PickupController@SavePickupOrder');
$api->post('Pickup/Cancel', 'App\Api\V4\Controllers\Express\PickupController@CancelPickupOrder');
//-----------------------------物流服务-end------------------------//
