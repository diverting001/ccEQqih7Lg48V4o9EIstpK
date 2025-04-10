<?php

use Illuminate\Http\Request;

//-----------------------------券服务-start----------------------------//
$api->post(
    'Voucher/Freeshipping/GetCouponWithRule',
    'App\Api\V2\Controllers\FreeShippingController@queryCouponWithRule'
);
$api->post(
    'Voucher/DutyFree/GetCouponWithRule',
    'App\Api\V2\Controllers\DutyFreeController@queryCouponWithRule'
);
//-----------------------------券服务-end----------------------------//

//-----------------------------订单服务-start------------------------//
$api->post('Order/GetList', 'App\Api\V2\Controllers\OrderController@GetOrderList');
$api->post('Order/BatchGetInfo', 'App\Api\V2\Controllers\OrderController@BatchGetOrderInfo');   //批量获取订单详情
$api->post('Order/SearchOrderList', 'App\Api\V2\Controllers\OrderController@SearchOrderList'); // 订单查询
$api->post('Order/SearchCount', 'App\Api\V2\Controllers\OrderController@SearchCount'); // 订单查询总数
//-----------------------------订单服务-end------------------------//


//-----------------------------积分服务-start------------------------//
$api->post('Point/Get', 'App\Api\V2\Controllers\PointController@GetMemberPoint');
$api->post('Point/Lock', 'App\Api\V2\Controllers\PointController@LockMemberPoint');
$api->post('Point/CancelLock', 'App\Api\V2\Controllers\PointController@CancelLockMemberPoint');
$api->post('Point/ConfirmLock', 'App\Api\V2\Controllers\PointController@ConfirmLockMemberPoint');
$api->post('Point/Refund', 'App\Api\V2\Controllers\PointController@RefundPoint');
$api->post('Point/Lock/Record', 'App\Api\V2\Controllers\PointController@GetLockRecord');
$api->post('Point/GetMemberRecord', 'App\Api\V2\Controllers\PointController@GetMemberRecord');
$api->post('Point/WithRule', 'App\Api\V2\Controllers\PointController@WithRule');
$api->post('Point/QueryByOverdueTime', 'App\Api\V2\Controllers\PointController@GetMemberPointByOverdueTime');
$api->post('Point/Company/Get', 'App\Api\V2\Controllers\CompanyPointController@GetCompanyPoint');
$api->post('Point/Company/GetRecord', 'App\Api\V2\Controllers\CompanyPointController@GetCompanyRecordList');
$api->post('Point/Company/AssignLock', 'App\Api\V2\Controllers\CompanyPointController@LockCompanyPointByAssign');
$api->post('Point/Company/AssignUnLock', 'App\Api\V2\Controllers\CompanyPointController@UnLockCompanyPointByAssign');
$api->post('Point/Company/AssignToMembers', 'App\Api\V2\Controllers\CompanyPointController@CompanyAssignToMembers');
//-----------------------------积分服务-end--------------------------//

//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V2\Controllers\CalculateController@priceCalculate'); //获取预下单信息
$api->post('Calculate/GetLog', 'App\Api\V2\Controllers\CalculateController@getLog');
//-----------------------------计算服务-end--------------------------//


//-----------------------------发票服务-start------------------------//
$api->post('Invoice/Apply', 'App\Api\V2\Controllers\InvoiceController@Apply');
$api->post('Invoice/ApplyChange', 'App\Api\V2\Controllers\InvoiceController@ApplyChange');
$api->post('Invoice/ApplyCancel', 'App\Api\V2\Controllers\InvoiceController@ApplyCancel');
$api->post('Invoice/ApplyRevoke', 'App\Api\V2\Controllers\InvoiceController@ApplyRevoke');
$api->post('Invoice/GetApplyDetail', 'App\Api\V2\Controllers\InvoiceController@GetApplyDetail');
$api->post('Invoice/GetApplyList', 'App\Api\V2\Controllers\InvoiceController@GetApplyList');
$api->post('Invoice/Notify', 'App\Api\V2\Controllers\InvoiceController@Notify');
$api->post('Invoice/ApplyReview', 'App\Api\V2\Controllers\InvoiceController@ApplyReview');
$api->post('Invoice/FixApply', 'App\Api\V2\Controllers\InvoiceController@FixApplyDataException');
//-----------------------------发票服务-end--------------------------//

//-----------------------------快递服务-start------------------------//
//运费服务
$api->post('Delivery/Template/Create', 'App\Api\V2\Controllers\Delivery\TemplateController@Create');
$api->post('Delivery/Template/Update', 'App\Api\V2\Controllers\Delivery\TemplateController@Update');
$api->post('Delivery/Template/Get', 'App\Api\V2\Controllers\Delivery\TemplateController@Get');
$api->post('Delivery/Rule/BatchCreate', 'App\Api\V2\Controllers\Delivery\RuleController@BatchCreate');
$api->post('Delivery/Rule/BatchDelete', 'App\Api\V2\Controllers\Delivery\RuleController@BatchDelete');

$api->post('Delivery/Freight', 'App\Api\V2\Controllers\Delivery\RuleController@BatchQueryFreight');
$api->post('Delivery/Promise/GetProductPromise', 'App\Api\V2\Controllers\Delivery\PromiseController@GetProductPromiseInfo');

//快递限制
$api->post('DeliveryLimit/Template/Create', 'App\Api\V2\Controllers\DeliveryLimit\TemplateController@Create');
$api->post('DeliveryLimit/Template/Update', 'App\Api\V2\Controllers\DeliveryLimit\TemplateController@Update');
$api->post('DeliveryLimit/Rule/BatchCreate', 'App\Api\V2\Controllers\DeliveryLimit\RuleController@BatchCreate');
$api->post('DeliveryLimit/Rule/BatchDelete', 'App\Api\V2\Controllers\DeliveryLimit\RuleController@BatchDelete');

$api->post('DeliveryLimit/RuleList/Get', 'App\Api\V2\Controllers\DeliveryLimit\RuleController@getRuleListByTemplate');
$api->post('DeliveryLimit/BatchQueryStatus', 'App\Api\V2\Controllers\DeliveryLimit\RuleController@BatchQueryStatus');
//-----------------------------快递服务-end--------------------------//

//-----------------------------售后中心相关服务-start--------------------------//
$api->post('CustomerCare/AfterSale/Create', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@Create');
//$api->post('CustomerCare/AfterSale/Apply', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@Apply');
//$api->post('CustomerCare/AfterSale/Cancel', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@Cancel');
//$api->post('CustomerCare/AfterSale/SendBack', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@SendBack');
//$api->post('CustomerCare/AfterSale/PutInWareHouse', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@PutInWareHouse');
//$api->post('CustomerCare/AfterSale/Refund', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@Refund');
//$api->post('CustomerCare/AfterSale/Finish', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@Finish');
//$api->post('CustomerCare/AfterSale/RepackToCustomer', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@RepackToCustomer');
$api->post('CustomerCare/AfterSale/Find', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@find');
$api->post('CustomerCare/AfterSale/GetActualReasonType', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@getActualReasonType');
$api->post('CustomerCare/AfterSale/GetList', 'App\Api\V2\Controllers\CustomerCare\AfterSaleController@getList');

//$api->post('CustomerCare/WareHouse/Create', 'App\Api\V2\Controllers\CustomerCare\WareHouseController@Create');
//$api->post('CustomerCare/WareHouse/PutIn', 'App\Api\V2\Controllers\CustomerCare\WareHouseController@PutIn');
//$api->post('CustomerCare/WareHouse/Repack', 'App\Api\V2\Controllers\CustomerCare\WareHouseController@Repack');
//$api->post('CustomerCare/WareHouse/Find', 'App\Api\V2\Controllers\CustomerCare\WareHouseController@find');
//$api->post('CustomerCare/WareHouse/GetList', 'App\Api\V2\Controllers\CustomerCare\WareHouseController@getList');

//-----------------------------售后中心相关服务-end--------------------------//

//$api->post('Promotion/GetPromotionGoods', 'App\Api\V2\Controllers\Promotion\PromotionController@GetPromotionGoods');
$api->post('Promotion/GetGoodsPromotion', 'App\Api\V2\Controllers\Promotion\PromotionController@GetGoodsPromotion');
$api->post('Promotion/CheckPromotionGoods', 'App\Api\V2\Controllers\Promotion\PromotionController@CheckRule');
$api->post('Promotion/TimeBuy/Check', 'App\Api\V2\Controllers\Promotion\PromotionController@CheckStock');
$api->post('Promotion/TimeBuy/Lock', 'App\Api\V2\Controllers\Promotion\PromotionController@LockStock');
$api->post('Promotion/Money/Lock', 'App\Api\V2\Controllers\Promotion\PromotionController@LockMoney');
$api->post('Promotion/Money/Check', 'App\Api\V2\Controllers\Promotion\PromotionController@CheckMoney');
$api->post('Promotion/Rule/Create', 'App\Api\V2\Controllers\Promotion\RuleController@CreateRule');
$api->post('Promotion/Rule/Save', 'App\Api\V2\Controllers\Promotion\RuleController@SaveRule');
$api->post('Promotion/Rule/QueryList', 'App\Api\V2\Controllers\Promotion\RuleController@RuleList');
$api->post('Promotion/Rule/Query', 'App\Api\V2\Controllers\Promotion\RuleController@Query');
$api->post('Promotion/Rule/PubRuleRelList', 'App\Api\V2\Controllers\Promotion\RuleController@PubRuleRelList');
$api->post('Promotion/Rule/PubRule', 'App\Api\V2\Controllers\Promotion\RuleController@PubRule');
$api->post('Promotion/Rule/StatusEdit', 'App\Api\V2\Controllers\Promotion\RuleController@StatusEdit');
$api->post('Promotion/Rule/Delete', 'App\Api\V2\Controllers\Promotion\RuleController@DeleteRule');
$api->post('Promotion/WhiteList/Create', 'App\Api\V2\Controllers\Promotion\WhiteListController@Create');
$api->post('Promotion/WhiteList/Delete', 'App\Api\V2\Controllers\Promotion\WhiteListController@Delete');
$api->post('Promotion/WhiteList/GetList', 'App\Api\V2\Controllers\Promotion\WhiteListController@GetList');
$api->post('Promotion/Rule/GetCompanyRuleList', 'App\Api\V2\Controllers\Promotion\RuleController@GetCompanyRuleList');

$api->post('Outlet/getList','App\Api\V2\Controllers\Outlet\OutletController@getList');

//----------------------------ES订单索引结构初始化--------------------------------//
$api->post('EsOrderInit/CreateIndex', 'App\Api\V2\Controllers\EsOrderInitController@CreateIndex');

//-----------------------------消息中心服务-start------------------------//
//批量消息-支持多个用户
$api->post('MessageCenter/batchCreate', 'App\Api\V2\Controllers\MessageCenterController@batchCreate');
//-----------------------------消息中心服务-end------------------------//
