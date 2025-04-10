<?php

use Illuminate\Http\Request;

//-----------------------------店铺服务-start------------------------//
$api->post('Shop/GetList', 'App\Api\V3\Controllers\ShopController@GetList');
$api->post('Shop/GetAccountList', 'App\Api\V3\Controllers\ShopController@GetAccountList');
$api->post('Shop/GetPopShopList', 'App\Api\V3\Controllers\ShopController@GetPopShopList');
//-----------------------------店铺服务-end--------------------------//


//-----------------------------价格服务-start-------------------------------//
$api->post('Price/GetList', 'App\Api\V3\Controllers\PriceController@GetList');
//-----------------------------价格服务-end---------------------------------//


//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V3\Controllers\CalculateController@priceCalculate');
$api->post('Calculate/GetLog', 'App\Api\V3\Controllers\CalculateController@getLog');
//-----------------------------计算服务-end--------------------------//


//-----------------------------商品服务-start------------------------//
$api->post('Goods/SearchList', 'App\Api\V3\Controllers\GoodsController@SearchList');
$api->post('Goods/GetList', 'App\Api\V3\Controllers\GoodsController@GetList');
$api->post('Goods/Get', 'App\Api\V3\Controllers\GoodsController@Get');
$api->post('Product/GetList', 'App\Api\V3\Controllers\ProductController@GetList');
$api->post('Product/Get', 'App\Api\V3\Controllers\ProductController@Get');


$api->post('Cat/GetList', 'App\Api\V3\Controllers\Goods\CatController@GetList');
$api->post('ProviderCat/GetList', 'App\Api\V3\Controllers\Goods\ProviderCatController@GetList');
$api->post('Brand/SearchList', 'App\Api\V3\Controllers\Goods\BrandController@SearchList');
$api->post('Goods/Manage/SearchList', 'App\Api\V3\Controllers\Goods\ManageController@SearchList');
$api->post('Goods/Manage/Get', 'App\Api\V3\Controllers\Goods\ManageController@Get');
$api->post('Goods/Manage/Update', 'App\Api\V3\Controllers\Goods\ManageController@Update');
$api->post('Goods/Manage/UpdateList', 'App\Api\V3\Controllers\Goods\ManageController@UpdateList');

//-----------------------------商品服务-end--------------------------//


//-----------------------------库存服务-start----------------------------//
$api->post('Stock/Lock', 'App\Api\V3\Controllers\StockController@Lock');
$api->post('Stock/CancelLock', 'App\Api\V3\Controllers\StockController@CancelLock');
$api->post('Stock/TempLock', 'App\Api\V3\Controllers\StockController@TempLock');
$api->post('Stock/CancelTempLock', 'App\Api\V3\Controllers\StockController@CancelTempLock');
$api->post('Stock/TempLockChange', 'App\Api\V3\Controllers\StockController@TempLockChange');
$api->post('Stock/Limie/Create', 'App\Api\V3\Controllers\StockRestrictController@Create');
$api->post('Stock/Limie/Delete', 'App\Api\V3\Controllers\StockRestrictController@Delete');
$api->post(
    'StockBackend/Message/StockUpdate',
    'App\Api\V3\Controllers\StockProductController@UpdateProductsMessage'
);
$api->post('Stock/GetList', function (Request $request) {
    $obj = new App\Api\V3\Controllers\StockController();
    $obj->SetStockObj(new App\Api\V3\Service\Stock\BranchStock);
    $obj->SetStockObj(new App\Api\V3\Service\Stock\StockRestrict);
    return $obj->GetProudctStock($request);
});
$api->post('Stock/Main/Get', function (Request $request) {
    $obj = new App\Api\V3\Controllers\StockController();
    $obj->SetStockObj(new App\Api\V3\Service\Stock\BranchStock);
    return $obj->GetProudctStock($request);
});
//-----------------------------库存服务-end----------------------------//

//-----------------------------账号服务-start------------------------//
//公司信息
$api->post('Company/Create', 'App\Api\V3\Controllers\Account\CompanyController@create');
$api->post('Company/QueryById', 'App\Api\V3\Controllers\Account\CompanyController@queryById');
$api->post('Company/QueryByCode', 'App\Api\V3\Controllers\Account\CompanyController@queryByCode');
$api->post('Company/QueryByFullName', 'App\Api\V3\Controllers\Account\CompanyController@queryByFullName');

//员工信息
$api->post('CompanyMember/Create', 'App\Api\V3\Controllers\Account\CompanyMemberController@create');
$api->post('CompanyMember/QueryById', 'App\Api\V3\Controllers\Account\CompanyMemberController@queryById');
$api->post('CompanyMember/QueryByEmail', 'App\Api\V3\Controllers\Account\CompanyMemberController@queryByEmail');
$api->post(
    'CompanyMember/UpdateById',
    'App\Api\V3\Controllers\Account\CompanyMemberController@updateById'
);
$api->post(
    'CompanyMember/UpdateByCompanyAndMember',
    'App\Api\V3\Controllers\Account\CompanyMemberController@updateByCompanyAndMember'
);

$api->post(
    'CompanyMember/QueryByCompanyAndMember',
    'App\Api\V3\Controllers\Account\CompanyMemberController@queryByCompanyAndMember'
);
$api->post(
    'CompanyMember/QueryByCompanyAndAccount',
    'App\Api\V3\Controllers\Account\CompanyMemberController@queryByCompanyAndAccount'
);

//公司地址
$api->post('CompanyAddress/create', 'App\Api\V3\Controllers\Account\CompanyAddressController@create');
$api->post(
    'CompanyAddress/QueryByCompanyId',
    'App\Api\V3\Controllers\Account\CompanyAddressController@queryByCompanyId'
);
$api->post(
    'CompanyAddress/QueryByAddressId',
    'App\Api\V3\Controllers\Account\CompanyAddressController@queryByAddressId'
);
$api->post(
    'CompanyAddress/UpdateByAddressId',
    'App\Api\V3\Controllers\Account\CompanyAddressController@updateByAddressId'
);
$api->post(
    'CompanyAddress/DeleteByAddressId',
    'App\Api\V3\Controllers\Account\CompanyAddressController@deleteByAddressId'
);

// 用户相关
$api->post('Member/Create', 'App\Api\V3\Controllers\Account\MemberController@create');
$api->post('Member/QueryById', 'App\Api\V3\Controllers\Account\MemberController@queryById');
$api->post('Member/QueryByMobile', 'App\Api\V3\Controllers\Account\MemberController@queryByMobile');
$api->post('Member/QueryByEmail', 'App\Api\V3\Controllers\Account\MemberController@queryByEmail');
$api->post('Member/UpdateById', 'App\Api\V3\Controllers\Account\MemberController@updateById');
$api->post('Member/CreateLoginToken', 'App\Api\V3\Controllers\Account\MemberController@createLoginToken');
$api->post('Member/GetInfoByToken', 'App\Api\V3\Controllers\Account\MemberController@getInfoByToken');
$api->post(
    'Member/QueryByCompanyAndAccount',
    'App\Api\V3\Controllers\Account\MemberController@queryByCompanyAndAccount'
);

// 用户安全相关
$api->post('MemberSecurity/SetPassword', 'App\Api\V3\Controllers\Account\MemberSecurityController@setPassword');
$api->post('MemberSecurity/CheckPassword', 'App\Api\V3\Controllers\Account\MemberSecurityController@checkPassword');

//福利卡
$api->post('WelfareCard/LockCard', 'App\Api\V3\Controllers\WelfareCard\WelfareCardController@lockCard');
$api->post('WelfareCard/UseCard', 'App\Api\V3\Controllers\WelfareCard\WelfareCardController@useCard');
$api->post('WelfareCard/GetUseRecord', 'App\Api\V3\Controllers\WelfareCard\WelfareCardController@getUseRecord');
$api->post(
    'WelfareCard/GetWelfareCardByPassword',
    'App\Api\V3\Controllers\WelfareCard\WelfareCardController@getWelfareCardByPassword'
);
//-----------------------------账号服务-end---------------------------------//


//-----------------------------工具服务-start-------------------------------//
// 物流服务
$api->post('Express/Get', 'App\Api\V3\Controllers\Express\ExpressController@GetExpressInfo');
$api->post('Express/Register', 'App\Api\V3\Controllers\Express\ExpressController@RegisterExpress');
$api->post('Express/Save', 'App\Api\V3\Controllers\Express\ExpressController@SaveExpress');
$api->post('Express/GetCompanyList', 'App\Api\V3\Controllers\Express\ExpressController@GetExpressCompanyList');

// 地址服务
$api->post('Region/GetChildList', 'App\Api\V3\Controllers\Region\RegionController@GetChildList');
$api->post('Region/GetParentList', 'App\Api\V3\Controllers\Region\RegionController@GetParentList');
$api->post('Region/GetParentAll', 'App\Api\V3\Controllers\Region\RegionController@GetParentAll');

// 图片
$api->post('Image/Upload', 'App\Api\V3\Controllers\Image\ImageController@UploadImage');

// 短信
$api->post('Sms/Send', 'App\Api\V3\Controllers\Sms\SmsController@SendSms');

// 日志
$api->post('Log/GetActionList', 'App\Api\V3\Controllers\Log\LogController@GetActionList');
//-----------------------------工具服务-end---------------------------------//


//-----------------------------积分服务-start------------------------//
$api->post('Point/Get', 'App\Api\V3\Controllers\PointController@GetMemberPoint');
$api->post('Point/Lock', 'App\Api\V3\Controllers\PointController@LockMemberPoint');
$api->post('Point/ConfirmLock', 'App\Api\V3\Controllers\PointController@ConfirmMemberPoint');
$api->post('Point/CancelLock', 'App\Api\V3\Controllers\PointController@CancelMemberPoint');
$api->post('Point/Refund', 'App\Api\V3\Controllers\PointController@RefundMemberPoint');
$api->post('Point/GetMemberRecord', 'App\Api\V3\Controllers\PointController@GetMemberRecord');
//-----------------------------积分服务-end--------------------------//

//-----------------------------内购场景积分-start------------------------//
//对接积分服务
$api->post('ScenePoint/MemberAccount/Get', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@GetMemberAccount');
$api->post('ScenePoint/MemberAccount/Lock', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@LockMemberPoint');
$api->post('ScenePoint/MemberAccount/ConfirmLock', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@ConfirmMemberPoint');
$api->post('ScenePoint/MemberAccount/CancelLock', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@CancelMemberPoint');
$api->post('ScenePoint/MemberAccount/Refund', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@RefundMemberPoint');
$api->post('ScenePoint/MemberAccount/Record', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@GetMemberRecord');
$api->post('ScenePoint/MemberAccount/WithRule', 'App\Api\V3\Controllers\ScenePoint\ScenePointApiController@MemberPointWithRule');

//B端管理
$api->post('ScenePoint/AccountManage/GetMemberList', 'App\Api\V3\Controllers\ScenePoint\AccountManageController@GetMemberList');

//-----------------------------内购场景积分-end--------------------------//



