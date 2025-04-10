<?php

use Illuminate\Http\Request;

//-----------------------------业务服务-start------------------------------//
$api->post('BusinessCode/Code/Create', 'App\Api\V1\Controllers\BusinessCodeController@CreateBusinessCode');
//-----------------------------业务服务-end---------------------------------//

//-----------------------------工具服务-start-------------------------------//
$api->post('Code/Voice', 'App\Api\V1\Controllers\CodeController@Voice');
/** @var  App\Api\V1\Controllers\ImageOCR\ImageOcrController@CardVoucherDistinguish OCR图片文字识别*/
$api->post('Ocr/CardVoucher/distinguish', 'App\Api\V1\Controllers\ImageOCR\ImageOcrController@CardVoucherDistinguish');
//国密sm2
$api->post('GuoMi/Sms2/GetSign', 'App\Api\V1\Controllers\GuoMi\Sm2Controller@GetSign');
$api->post('GuoMi/Sms2/CheckSign', 'App\Api\V1\Controllers\GuoMi\Sm2Controller@CheckSign');
$api->post('GuoMi/Sms2/DoEncrypt', 'App\Api\V1\Controllers\GuoMi\Sm2Controller@DoEncrypt');
$api->post('GuoMi/Sms2/DoDecrypt', 'App\Api\V1\Controllers\GuoMi\Sm2Controller@DoDecrypt');
//国密sm4
$api->post('GuoMi/Sms4/encrypt', 'App\Api\V1\Controllers\GuoMi\Sm4Controller@encrypt');
$api->post('GuoMi/Sms4/decrypt', 'App\Api\V1\Controllers\GuoMi\Sm4Controller@decrypt');

//获取accessToekn
$api->post('OpenPlatform/GetAccessToken', 'App\Api\V1\Controllers\OpenPlatformController@GetAccessToken');
$api->post('OpenPlatform/GetTicket', 'App\Api\V1\Controllers\OpenPlatformController@GetTicket');
$api->post('OpenPlatform/GetOauth2AccessToken', 'App\Api\V1\Controllers\OpenPlatformController@GetOauth2AccessToken');
$api->post('OpenPlatform/saveConfig', 'App\Api\V1\Controllers\OpenPlatformController@saveConfig'); //新增、修改配置
//脱敏账号查询（支付宝）
$api->post('DesensitizationAccount/getAccount', 'App\Api\V1\Controllers\DesensitizationAccountController@getAccount');
//-----------------------------工具服务-end---------------------------------//

//-----------------------------计算服务-start------------------------//
$api->post('Calculate/Get', 'App\Api\V1\Controllers\CalculateController@PriceCalculate');
//-----------------------------计算服务-end--------------------------//

//-----------------------------用户服务-start------------------------//
$api->post('Member/Invoice/Get', 'App\Api\V1\Controllers\Member\InvoiceController@Get');
$api->post('Member/Invoice/Save', 'App\Api\V1\Controllers\Member\InvoiceController@Save');
//-----------------------------用户服务-end--------------------------//


//-----------------------------拆单服务-start------------------------//
$api->post('OrderSplit/Get', 'App\Api\V1\Controllers\OrderSplitController@GetSplitInfo');
$api->post('OrderSplit/Create', 'App\Api\V1\Controllers\OrderSplitController@SaveSplit');
//-----------------------------拆单服务-end--------------------------//

//-----------------------------预下单服务-start------------------------//
$api->post('PreOrder/Create', 'App\Api\V1\Controllers\PreOrderController@Create');   //创建预下单
$api->post('PreOrder/Get', 'App\Api\V1\Controllers\PreOrderController@GetPreOrderInfo'); //获取预下单信息
//-----------------------------预下单服务-end--------------------------//

//-----------------------------搜索服务-start------------------------//
$api->post('Search/BusinessData/push', 'App\Api\V1\Controllers\SearchController@BusinessDataPush');
$api->post('Search/BusinessData/get', 'App\Api\V1\Controllers\SearchController@BusinessDataGet');
$api->post('Search/BusinessData/getCount', 'App\Api\V1\Controllers\SearchController@GetBusinessDataCount');
$api->post('Search/BusinessKeyword/cover', 'App\Api\V1\Controllers\SearchController@BusinessKeywordCover');
$api->post('Search/BusinessKeyword/del', 'App\Api\V1\Controllers\SearchController@BusinessKeywordDel');
$api->post('ProjectMall/Save', 'App\Api\V1\Controllers\ProjectMallController@Save');
$api->post('ProjectMall/Get', 'App\Api\V1\Controllers\ProjectMallController@Get');
//-----------------------------搜索服务-end--------------------------//

//-----------------------------地址服务-start------------------------//
$api->post('Address/Create', 'App\Api\V1\Controllers\AddressController@create');
$api->post('Address/Save', 'App\Api\V1\Controllers\AddressController@update');
$api->post('Address/Delete', 'App\Api\V1\Controllers\AddressController@delete');
$api->post('Address/Get', 'App\Api\V1\Controllers\AddressController@getRow');
$api->post('Address/GetList', 'App\Api\V1\Controllers\AddressController@getList');
$api->post('Address/Search', 'App\Api\V1\Controllers\AddressController@search');
$api->post('Address/Count', 'App\Api\V1\Controllers\AddressController@memberAddrCount');
$api->post('Address/GetSuggestLocationList', 'App\Api\V1\Controllers\AddressController@getSuggestLocationList');
$api->get('Address/getLocationKey', 'App\Api\V1\Controllers\AddressController@getLocationKey');
$api->post('Address/getRegionByLocation', 'App\Api\V1\Controllers\AddressController@getRegionByLocation');
$api->post('Address/getDistanceByLocation', 'App\Api\V1\Controllers\AddressController@getDistanceByLocation');
$api->post('Address/getBaiduRegionByLocation', 'App\Api\V1\Controllers\AddressController@getBaiduAddressByLonAndLat');
//-----------------------------地址服务-end--------------------------//

//-----------------------------运费服务-start------------------------//
$api->post('Delivery/Create', 'App\Api\V1\Controllers\DeliveryController@create');
$api->post('Delivery/Delete', 'App\Api\V1\Controllers\DeliveryController@del');
$api->post('Delivery/Edit', 'App\Api\V1\Controllers\DeliveryController@save');
$api->post('Delivery/Get', 'App\Api\V1\Controllers\DeliveryController@info');
$api->post('Delivery/List', 'App\Api\V1\Controllers\DeliveryController@lists');
$api->post('Delivery/Freight', 'App\Api\V1\Controllers\DeliveryController@freight');
$api->post('Delivery/freightDetail', 'App\Api\V1\Controllers\DeliveryController@freightDetail');
//ToB运费服务
$api->post('Delivery/ToB/Freight', 'App\Api\V1\Controllers\DeliveryToBController@freight');
//-----------------------------运费服务-end--------------------------//


//-----------------------------账单服务（支付单）-start------------------------//
$api->post('Bill/Create', 'App\Api\V1\Controllers\BillController@Create');
$api->post('Bill/setPayed', 'App\Api\V1\Controllers\BillController@setPayed');
$api->post('Bill/Get', 'App\Api\V1\Controllers\BillController@Get');
$api->post('BillId/Create', 'App\Api\V1\Controllers\BillController@CreateBillId');
$api->post('Bill/GetAppsByCode', 'App\Api\V1\Controllers\BillController@GetAppsByCode');
$api->post('Bill/setRefunded', 'App\Api\V1\Controllers\BillController@setRefunded');
//-----------------------------账单服务-end---------------------------------//

//-----------------------------支付服务-start------------------------//
$api->post('Payment/Config/Get', 'App\Api\V1\Controllers\PaymentController@GetConfig');
$api->post('Payment/AppList/GetWithCode', 'App\Api\V1\Controllers\PaymentController@GetAppListByCode');
$api->post('Payment/AppList/AddByCode', 'App\Api\V1\Controllers\PaymentController@AddAppListByCode');
//-----------------------------支付服务-end---------------------------------//

//-----------------------------授信服务-start------------------------------//
$api->post('Credit/Bill/Record', 'App\Api\V1\Controllers\CreditLimitController@Record');
$api->post('Credit/Bill/CancelRecord', 'App\Api\V1\Controllers\CreditLimitController@CancelRecord');
$api->post('Credit/Bill/Reply', 'App\Api\V1\Controllers\CreditLimitController@Reply');
$api->post('Credit/Bill/Balance', 'App\Api\V1\Controllers\CreditLimitController@Balance');
$api->post('Credit/Bill/Check', 'App\Api\V1\Controllers\CreditLimitController@Check');
$api->post('Credit/Account/Create', 'App\Api\V1\Controllers\CreditLimitController@CreateAccount');
$api->post('Credit/Account/Edit', 'App\Api\V1\Controllers\CreditLimitController@EditAccount');
$api->post('Credit/Account/Report', 'App\Api\V1\Controllers\CreditLimitController@AccountReport');
$api->post('Credit/Account/Get', 'App\Api\V1\Controllers\CreditLimitController@AccountInfo');
$api->post('Credit/Account/GetList', 'App\Api\V1\Controllers\CreditLimitController@AccountList');
//-----------------------------授信服务-end---------------------------------//


//-----------------------------经销商服务-start-------------------------------//
$api->post('Distribution/Distributor/getGoodsScope', 'App\Api\V1\Controllers\DistributorController@getGoodsScope');
$api->post('Distribution/Distributor/getDistributorList',
    'App\Api\V1\Controllers\DistributorController@getDistributorList');
$api->post('Distribution/Distributor/deductMoneyPool', 'App\Api\V1\Controllers\DistributorController@deductMoneyPool');
$api->post('Distribution/Distributor/refundMoneyPool', 'App\Api\V1\Controllers\DistributorController@refundMoneyPool');
$api->post(
    'Distribution/Distributor/SaveDistributorInfo',
    'App\Api\V1\Controllers\DistributorController@saveDistributorInfo'
);
$api->post(
    'Distribution/Distributor/getAllGoodsScope',
    'App\Api\V1\Controllers\DistributorController@getAllGoodsScope'
);
$api->post(
    'Distribution/Distributor/checkDeductMoneyPool',
    'App\Api\V1\Controllers\DistributorController@checkDeductMoneyPool'
);
$api->post('Distribution/Distributor/getMessageScope', 'App\Api\V1\Controllers\DistributorController@getMessageScope');

//-----------------------------经销商服务-end---------------------------------//

//-----------------------------库存服务-start------------------------//
$api->post('Stock/Lock', 'App\Api\V1\Controllers\StockController@Lock');
$api->post('Stock/CancelLock', 'App\Api\V1\Controllers\StockController@CancelLock');
$api->post('Stock/TempLock', 'App\Api\V1\Controllers\StockController@TempLock');
$api->post('Stock/CancelTempLock', 'App\Api\V1\Controllers\StockController@CancelTempLock');
$api->post('Stock/TempLockChange', 'App\Api\V1\Controllers\StockController@TempLockChange');
$api->post('Stock/Limie/Create', 'App\Api\V1\Controllers\StockRestrictController@Create');
$api->post('Stock/Limie/Delete', 'App\Api\V1\Controllers\StockRestrictController@Delete');
$api->post(
    'StockBackend/Message/StockUpdate',
    'App\Api\V1\Controllers\StockProductController@UpdateProductsMessage'
);
$api->post('Stock/Get', function (Request $request) {
    $obj = new App\Api\V1\Controllers\StockController();
    $obj->SetStockObj(new App\Api\V1\Service\Stock\BranchStock);
    $obj->SetStockObj(new App\Api\V1\Service\Stock\StockRestrict);
    return $obj->GetProudctStock($request);
});
$api->post('Stock/Main/Get', function (Request $request) {
    $obj = new App\Api\V1\Controllers\StockController();
    $obj->SetStockObj(new App\Api\V1\Service\Stock\BranchStock);
    return $obj->GetProudctStock($request);
});
//-----------------------------库存服务-end--------------------------//

//-----------------------------运营服务-start------------------------//
$api->get('Promotion/Operate/fieldDesc', 'App\Api\V1\Controllers\PromotionOperateController@fieldDesc');
$api->post('Promotion/GetPromotionGoods', 'App\Api\V1\Controllers\GoodsController@GetPromotion');
$api->post('Promotion/TimeBuy/Lock', 'App\Api\V1\Controllers\GoodsController@LockStock');
$api->post('Promotion/TimeBuy/UnLock', 'App\Api\V1\Controllers\GoodsController@UnLockStock');
$api->post('Promotion/TimeBuy/Check', 'App\Api\V1\Controllers\GoodsController@checkStock');
$api->post('Promotion/TimeBuy/PayedCancelUnLock', 'App\Api\V1\Controllers\GoodsController@PayedCancelUnLockStock');
$api->post('Promotion/TimeBuy/AfterSaleUnLock', 'App\Api\V1\Controllers\GoodsController@AfterSaleUnLockStock');
$api->post('Promotion/Operate/Get/ruleList', 'App\Api\V1\Controllers\PromotionOperateController@ruleList');
$api->post('Promotion/Operate/Get/ruleInfo', 'App\Api\V1\Controllers\PromotionOperateController@ruleInfo');
$api->post('Promotion/Operate/Get/ruleItems', 'App\Api\V1\Controllers\PromotionOperateController@ruleItems');
$api->post('Promotion/Operate/Get/ruleCompany', 'App\Api\V1\Controllers\PromotionOperateController@ruleCompany');
$api->post('Promotion/Operate/Set/rule', 'App\Api\V1\Controllers\PromotionOperateController@updatePromotion');
$api->post('Promotion/Operate/Create/rule', 'App\Api\V1\Controllers\PromotionOperateController@createPromotion');
$api->post('Promotion/Operate/Set/item', 'App\Api\V1\Controllers\PromotionOperateController@execItems');
$api->post('Promotion/Operate/Check/item', 'App\Api\V1\Controllers\PromotionOperateController@checkRuleItem');
$api->post('Promotion/Operate/Push/rule', 'App\Api\V1\Controllers\PromotionOperateController@pushRule');
//-----------------------------运营服务-end--------------------------//

//-----------------------------售后服务-start------------------------//
$api->post('AfterSale/Create', 'App\Api\V1\Controllers\AfterSaleController@create');
$api->post('AfterSale/Update', 'App\Api\V1\Controllers\AfterSaleController@update');
$api->post('AfterSale/GetSearchList', 'App\Api\V1\Controllers\AfterSaleController@getSearchList');
$api->post('AfterSale/GetList', 'App\Api\V1\Controllers\AfterSaleController@getList');
$api->post('AfterSale/Get', 'App\Api\V1\Controllers\AfterSaleController@getOne');
$api->post('AfterSale/CreateWareHouse', 'App\Api\V1\Controllers\AfterSaleController@addWareHouseOrder');
$api->post('AfterSale/UpdateWareHouse', 'App\Api\V1\Controllers\AfterSaleController@updateWareHouse');
$api->post('AfterSale/WareHouseSearchList', 'App\Api\V1\Controllers\AfterSaleController@wareHouseSearchList');
$api->post('AfterSale/GetWareHouse', 'App\Api\V1\Controllers\AfterSaleController@getWareHouse');
$api->post('AfterSale/GetWareHouseList', 'App\Api\V1\Controllers\AfterSaleController@getWareHouseList');
$api->post('AfterSale/ProductNum', 'App\Api\V1\Controllers\AfterSaleController@getProductNum');
//以上接口部分版本使用

//以下接口暂未修改
$api->post('AfterSale/CreateImage', 'App\Api\V1\Controllers\AfterSaleController@createImage');
$api->post('AfterSale/GetImage', 'App\Api\V1\Controllers\AfterSaleController@getImage');
//$api->post('AfterSale/AddLog', 'App\Api\V1\Controllers\AfterSaleController@addLog');//该接口业务未调用
$api->post('AfterSale/GetLog', 'App\Api\V1\Controllers\AfterSaleController@getLog');

$api->post('AfterSale/GetBackVoucherRuleList', 'App\Api\V1\Controllers\AfterSaleController@backRuleList');
$api->post('AfterSale/AddRemark', 'App\Api\V1\Controllers\AfterSaleController@addRemark');
$api->post('AfterSale/GetRemark', 'App\Api\V1\Controllers\AfterSaleController@getRemark');
$api->post('AfterSale/Appoint', 'App\Api\V1\Controllers\AfterSaleController@appoint');

//补充描述
$api->post('AfterSale/AddDescribe', 'App\Api\V1\Controllers\AfterSaleController@addDescribe');
$api->post('AfterSale/GetDescribe', 'App\Api\V1\Controllers\AfterSaleController@getDescribe');

//售后统计
$api->post('AfterSale/StatisticsCreate', 'App\Api\V1\Controllers\AfterSaleController@createStatistics');//create
$api->post('AfterSale/StatisticsUpdate', 'App\Api\V1\Controllers\AfterSaleController@updateStatistics');//update
$api->post('AfterSale/StatisticsGet', 'App\Api\V1\Controllers\AfterSaleController@getStatistics');//get
$api->post('AfterSale/StatisticsList', 'App\Api\V1\Controllers\AfterSaleController@getStatisticsList');//list
$api->post('AfterSale/StatisticsGetStatus', 'App\Api\V1\Controllers\AfterSaleController@getStatisticsStatus');
$api->post('AfterSale/StatisticsGetStatusById', 'App\Api\V1\Controllers\AfterSaleController@getStatisticsStatusById');

$api->post('Refund/GetList', 'App\Api\V1\Controllers\RefundController@GetList');

//-----------------------------售后服务-end--------------------------//


//-----------------------------订单服务-start------------------------//
$api->post('OrderId/Create', 'App\Api\V1\Controllers\OrderController@GenOrderId');
$api->post('Order/Create', 'App\Api\V1\Controllers\OrderController@Create');
$api->post('Order/DoPay', 'App\Api\V1\Controllers\OrderController@DoPay');
$api->post('Order/Cancel', 'App\Api\V1\Controllers\OrderController@Cancel');
$api->post('Order/PayedCancel', 'App\Api\V1\Controllers\OrderController@OrderPayedCancel');
$api->post('Order/Confirm', 'App\Api\V1\Controllers\OrderController@OrderConfirm');
$api->post('Order/Get', 'App\Api\V1\Controllers\OrderController@GetOrderInfo');
$api->post('Order/GetList', 'App\Api\V1\Controllers\OrderController@GetOrderList');
$api->post('WmsOrder/Get', 'App\Api\V1\Controllers\OrderController@GetWmsOrder');
$api->post('OrderPayment/GetList', 'App\Api\V1\Controllers\OrderController@GetOrderPaymentList');
$api->post('Order/RefundConfirm', 'App\Api\V1\Controllers\OrderController@RefundConfirm');
$api->post('Order/Message/OrderUpdate', 'App\Api\V1\Controllers\OrderController@OrderUpdateMessage');
$api->post('Order/TimeoutPayOrderReTry', 'App\Api\V1\Controllers\OrderController@TimeoutPayOrderReTry');
$api->post('Order/UpdateWmsOrderMsg', 'App\Api\V1\Controllers\OrderController@UpdateWmsOrderMsg');
//通过订单id记录异常订单数据
$api->post('Order/UpdateWmsOrderMsgByOrderId', 'App\Api\V1\Controllers\OrderController@UpdateWmsOrderMsgByOrderId');
$api->post('Order/GetOrderListForMis', 'App\Api\V1\Controllers\OrderController@GetOrderListForMis');
$api->post('Order/GetWmsOrderMsgList', 'App\Api\V1\Controllers\OrderController@GetWmsOrderMsgList');
$api->post('Order/GetMsgStats', 'App\Api\V1\Controllers\OrderController@GetMsgStats');
$api->post('Order/Pause', 'App\Api\V1\Controllers\OrderController@Pause');
$api->post('Order/GetPauseList', 'App\Api\V1\Controllers\OrderController@GetPauseList');
$api->post('Order/Intercept/Recovery', 'App\Api\V1\Controllers\OrderController@InterceptRecovery');
$api->post('Order/Update', 'App\Api\V1\Controllers\OrderController@Update');
$api->post('Order/CancelOrderForMis', 'App\Api\V1\Controllers\OrderController@CancelOrderForMis');
$api->post('Order/RetryWms', 'App\Api\V1\Controllers\OrderController@RetryWms');
$api->post('Order/SplitOrder', 'App\Api\V1\Controllers\OrderController@SplitOrder');

$api->post('OrderGather/create', 'App\Api\V1\Controllers\OrderGatherController@create');
// 获取公司订单统计(根据订单表中的某一列)
$api->post('Order/GetCompanyOrderStatistics', 'App\Api\V1\Controllers\OrderController@getCompanyOrderStatistics');
// 获取用户订单统计
$api->post('Order/GetMemberOrderStatistics', 'App\Api\V1\Controllers\OrderController@GetMemberOrderStatistics');
$api->post(
    'Order/UpdateWmsOrderMsgStatusById',
    'App\Api\V1\Controllers\OrderController@UpdateWmsOrderMsgStatusById'
);
$api->post(
    'Order/PayOrderCompleteByOrderId',
    'App\Api\V1\Controllers\OrderController@PayOrderCompleteByOrderId'
);

// 订单发票
$api->post('Invoice/Create', 'App\Api\V1\Controllers\InvoiceController@Create');
$api->post('Invoice/Detail/Get', 'App\Api\V1\Controllers\InvoiceController@GetDetail');
$api->post('Invoice/GetList', 'App\Api\V1\Controllers\InvoiceController@GetList');
$api->post('Invoice/Finance/Verify', 'App\Api\V1\Controllers\InvoiceController@FinanceVerify');
$api->post('Invoice/Finance/Delete', 'App\Api\V1\Controllers\InvoiceController@FinanceVerifyFail');
$api->post('Invoice/Finance/SaveDelivery', 'App\Api\V1\Controllers\InvoiceController@SaveDelivery');

$api->post('Order/Invoice/Apply', 'App\Api\V1\Controllers\OrderInvoiceController@apply');
$api->post('Order/Invoice/Change', 'App\Api\V1\Controllers\OrderInvoiceController@change');
$api->post('Order/Invoice/Cancel', 'App\Api\V1\Controllers\OrderInvoiceController@cancel');
$api->post('Order/Invoice/GetDetail', 'App\Api\V1\Controllers\OrderInvoiceController@getOrderInvoiceDetail');
$api->post('Order/Invoice/GetRecord', 'App\Api\V1\Controllers\OrderInvoiceController@getOrderInvoiceRecord');
$api->post('Order/Invoice/PreApplyBatch', 'App\Api\V1\Controllers\OrderInvoiceController@preApplyBatch');
$api->post('Order/Invoice/Confirm', 'App\Api\V1\Controllers\OrderInvoiceController@confirm');
$api->post('Order/Invoice/Revoke', 'App\Api\V1\Controllers\OrderInvoiceController@revoke');

//旧数据同步接口，已停用
$api->post('Order/Push', 'App\Api\V1\Controllers\OrderController@PushOrder');

$api->post('ServerOrders/supplierOrderIds', 'App\Api\V1\Controllers\ServerOrdersController@supplierOrderIds');
$api->post('ServerOrders/RefundSuccess', function (Request $request) {
    $obj = new App\Api\V1\Controllers\ServerOrdersController();
    return $obj->Refund(1, $request);
});
$api->post('ServerOrders/RefundFailed', function (Request $request) {
    $obj = new App\Api\V1\Controllers\ServerOrdersController();
    return $obj->Refund(2, $request);
});
$api->post('ServerOrders/RefundProcessing', function (Request $request) {
    $obj = new App\Api\V1\Controllers\ServerOrdersController();
    return $obj->Refund(3, $request);
});

// 订单审核
$api->post('Order/OrderReview/Create', 'App\Api\V1\Controllers\OrderReviewController@createOrderReview');
$api->post('Order/OrderReview/Get', 'App\Api\V1\Controllers\OrderReviewController@getOrderReviewInfo');
$api->post('Order/OrderReview/GetList', 'App\Api\V1\Controllers\OrderReviewController@getOrderReviewList');
$api->post('Order/OrderReview/GetMemberInfo', 'App\Api\V1\Controllers\OrderReviewController@getMemberReviewInfo');
$api->post('Order/OrderReview/Submit', 'App\Api\V1\Controllers\OrderReviewController@submitReview');
$api->post('Order/OrderReview/Approve', 'App\Api\V1\Controllers\OrderReviewController@approve');
$api->post('Order/OrderReview/Deny', 'App\Api\V1\Controllers\OrderReviewController@deny');

// 订单定制
$api->post('Order/OrderCustomization/GetInfo', 'App\Api\V1\Controllers\OrderCustomizationController@GetOrderCustomizationInfo');
$api->post('Order/OrderCustomization/GetResult', 'App\Api\V1\Controllers\OrderCustomizationController@GetOrderCustomizationResult');

//-----------------------------订单服务-end--------------------------//

//-----------------------------券服务-start----------------------------//
$api->post('Voucher/Get', 'App\Api\V1\Controllers\VoucherController@queryVoucher');
$api->post('Voucher/Count', 'App\Api\V1\Controllers\VoucherController@queryVoucherCount');
$api->post('Voucher/usedCount', 'App\Api\V1\Controllers\VoucherController@queryVoucherUsedCount');
$api->post('Voucher/Create', 'App\Api\V1\Controllers\VoucherController@addVoucher');
$api->post('Voucher/Use', 'App\Api\V1\Controllers\VoucherController@useVoucher');
$api->post('Voucher/Used/Get', 'App\Api\V1\Controllers\VoucherController@usedQuery');
$api->post('Voucher/MultiUse', 'App\Api\V1\Controllers\VoucherController@multiUseVoucher');
$api->post('Voucher/Disable', 'App\Api\V1\Controllers\VoucherController@disableVoucher');
$api->post('Voucher/Transfer', 'App\Api\V1\Controllers\VoucherController@transferVoucher');
$api->post('Voucher/DisableForCreateID', 'App\Api\V1\Controllers\VoucherController@disableVoucherForCreateID');
$api->post('Voucher/exchangeStatus', 'App\Api\V1\Controllers\VoucherController@exchangeStatus');
$api->post('Voucher/GetWithRule', 'App\Api\V1\Controllers\VoucherController@queryVoucherWithRule');
$api->post('Voucher/UseWithRule', 'App\Api\V1\Controllers\VoucherController@useVoucherWithRule');
$api->post('Voucher/MultiUseWithRule', 'App\Api\V1\Controllers\VoucherController@multiUseVoucherWithRule');
$api->post('Voucher/GetOrderVoucher', 'App\Api\V1\Controllers\VoucherController@queryOrderVoucher');
$api->post('Voucher/GetRefundOrderVoucher', 'App\Api\V1\Controllers\VoucherController@getOrderVoucher');
$api->post('Voucher/BlackList/Create', 'App\Api\V1\Controllers\VoucherController@addBlackList');
$api->post('Voucher/BlackList/Save', 'App\Api\V1\Controllers\VoucherController@saveBlackList');
$api->post('Voucher/BlackList/Delete', 'App\Api\V1\Controllers\VoucherController@deleteBlackList');
$api->post('Voucher/BlackList/Get', 'App\Api\V1\Controllers\VoucherController@queryBlackList');
$api->post('Voucher/BlackList/Query', 'App\Api\V1\Controllers\VoucherController@batchQueryBlackList');
$api->post('Voucher/Rule/Create', 'App\Api\V1\Controllers\VoucherController@createRule');
$api->post('Voucher/Rule/Save', 'App\Api\V1\Controllers\VoucherController@saveRule');
$api->post('Voucher/Rule/Get', 'App\Api\V1\Controllers\VoucherController@getRule');
$api->post('Voucher/Rule/GetList', 'App\Api\V1\Controllers\VoucherController@getRuleList');
$api->post('Voucher/Package/Create', 'App\Api\V1\Controllers\VoucherController@createPackage');
$api->post('Voucher/Package/Apply', 'App\Api\V1\Controllers\VoucherController@applyVoucherPkg');
$api->post('Voucher/Package/Get', 'App\Api\V1\Controllers\VoucherController@queryVoucherPkg');
$api->post('Voucher/PackageRule/Create', 'App\Api\V1\Controllers\VoucherController@createPackageRule');
$api->post('Voucher/MemberVoucher/CreateByCode', 'App\Api\V1\Controllers\VoucherController@addMemberVoucherByCode');
$api->post('Voucher/MemberVoucher/Used', 'App\Api\V1\Controllers\VoucherController@queryMemberVoucher');
$api->post('Voucher/MemberVoucher/UsedByCompany', 'App\Api\V1\Controllers\VoucherController@queryMemberVoucherByCompany');
$api->post('Voucher/MemberVoucher/GetBinded', 'App\Api\V1\Controllers\VoucherController@queryMemberBindedVoucher');
$api->post('Voucher/MemberVoucher/GetList', 'App\Api\V1\Controllers\VoucherController@queryMemberVoucherList');
$api->post('Voucher/MemberVoucher/Create', 'App\Api\V1\Controllers\VoucherController@createMemVoucher');
$api->post('Voucher/MemberVoucher/Refund', 'App\Api\V1\Controllers\VoucherController@refundMemVoucher');
$api->post('Voucher/MemberVoucher/Bind', 'App\Api\V1\Controllers\VoucherController@bindMemVoucher');
$api->post('Voucher/MemberVoucher/BatchBind', 'App\Api\V1\Controllers\VoucherController@largeBindMemVoucher');
$api->post('Voucher/MemberVoucher/ListWithRule', 'App\Api\V1\Controllers\VoucherController@getVoucherByProduct');
$api->post('Voucher/Freeshipping/MemberCoupon/Get', 'App\Api\V1\Controllers\FreeShippingController@queryMemberCoupon');
$api->post('Voucher/FreeShipping/GetOrderForCoupon', 'App\Api\V1\Controllers\FreeShippingController@queryOrderCoupon');
$api->post('Voucher/FreeshippingRule/GetList', 'App\Api\V1\Controllers\FreeShippingController@getRuleList');
$api->post('Voucher/FreeshippingRule/Get', 'App\Api\V1\Controllers\FreeShippingController@getRule');
$api->post('Voucher/FreeshippingRule/Save', 'App\Api\V1\Controllers\FreeShippingController@saveRule');
$api->post('Voucher/DutyFree/MemberCoupon/Get', 'App\Api\V1\Controllers\DutyFreeController@queryMemberCoupon');
$api->post('Voucher/DutyFree/MemberCoupon/Create', 'App\Api\V1\Controllers\DutyFreeController@createMemberCoupon');
$api->post('Voucher/DutyFree/CancelOrderForCoupon', 'App\Api\V1\Controllers\DutyFreeController@cancelOrderForCoupon');
$api->post('Voucher/DutyFree/FinishOrderForCoupon', 'App\Api\V1\Controllers\DutyFreeController@finishOrderForCoupon');
$api->post('Voucher/DutyFree/GetOrderForCoupon', 'App\Api\V1\Controllers\DutyFreeController@queryOrderCoupon');
$api->post('Voucher/DutyFreeRule/GetList', 'App\Api\V1\Controllers\DutyFreeController@getRuleList');
$api->post('Voucher/DutyFreeRule/Get', 'App\Api\V1\Controllers\DutyFreeController@getRule');
$api->post('Voucher/DutyFreeRule/Save', 'App\Api\V1\Controllers\DutyFreeController@saveRule');
$api->post(
    'Voucher/Rule/GetWithProduct',
    'App\Api\V1\Controllers\VoucherController@getRuleIdByProducts'
);
$api->post(
    'Voucher/MemberVoucher/GetBindedWithRule',
    'App\Api\V1\Controllers\VoucherController@queryMemberBindedVoucherWithRule'
);
$api->post(
    'Voucher/MemberVoucher/GetByGuid',
    'App\Api\V1\Controllers\VoucherController@queryMemberBindedVoucherByGuid'
);
$api->post(
    'Voucher/MemberVoucher/GetByCompany',
    'App\Api\V1\Controllers\VoucherController@queryMemberBindedVoucherByCompany'
);

$api->post('Voucher/MemberVoucher/GetByCreateID',
    'App\Api\V1\Controllers\VoucherController@GetByCreateID'
);

$api->post(
    'Voucher/FreeShipping/MemberCoupon/Create',
    'App\Api\V1\Controllers\FreeShippingController@createMemberCoupon'
);
$api->post(
    'Voucher/Freeshipping/MemberCoupon/GetWithRule',
    'App\Api\V1\Controllers\FreeShippingController@queryMemberCouponWithRule'
);
$api->post(
    'Voucher/FreeShipping/CreateOrderForCoupon',
    'App\Api\V1\Controllers\FreeShippingController@createOrderForCoupon'
);
$api->post(
    'Voucher/FreeShipping/CreateOrderForCoupons',
    'App\Api\V1\Controllers\FreeShippingController@createOrderForCouponV2'
);
$api->post(
    'Voucher/FreeShipping/CancelOrderForCoupon',
    'App\Api\V1\Controllers\FreeShippingController@cancelOrderForCoupon'
);
$api->post(
    'Voucher/FreeShipping/FinishOrderForCoupon',
    'App\Api\V1\Controllers\FreeShippingController@finishOrderForCoupon'
);
$api->post(
    'Voucher/Freeshipping/GetCouponWithRule',
    'App\Api\V1\Controllers\FreeShippingController@queryCouponWithRule'
);
$api->post(
    'Voucher/DutyFree/MemberCoupon/GetWithList',
    'App\Api\V1\Controllers\DutyFreeController@queryCouponWithList'
);
$api->post(
    'Voucher/DutyFree/MemberCoupon/GetWithRule',
    'App\Api\V1\Controllers\DutyFreeController@queryCouponWithRule'
);
$api->post(
    'Voucher/DutyFree/MemberCoupon/UseWithRule',
    'App\Api\V1\Controllers\DutyFreeController@useVoucherWithRule'
);
$api->post(
    'Voucher/DutyFree/CreateOrderForCoupon',
    'App\Api\V1\Controllers\DutyFreeController@createOrderForCoupon'
);
$api->post(
    'Voucher/DutyFree/CreateOrderForCoupons',
    'App\Api\V1\Controllers\DutyFreeController@createOrderForCouponV2'
);
//-----------------------------券服务-end----------------------------//


//-----------------------------积分服务-start------------------------//
$api->post('Point/Channel/GetList', 'App\Api\V1\Controllers\PointController@GetCompanyChannel');
$api->post('Point/Channel/Get', 'App\Api\V1\Controllers\PointController@GetChannelInfo');
$api->post('Point/Channel/All', 'App\Api\V1\Controllers\PointController@GetAllChannel');
$api->post('Point/Get', 'App\Api\V1\Controllers\PointController@GetMemberPoint');
$api->post('Point/Lock', 'App\Api\V1\Controllers\PointController@LockMemberPoint');
$api->post('Point/CancelLock', 'App\Api\V1\Controllers\PointController@CancelLockMemberPoint');
$api->post('Point/ConfirmLock', 'App\Api\V1\Controllers\PointController@ConfirmLockMemberPoint');
$api->post('Point/Refund', 'App\Api\V1\Controllers\PointController@RefundPoint');
$api->post('Point/Lock/Record', 'App\Api\V1\Controllers\PointController@GetLockRecord');
$api->post('Point/Company/Add', 'App\Api\V1\Controllers\PointController@AddCompany');
$api->post('Point/Company/Delete', 'App\Api\V1\Controllers\PointController@DeleteCompany');
$api->post('Point/GetMemberRecord', 'App\Api\V1\Controllers\PointController@GetMemberRecord');
$api->post(
    'Point/getPointChannelCompanyDetail',
    'App\Api\V1\Controllers\PointController@getPointChannelCompanyDetail'
);
$api->post(
    'Point/Company/SetCompanyMultiChannel',
    'App\Api\V1\Controllers\PointController@SetCompanyMultiChannel'
);
$api->post('Point/Channel/GetLists', 'App\Api\V1\Controllers\PointController@GetCompanyChannelByCompanyIds');
$api->post('Point/GetAllCompanyPoint', 'App\Api\V1\Controllers\PointController@GetMemberAllCompanyPoint');
//-----------------------------积分服务-end--------------------------//

//-----------------------------场景积分-start------------------------//
//内购规则接口
$api->post('Point/SceneRule/QueryList', 'App\Api\V1\Controllers\ScenePoint\SceneRuleController@QueryList');
$api->post('Point/SceneRule/Get', 'App\Api\V1\Controllers\ScenePoint\SceneRuleController@Query');
$api->post('Point/SceneRule/Create', 'App\Api\V1\Controllers\ScenePoint\SceneRuleController@Create');
$api->post('Point/SceneRule/Update', 'App\Api\V1\Controllers\ScenePoint\SceneRuleController@Update');
$api->post('Point/Scene/QueryList', 'App\Api\V1\Controllers\ScenePoint\SceneController@QueryList');

$api->post('Point/Scene/Create', 'App\Api\V1\Controllers\ScenePoint\SceneController@Create');//创建场景
$api->post('Point/Scene/Update', 'App\Api\V1\Controllers\ScenePoint\SceneController@Update');//创建场景
$api->post('Point/Scene/RelationCompany', 'App\Api\V1\Controllers\ScenePoint\SceneController@RelationCompany');//添加关联关系
$api->post('Point/Scene/GetCompanyRel', 'App\Api\V1\Controllers\ScenePoint\SceneController@GetCompanyRel');//公司关联的场景
$api->post('Point/Scene/Company/QueryAll', 'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@QueryAll');
$api->post('Point/Scene/Company/Income', 'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@Income');
$api->post('Point/Scene/Company/AssignList', 'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@AssignList');
$api->post('Point/Scene/Company/RecordList', 'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@RecordList');
$api->post('Point/Scene/Member/QueryAll', 'App\Api\V1\Controllers\ScenePoint\MemberAccountController@QueryAll');
$api->post('Point/Scene/Member/CreateOrder', 'App\Api\V1\Controllers\ScenePoint\MemberAccountController@CreateOrder');
$api->post('Point/Scene/Member/OrderConfirm', 'App\Api\V1\Controllers\ScenePoint\MemberAccountController@OrderConfirm');
$api->post('ScenePoint/OrderRecord/Get', 'App\Api\V1\Controllers\ScenePoint\OrderController@RecordGet');
$api->post(
    'Point/Scene/Company/getMemberOverduePointByCompany',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@getMemberOverduePointByCompany'
);
$api->post(
    'ScenePoint/CompanyAccount/Get',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@getCompanyAccount'
);
$api->post(
    'Point/Scene/Company/AssignFrozen',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@AssignFrozen'
);
$api->post(
    'Point/Scene/Company/UnAssignFrozen',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@UnAssignFrozen'
);
$api->post(
    'Point/Scene/Company/AssignToMembers',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@AssignToMembers'
);
$api->post(
    'Point/Scene/Member/OrderCancel',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@OrderCancel'
);
$api->post(
    'Point/Scene/Member/OrderRefund',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@OrderRefund'
);
$api->post(
    'Point/Scene/Member/RecordList',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@GetMemberRecord'
);
$api->post(
    'Point/Scene/Member/WithRule',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@WithRule'
);
$api->post(
    'Point/Scene/Member/QueryByOverdueTime',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@QueryByOverdueTime'
);

$api->post(
    'Point/Scene/Member/QueryAllCompany',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@QueryAllCompany'
);

$api->post(
    'Point/Scene/Member/QueryByCompany',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@QueryByCompany'
);

$api->post(
    'Point/Scene/Member/GetScenePointConsumeList',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@GetScenePointConsumeList'
);

// 积分转账冻结
$api->post(
    'Point/Scene/Member/TransferFrozen',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@TransferFrozen'
);

// 积分转账释放冻结
$api->post(
    'Point/Scene/Member/ReleaseTransferFrozen',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@ReleaseTransferFrozen'
);

// 积分转账
$api->post(
    'Point/Scene/Member/ScenePointTransfer',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@ScenePointTransfer'
);

// 查询积分转账子账户扣减详情
$api->post(
    'Point/Scene/Member/queryTransferPaySonAccount',
    'App\Api\V1\Controllers\ScenePoint\MemberAccountController@queryTransferPaySonAccount'
);

//根据公司id获取账户列表
$api->post(
    'ScenePoint/CompanyAccount/QueryWithCompanyIds',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@QueryWithCompanyIds'
);
//获取开启场景积分的企业列表
$api->post(
    'ScenePoint/CompanyList/Get',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@CompanyList'
);
//获取账户获取账户信息
$api->post(
    'ScenePoint/AccountInfo/GetWithAccounts',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@GetWithAccounts'
);
//获取账户获取账户信息
$api->post(
    'ScenePoint/Scene/QueryWithSceneIds',
    'App\Api\V1\Controllers\ScenePoint\SceneController@queryListById'
);
//获取账户获取账户信息
$api->post(
    'ScenePoint/SonAccountInfo/GetWithAccountId',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@GetSonAccountByAccountId'
);
//获取账户获取账户信息
$api->post(
    'ScenePoint/CompanyRecord/Query',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@QueryRecord'
);
//获取账户获取账户信息
$api->post(
    'ScenePoint/CompanyAccountRecord/Query',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@QueryCompanyAccountRecord'
);
//场景对应的公司列表
$api->post(
    'ScenePoint/CompanyList/GetBySceneId',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@GetBySceneId'
);

$api->post(
    'ScenePoint/SonAccount/Query',
    'App\Api\V1\Controllers\ScenePoint\AccountController@GetSonAccount'
);

$api->post(
    'ScenePoint/Channel/Get',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@GetChannel'
);

$api->post(
    'ScenePoint/Channel/Save',
    'App\Api\V1\Controllers\ScenePoint\CompanyAccountController@SaveChannel'
);
//-----------------------------场景积分-end--------------------------//


//-----------------------------限制规则服务-start------------------------//
//资产方使用接口
$api->post('RuleChannel/Get', 'App\Api\V1\Controllers\RuleController@getRuleChannel');
$api->post('Rule/Get', 'App\Api\V1\Controllers\RuleController@getRule');
$api->post('Rule/Save', 'App\Api\V1\Controllers\RuleController@saveRule');

//查询现有规则 不同步
$api->post('Rule/Query', 'App\Api\V1\Controllers\RuleController@queryRule');

//业务使用接口
$api->post('Rule/ChannelRuleBn/Query', 'App\Api\V1\Controllers\RuleController@getChannelRuleBn');
//-----------------------------限制规则服务-end--------------------------//

//-----------------------------内购规则服务-start--------------------------//
//规则服务下内购规则供应商
$api->post('NeigouRule/WithRule', 'App\Api\V1\Controllers\NeigouRuleController@withRule');
//-----------------------------内购规则服务-end--------------------------//

//-----------------------------资源服务-start------------------------//
$api->post('Resource/GetList', 'App\Api\V1\Controllers\ResourceController@getResourceList');
$api->post('Resource/Lock', 'App\Api\V1\Controllers\ResourceController@lock');
$api->post('Resource/Release', 'App\Api\V1\Controllers\ResourceController@release');
$api->post('Resource/Deduct', 'App\Api\V1\Controllers\ResourceController@deduct');
//-----------------------------资源服务-end--------------------------//

//-----------------------------场景积分记录订单&商品&积分-start------------------------//
$api->post('OrderScenePoint/Save', 'App\Api\V1\Controllers\OrderScenePointController@Save');
$api->post('OrderScenePoint/Get', 'App\Api\V1\Controllers\OrderScenePointController@Get');
$api->post('OrderScenePoint/GetListByOrderId', 'App\Api\V1\Controllers\OrderScenePointController@GetListByOrderId');
//-----------------------------场景积分记录订单&商品&积分-end--------------------------//

//-----------------------------消息通知服务-start------------------------//
$api->post('Message/sendMessage', 'App\Api\V1\Controllers\MessageController@sendMessage');
$api->post('Message/getTemplate', 'App\Api\V1\Controllers\MessageController@getTemplate');
$api->post('Message/createTemplate', 'App\Api\V1\Controllers\MessageController@createTemplate');
$api->post('Message/editTemplate', 'App\Api\V1\Controllers\MessageController@editTemplate');
$api->post('Message/getTemplateList', 'App\Api\V1\Controllers\MessageController@getTemplateList');
$api->post('Message/getMessageProgress', 'App\Api\V1\Controllers\MessageController@getMessageProgress');
// 模板和渠道绑定
$api->post('Message/bindTemplateChannel', 'App\Api\V1\Controllers\MessageController@bindTemplateChannel');
// 获取模板绑定的渠道列表
$api->post('Message/getTemplateBindChannels', 'App\Api\V1\Controllers\MessageController@getTemplateBindChannels');
// 获取渠道列表
$api->post('Message/getChannelList', 'App\Api\V1\Controllers\MessageController@getChannelList');
// 获取渠道详细
$api->post('Message/getChannel', 'App\Api\V1\Controllers\MessageController@getChannel');
// 获取模板绑定的渠道列表
$api->post('Message/getChannelIdListByTemplateId', 'App\Api\V1\Controllers\MessageController@getChannelIdListByTemplateId');

//根据公司获取配置的短信模板
$api->post('Message/getBusinessTemplateByCompany', 'App\Api\V1\Controllers\MessageController@getBusinessTemplateByCompany');
//获取基础短信模板配置
$api->post('Message/getBasicTemplateByBusinessKey', 'App\Api\V1\Controllers\MessageController@getBasicTemplateByBusinessKey');

//-----------------------------消息通知服务-end--------------------------//

//-----------------------------消息中心服务-start------------------------//
//创建消息中心
$api->post('MessageCenter/create', 'App\Api\V1\Controllers\MessageCenterController@create');
$api->post('MessageCenter/appendItems', 'App\Api\V1\Controllers\MessageCenterController@appendItems');
$api->post('MessageCenter/updateSendStatus', 'App\Api\V1\Controllers\MessageCenterController@updateSendStatus');
// 获取消息中心详情
$api->post('MessageCenter/getDetail', 'App\Api\V1\Controllers\MessageCenterController@getDetail');
// 获取列表
$api->post('MessageCenter/getList', 'App\Api\V1\Controllers\MessageCenterController@getList');

// 批量消息
$api->post('MessageCenter/batchCreate', 'App\Api\V1\Controllers\MessageCenterController@batchCreate');
//-----------------------------消息中心服务-start------------------------//

//-----------------------------公司消息服务-start------------------------//
// 创建公司和渠道绑定
$api->post('CompanyMessage/channelPush', 'App\Api\V1\Controllers\CompanyMessageController@channelPush');
// 获取公司推送的渠道列表
$api->post('CompanyMessage/getCompanyChannelPushList', 'App\Api\V1\Controllers\CompanyMessageController@getCompanyChannelPushList');
// 获取渠道绑定的公司id列表
$api->post('CompanyMessage/getChannelBindCompanyList', 'App\Api\V1\Controllers\CompanyMessageController@getChannelBindCompanyList');
// 取消公司和渠道绑定/推送
$api->post('CompanyMessage/cancelChannelPush', 'App\Api\V1\Controllers\CompanyMessageController@cancelChannelPush');
// 获取渠道推送的基础信息
$api->post('CompanyMessage/getChannelPushBaseList', 'App\Api\V1\Controllers\CompanyMessageController@getChannelPushBaseList');

//-----------------------------公司消息服务-end--------------------------//

//电子发票
$api->post('EInvoice/SubmitInvoiceInfo','App\Api\V1\Controllers\EInvoiceController@SubmitInvoiceInfo');//提交蓝票信息
$api->post('EInvoice/SubmitRedInvoiceInfo','App\Api\V1\Controllers\EInvoiceController@SubmitRedInvoiceInfo');//提交红票信息
$api->post('EInvoice/GetDownloadInvoiceInfo','App\Api\V1\Controllers\EInvoiceController@GetDownloadInvoiceInfo');//下载发票
$api->post('EInvoice/SimpleSubmitRedInvoiceInfo','App\Api\V1\Controllers\EInvoiceController@SimpleSubmitRedInvoiceInfo');//简版红冲
$api->post('EInvoice/RetrySubmitEinvoiceInfo','App\Api\V1\Controllers\EInvoiceController@RetrySubmitEinvoiceInfo');//发票重开

//账户相关
$api->post('Account/Get','App\Api\V1\Controllers\AccountController@getAccountInfo'); // 获取账户相关信息

//-----------------------------结算相关-start------------------------//
$api->post('Settlement/Rule/Create', 'App\Api\V1\Controllers\SettlementController@createRule');
$api->post('Settlement/Rule/Get', 'App\Api\V1\Controllers\SettlementController@getRuleInfo');
$api->post('Settlement/Rule/GetList', 'App\Api\V1\Controllers\SettlementController@getRuleList');

$api->post('Settlement/Channel/Create', 'App\Api\V1\Controllers\SettlementController@createChannel');
$api->post('Settlement/Channel/Update', 'App\Api\V1\Controllers\SettlementController@updateChannel');
$api->post('Settlement/Channel/Get', 'App\Api\V1\Controllers\SettlementController@getChannelInfo');
$api->post('Settlement/Channel/GetList', 'App\Api\V1\Controllers\SettlementController@getChannelList');
$api->post('Settlement/Channel/BindPaymentByCompany', 'App\Api\V1\Controllers\SettlementController@bindPaymentByCompany');
$api->post('Settlement/Channel/BindRule', 'App\Api\V1\Controllers\SettlementController@bindRule');
$api->post('Settlement/Channel/BindAccount', 'App\Api\V1\Controllers\SettlementController@bindAccount');
$api->post('Settlement/Channel/OrderPay', 'App\Api\V1\Controllers\SettlementController@orderPay');
$api->post('Settlement/Channel/OrderRefund', 'App\Api\V1\Controllers\SettlementController@orderRefund');
$api->post('Settlement/Channel/GetPaymentByCompany', 'App\Api\V1\Controllers\SettlementController@getPaymentByCompany');
//-----------------------------结算相关-end--------------------------//

//-----------------------------支付账户-start------------------------//
$api->post('PaymentAccount/Create', 'App\Api\V1\Controllers\PaymentAccountController@createAccount');
$api->post('PaymentAccount/Get', 'App\Api\V1\Controllers\PaymentAccountController@getAccountInfo');
$api->post('PaymentAccount/GetList', 'App\Api\V1\Controllers\PaymentAccountController@getAccountList');

$api->post('PaymentAccount/BindAccount', 'App\Api\V1\Controllers\PaymentAccountController@bindAccount');
$api->post('PaymentAccount/OrderPay', 'App\Api\V1\Controllers\PaymentAccountController@orderPay');
$api->post('PaymentAccount/OrderRefund', 'App\Api\V1\Controllers\PaymentAccountController@orderRefund');
//-----------------------------支付账户-end--------------------------//

//-----------------------------账户相关-start------------------------//
$api->post('Account/Create', 'App\Api\V1\Controllers\AccountController@createAccount');
$api->post('Account/Update', 'App\Api\V1\Controllers\AccountController@updateAccount');
$api->post('Account/Get', 'App\Api\V1\Controllers\AccountController@getAccountInfo');
$api->post('Account/GetList', 'App\Api\V1\Controllers\AccountController@getAccountList');
$api->post('Account/GetRecordList', 'App\Api\V1\Controllers\AccountController@getRecordList');
$api->post('Account/GetAccountRecordTotalAmount', 'App\Api\V1\Controllers\AccountController@getAccountRecordTotalAmount');
$api->post('Account/getDayAmountList', 'App\Api\V1\Controllers\AccountController@getDayAmountList');
$api->post('Account/Recharge', 'App\Api\V1\Controllers\AccountController@recharge');
$api->post('Account/CreditLimit', 'App\Api\V1\Controllers\AccountController@updateCreditLimit');
$api->post('Account/DeductBatch', 'App\Api\V1\Controllers\AccountController@deductBatch');
$api->post('Account/RefundBatch', 'App\Api\V1\Controllers\AccountController@refundBatch');
$api->post('Account/GetBillList', 'App\Api\V1\Controllers\AccountController@getBillList');
$api->post('Account/CreateBill', 'App\Api\V1\Controllers\AccountController@createBill');

//-----------------------------账户相关-end--------------------------//

//-----------------------------资产相关-start------------------------//
$api->post('Asset/Register', 'App\Api\V1\Controllers\AssetController@registerAsset');
$api->post('Asset/Get', 'App\Api\V1\Controllers\AssetController@getAssetDetail');
//-----------------------------资产相关-end--------------------------//

//-----------------------------人员权限-start------------------------//
$api->post( 'MemberScope/CreateRule', 'App\Api\V1\Controllers\Member\ScopeController@Create' );//创建人员权限
$api->post( 'MemberScope/UpdateRule', 'App\Api\V1\Controllers\Member\ScopeController@Update' );//更新人员权限
$api->post( 'MemberScope/GetScopeIdentifyListByRuleBn', 'App\Api\V1\Controllers\Member\ScopeController@GetScopeIdentifyListByRuleBn' );//根据规则获取人员权限
$api->post( 'MemberScope/GetScopeByRuleBnsAndIdentify', 'App\Api\V1\Controllers\Member\ScopeController@GetScopeByRuleBnsAndIdentify' );//获取规则和人员标识获取权限
//-----------------------------人员权限-end------------------------//

//获取商品限制
$api->post('Operate/LimitBuy/Get','App\Api\V1\Controllers\Operate\LimitBuyController@GetGoodsLimitBuy');
$api->post('Operate/LimitBuy/Supplier/Get','App\Api\V1\Controllers\Operate\LimitBuyController@GetSupplierGoodsLimitBuy');

//-----------------------------订单状态变更日志------------------------//
$api->post('Order/ChangeLog', 'App\Api\V1\Controllers\OrderChangeLogController@getOrderChangeLog');
//-----------------------------订单状态变更日志------------------------//


//获取商品限额
$api->post('Operate/LimitMoney/Get','App\Api\V1\Controllers\Operate\LimitMoneyController@GetGoodsLimitMoney');

//---------------------------门店管理-outlet----------------------------------//
/** @var App\Api\V1\Controllers\Outlet\OutletController */
$api->post('Outlet/Create','App\Api\V1\Controllers\Outlet\OutletController@create');
$api->post('Outlet/Update','App\Api\V1\Controllers\Outlet\OutletController@update');
$api->post('Outlet/Delete','App\Api\V1\Controllers\Outlet\OutletController@delete');
$api->post('Outlet/GetList','App\Api\V1\Controllers\Outlet\OutletController@getList');
$api->post('Outlet/GetInfo','App\Api\V1\Controllers\Outlet\OutletController@getInfo');
$api->post('Outlet/GetOutletAgg','App\Api\V1\Controllers\Outlet\OutletController@getOutletAgg');
//---------------------------门店管理-outlet----------------------------------//

//-----------------------------三方组件------------------------//
$api->post('ThirdComponent/GetEvaluateComponent', 'App\Api\V1\Controllers\ThirdComponent\GoodsComponentController@GetEvaluateComponentInfo');
$api->post('ThirdComponent/GetExpressComponent', 'App\Api\V1\Controllers\ThirdComponent\ExpressComponentController@GetExpressComponentInfo');
//-----------------------------三方组件------------------------//

//-----------------------------商品校验------------------------//
$api->post('GoodsVerify/MatchGoods', 'App\Api\V1\Controllers\GoodsVerifyController@GetMatchGoodsList');
//-----------------------------商品校验------------------------//

//----------------------------基础订单风控--------------------------------//
$api->post('BasicBusinessLimit/Create', 'App\Api\V1\Controllers\BasicBusinessLimitController@Create');
$api->post('BasicBusinessLimit/Update', 'App\Api\V1\Controllers\BasicBusinessLimitController@Update');
$api->post('BasicBusinessLimit/UpdateState', 'App\Api\V1\Controllers\BasicBusinessLimitController@UpdateState');
$api->post('BasicBusinessLimit/GetList', 'App\Api\V1\Controllers\BasicBusinessLimitController@GetList');
$api->post('BasicBusinessLimit/GetInfo', 'App\Api\V1\Controllers\BasicBusinessLimitController@GetInfo');
$api->post('BasicBusinessLimit/Validate', 'App\Api\V1\Controllers\BasicBusinessLimitController@Validate');

//-----------------------------验证码------------------------//
$api->post('Captcha/checkCaptcha', 'App\Api\V1\Controllers\Captcha\CaptchaController@checkCaptcha');


//---------------------------线下品牌规则管理-brand_rule----------------------------------//
/** @var App\Api\V1\Controllers\Brand\BrandRuleController */
$api->post('BrandRule/Create','App\Api\V1\Controllers\Brand\BrandRuleController@CreateBrandRule');
$api->post('BrandRule/Update','App\Api\V1\Controllers\Brand\BrandRuleController@UpdateBrandRule');
$api->post('BrandRule/Delete','App\Api\V1\Controllers\Brand\BrandRuleController@DeleteBrandRule');
$api->post('BrandRule/GetList','App\Api\V1\Controllers\Brand\BrandRuleController@GetBrandRuleList');
$api->post('BrandRule/GetOutletList','App\Api\V1\Controllers\Brand\BrandRuleController@GetBrandRuleOutletList');
//---------------------------线下品牌规则管理-brand_rule----------------------------------//


//--------------------------操作异常数据记录-exception_msg----------------------------------//
/** @var \App\Api\V1\Controllers\ExceptionMsgController */
$api->post('ExceptionMsg/CreateMember', 'App\Api\V1\Controllers\ExceptionMsgController@CreateMemberException');
$api->post('ExceptionMsg/GetMemberList', 'App\Api\V1\Controllers\ExceptionMsgController@GetMemberExceptionList');
//---------------------------操作异常数据记录-exception_msg----------------------------------//

//--------------------------商品服务费管理-start----------------------------------//
/** @var \App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController */
$api->post('Goods/paymentTaxFee/getRateSingle', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getRateSingle');//根据给定的公司id和单bn，获取到单个货品的税率
$api->post('Goods/paymentTaxFee/getRateSingleNotRate', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getRateSingleNotRate');//根据给定的公司id和单bn，获取到单个货品是否要收服务费
$api->post('Goods/paymentTaxFee/getRateMulti', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getRateMulti');//根据给定的公司id和多个bn，获取到多个货品的税率
$api->post('Goods/paymentTaxFee/getTaxFeeInfo', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getTaxFeeInfo');//根据id获取服务费详情
$api->post('Goods/paymentTaxFee/createTaxFee', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@createTaxFee');//新增服务费分组记录
$api->post('Goods/paymentTaxFee/updateTaxFee', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@updateTaxFee');//修改服务费分组记录
$api->post('Goods/paymentTaxFee/deleteTaxFee', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@deleteTaxFee');// 删除服务费分组记录
$api->post('Goods/paymentTaxFee/sendTaxFee', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@sendTaxFee');//推送服务费分组记录
$api->post('Goods/paymentTaxFee/addTaxFeeProduct', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@addTaxFeeProduct');//新增服务费分组的货品记录【单个】
$api->post('Goods/paymentTaxFee/delTaxFeeProduct', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@delTaxFeeProduct');//删除服务费分组的货品记录【单个】
$api->post('Goods/paymentTaxFee/getTaxFeeList', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getTaxFeeList');//获取服务费分组列表
$api->post('Goods/paymentTaxFee/getGroupProductList', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getGroupProductList');//获取分组服务费对应的货品列表
$api->post('Goods/paymentTaxFee/getTaxFeesSendCompany', 'App\Api\V1\Controllers\GoodsPool\GoodsPaymentTaxFeeController@getTaxFeesSendCompany');//获取服务费分组对应的推送公司id列表
//---------------------------商品服务费管理-end-----------------------------------//
