<?php

namespace App\Api\Logic;


use App\Api\Dispatcher;

class Service
{
    private $_service_config = array(
        'stock_lock'                     => array(
            'service_name' => 'stock',
            'api'          => 'Stock/Lock',
            'version'      => 'v1'
        ),
        'stock_temp_lock'                => array(
            'service_name' => 'stock',
            'api'          => 'Stock/TempLock',
            'version'      => 'v1'
        ),
        'stock_temp_lock_cancel'         => array(
            'service_name' => 'stock',
            'api'          => 'Stock/CancelTempLock',
            'version'      => 'v1'
        ),
        'stock_temp_lock_change'         => array(
            'service_name' => 'stock',
            'api'          => 'Stock/TempLockChange',
            'version'      => 'v1'
        ),
        'order_split'                    => array(
            'service_name' => 'order_split',
            'api'          => 'OrderSplit/Get',
            'version'      => 'v1'
        ),
        'order_list'                     => array(
            'service_name' => 'order',
            'api'          => 'Order/GetList',
            'version'      => 'v1'
        ),
        'riskmanagement_chekc_order'     => array(
            'service_name' => 'riskmanagement',
            'api'          => 'RiskManagement/CreateOrder/Cehck',
            'version'      => 'v1'
        ),
        'order_info'                     => array(
            'service_name' => 'order',
            'api'          => 'Order/Get',
            'version'      => 'v1'
        ),
        'preorder_info'                  => array(
            'service_name' => 'preorder',
            'api'          => 'PreOrder/Get',
            'version'      => 'v1'
        ),
        'get_promotion_goods'            => array(
            'service_name' => 'promotion',
            'api'          => 'Promotion/GetPromotionGoods',
            'version'      => 'v1'
        ),
        'check_goods_promotion'            => array(
            'service_name' => 'promotion',
            'api'          => 'Promotion/CheckPromotionGoods',
            'version'      => 'v2'
        ),
        'promotion_check'            => array(
            'service_name' => 'promotion',
            'api'          => 'Promotion/Check',
            'version'      => 'v3'
        ),
        'get_freight'                    => array(
            'service_name' => 'delivery',
            'api'          => 'Delivery/Freight',
            'version'      => 'v1'
        ),
        'get_freight_v2'                    => array(
            'service_name' => 'delivery',
            'api'          => 'Delivery/Freight',
            'version'      => 'v2'
        ),
        'get_channel_point'              => array(
            'service_name' => 'point',
            'api'          => 'Point/Channel/Get',
            'version'      => 'v1'
        ),
        'get_member_point'               => array(
            'service_name' => 'point',
            'api'          => 'Point/Get',
            'version'      => 'v1'
        ),
        'member_point_with_rule'         => array(
            'service_name' => 'point',
            'api'          => 'Point/WithRule',
            'version'      => 'v2'
        ),
        'get_point_record_use'              => array(
            'service_name' => 'point',
            'api'          => 'Point/GetRecordByUse',
            'version'      => 'v3'
        ),
        'business_data_push'             => array(
            'service_name' => 'search',
            'api'          => 'Search/BusinessData/push',
            'version'      => 'v1'
        ),
        'business_data_get'              => array(
            'service_name' => 'search',
            'api'          => 'Search/BusinessData/get',
            'version'      => 'v1'
        ),
        'get_products_list'              => array(
            'service_name' => 'search',
            'api'          => 'Search/GetProductsList',
            'version'      => 'v1'
        ),
        'voucher_get'              => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/Get',
            'version'      => 'v1'
        ),
        'voucher_with_rule'              => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/GetWithRule',
            'version'      => 'v1'
        ),
        'voucher_order_get'              => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/GetOrderVoucher',
            'version'      => 'v1'
        ),
        'dutyfree_with_rule'             => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/DutyFree/GetCouponWithRule',
            'version'      => 'v2'
        ),
        'dutyfree_order_get'             => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/DutyFree/GetOrderForCoupon',
            'version'      => 'v1'
        ),
        'freeshipping_with_rule'         => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/Freeshipping/GetCouponWithRule',
            'version'      => 'v2'
        ),
        'freeshipping_order_get'         => array(
            'service_name' => 'Voucher',
            'api'          => 'Voucher/FreeShipping/GetOrderForCoupon',
            'version'      => 'v1'
        ),
        'get_stock'                      => array(
            'service_name' => 'stock',
            'api'          => 'Stock/Main/Get',
            'version'      => 'v1'
        ),
        'invoice_apply'                  => array(
            'service_name' => 'invoice',
            'api'          => 'Invoice/Apply',
            'version'      => 'v2'
        ),
        'invoice_change'                 => array(
            'service_name' => 'invoice',
            'api'          => 'Invoice/ApplyChange',
            'version'      => 'v2'
        ),
        'invoice_cancel'                 => array(
            'service_name' => 'invoice',
            'api'          => 'Invoice/ApplyCancel',
            'version'      => 'v2'
        ),
        'invoice_revoke'                 => array(
            'service_name' => 'invoice',
            'api'          => 'Invoice/ApplyRevoke',
            'version'      => 'v2'
        ),
        'invoice_detail'                 => array(
            'service_name' => 'invoice',
            'api'          => 'Invoice/GetApplyDetail',
            'version'      => 'v2'
        ),
        'get_member_scene_point'         => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/QueryAll',
            'version'      => 'v1'
        ),
        'lock_member_scene_point'        => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/CreateOrder',
            'version'      => 'v1'
        ),
        'cancel_member_scene_point'      => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/OrderCancel',
            'version'      => 'v1'
        ),
        'confirm_member_scene_point'     => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/OrderConfirm',
            'version'      => 'v1'
        ),
        'refund_member_scene_point'      => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/OrderRefund',
            'version'      => 'v1'
        ),
        'member_scene_point_record'      => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/RecordList',
            'version'      => 'v1'
        ),
        'member_scene_point_with_rule'   => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/WithRule',
            'version'      => 'v1'
        ),
        'get_company_scene_point'        => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Company/QueryAll',
            'version'      => 'v1'
        ),
        'get_company_scene_point_record' => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Company/RecordList',
            'version'      => 'v1'
        ),
        'lock_company_scene_point'       => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Company/AssignFrozen',
            'version'      => 'v1'
        ),
        'un_lock_company_scene_point'    => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Company/UnAssignFrozen',
            'version'      => 'v1'
        ),
        'company_assign_point'           => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Company/AssignToMembers',
            'version'      => 'v1'
        ),
        'get_member_scene_point_overdue' => array(
            'service_name' => 'scene_point',
            'api'          => 'Point/Scene/Member/QueryByOverdueTime',
            'version'      => 'v1'
        ),
        'image_upload'                   => array(
            'service_name' => 'tools',
            'api'          => 'Image/Upload',
            'version'      => 'v3'
        ),
        'rule_bn_to_neigou_rule_id'      => array(
            'service_name' => 'rule',
            'api'          => 'Rule/ChannelRuleBn/Query',
            'version'      => 'v1'
        ),
        'aftersale_getlist'              => array(
            'service_name' => 'aftersale',
            'api'          => 'AfterSale/GetList',
            'version'      => 'v1'
        ),
        'aftersale_getlist_v2'              => array(
            'service_name' => 'aftersale',
            'api'          => 'CustomerCare/AfterSale/GetList',
            'version'      => 'v2'
        ),
        'aftersale_get_v2'              => array(
            'service_name' => 'aftersale',
            'api'          => 'CustomerCare/AfterSale/Find',
            'version'      => 'v2'
        ),
        'express_get'                    => array(
            'service_name' => 'tools',
            'api'          => 'Express/Get',
            'version'      => 'v3'
        ),
        'express_get_v4'                    => array(
            'service_name' => 'tools',
            'api'          => 'Express/Get',
            'version'      => 'v4'
        ),
        'express_save'                    => array(
            'service_name' => 'tools',
            'api'          => 'Express/Save',
            'version'      => 'v4'
        ),
        'get_channel_list'               => array(
            'service_name' => 'point',
            'api'          => 'Point/Channel/GetList',
            'version'      => 'v1'
        ),
        'scene_point_v3_get'             => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/Get',
            'version'      => 'v3'
        ),
        'scene_point_v3_lock'          => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/Lock',
            'version'      => 'v3'
        ),
        'scene_point_v3_confirm'       => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/ConfirmLock',
            'version'      => 'v3'
        ),
        'scene_point_v3_cancel'        => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/CancelLock',
            'version'      => 'v3'
        ),
        'scene_point_v3_refund'        => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/Refund',
            'version'      => 'v3'
        ),
        'scene_point_v3_get_record'              => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/Record',
            'version'      => 'v3'
        ),
        'scene_point_v3_with_rule'         => array(
            'service_name' => 'scene_point',
            'api'          => 'ScenePoint/MemberAccount/WithRule',
            'version'      => 'v3'
        ),
        'product_with_rule'         => array(
            'service_name' => 'rule',
            'api'          => 'NeigouRule/WithRule',
            'version'      => 'v1'
        ),
        'order_confirm'    => array(
            'service_name' => 'order',
            'api'          => 'Order/Confirm',
            'version'      => 'v1'
        ),
        'order_cancel'     => array(
            'service_name' => 'order',
            'api'          => 'Order/Cancel',
            'version'      => 'v1'
        ),
        'order_payed_cancel' => array(
            'service_name' => 'order',
            'api'          => 'Order/PayedCancel',
            'version'      => 'v1'
        ),
        'exception_msg_update' => array(
            'service_name' => 'order',
            'api'          => 'Order/UpdateWmsOrderMsg',
            'version'      => 'v1'
        ),
        'calculate_log_get' => array(
            'service_name' => 'calculate',
            'api'          => 'Calculate/GetLog',
            'version'      => 'v2'
        ),
        'payment_account_order_pay' => array(
            'service_name' => 'payment_account',
            'api'          => 'PaymentAccount/OrderPay',
            'version'      => 'v1'
        ),
        'payment_account_order_refund' => array(
            'service_name' => 'payment_account',
            'api'          => 'PaymentAccount/OrderRefund',
            'version'      => 'v1'
        ),
        'account_get_list' => array(
            'service_name' => 'account',
            'api'          => 'Account/GetList',
            'version'      => 'v1'
        ),
        'account_deduct_batch' => array(
            'service_name' => 'account',
            'api'          => 'Account/DeductBatch',
            'version'      => 'v1'
        ),
        'account_refund_batch' => array(
            'service_name' => 'account',
            'api'          => 'Account/RefundBatch',
            'version'      => 'v1'
        ),
        // 基础定价服务
        "base_price_batch"  => array(
            'service_name' => 'base_price',
            'api'          => 'Price/GetBaseList',
            'version'      => 'v3'
        ) ,
        // 运营价格服务
        "operate_price_batch"  => array(
            'service_name' => 'operate_price',
            'api'          => 'Price/GetList',
            'version'      => 'v3'
        ) ,
        // 资产获取
        "asset_get"  => array(
            'service_name' => 'asset',
            'api'          => 'Asset/Get',
            'version'      => 'v1'
        ) ,

        // 获取消息渠道
        "message_get_channel"  => array(
            'service_name' => 'message',
            'api'          => 'Message/getChannel',
            'version'      => 'v1'
        ) ,
        // 获取消息渠道列表
        "message_get_channel_list"  => array(
            'service_name' => 'message',
            'api'          => 'Message/getChannelList',
            'version'      => 'v1'
        ) ,
        // 获取公司消息渠道推送的基础信息
        "company_message_get_channel_push_base_list"  => array(
            'service_name' => 'company_message',
            'api'          => 'CompanyMessage/getChannelPushBaseList',
            'version'      => 'v1'
        ) ,
        // 消息发送
        "message_send"  => array(
            'service_name' => 'message',
            'api'          => 'Message/sendMessage',
            'version'      => 'v1'
        ) ,

        // 获取消息进程
        "message_get_progress"  => array(
            'service_name' => 'message',
            'api'          => 'Message/getMessageProgress',
            'version'      => 'v1'
        ) ,

        // 资产注册
        'asset_register' => array(
            'service_name' => 'asset',
            'api'          => 'Asset/Register',
            'version'      => 'v1'
        ) ,

        "point_get" => array(
            'service_name' => 'point',
            'api'          => 'Point/Get',
            'version'      => 'v3'
        ) ,
        // 积分锁定
        "point_lock" => array(
            'service_name' => 'point',
            'api'          => 'Point/Lock',
            'version'      => 'v3'
        ) ,
        // 积分取消
        'point_cancel' => array(
             'service_name' => 'point',
             'api'          =>  'Point/CancelLock',
             'version'      => 'v3'
         ) ,
        // 积分锁定之后确认
        'point_confirm' =>  array(
            'service_name' => 'point',
            'api'          =>  'Point/ConfirmLock',
            'version'      => 'v3'
        ) ,
        'calculate_get' => array(
            'service_name' => 'calculate',
            'api'          => 'Calculate/Get',
            'version'      => 'v6'
        ),

        'calculatev2_put' => array(
            'service_name' => 'calculate',
            'api'          => 'CalculateV2/Put',
            'version'      => 'v6'
        ) ,
        'calculatev2_get' => array(
            'service_name' => 'calculate',
            'api'          => 'CalculateV2/Get',
            'version'      => 'v6'
        ) ,
        // 资源锁定
        'resource_lock' => array(
            'service_name' => 'resource',
            'api'          => 'Resource/Lock',
            'version'      => 'v1'
        ) ,
        // 锁定限时限购资源
        // Promotion/TimeBuy/Lock', 'v2'
        'promotion_timeBuy_lock' => array(
            'service_name' => 'promotion',
            'api'          => 'Promotion/TimeBuy/Lock',
            'version'      => 'v2'
        ) ,
        // 锁定金额
        'promotion_money_lock' => array(
            'service_name' => 'promotion',
            'api'          => 'Promotion/Money/Lock',
            'version'      => 'v2'
        ) ,
        // 锁定优惠券
        'voucher_lock' => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/MultiUse',
            'version'      => 'v1'
        ) ,
         // 取消锁定
        'voucher_cancel' => array(
                'service_name' => 'voucher',
                'api'          => 'Voucher/exchangeStatus',
                'version'      => 'v1'
        ) ,
        // 锁定免邮券
        'freeShipping_lock' => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/FreeShipping/CreateOrderForCoupons',
            'version'      => 'v1'
        ) ,
        // 取消锁定 取消订单使用免邮券
        'freeShipping_cancel' => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/FreeShipping/CancelOrderForCoupon',
            'version'      => 'v1'
        ) ,
        // 免邮券确认
        'freeShipping_confirm' => array(
            'service_name' => 'voucher',
            'api'          => '/Voucher/FreeShipping/FinishOrderForCoupon',
            'version'      => 'v1'
        ) ,
        // 锁定免税券 lockDutyFree
        'dutyFree_lock' => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/DutyFree/CreateOrderForCoupons',
            'version'      => 'v1'
        ) ,
        // 取消已锁定免税券
        'dutyFree_cancel' => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/DutyFree/CancelOrderForCoupon',
            'version'      => 'v1'
        ) ,
        // 确认使用的免税券
        'dutyFree_confirm' => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/DutyFree/FinishOrderForCoupon',
            'version'      => 'v1'
        ) ,
        // 结算支付
        'settlement_orderPay' => array(
            'service_name' => 'settlement',
            'api'          => 'Settlement/Channel/OrderPay',
            'version'      => 'v1'
        ) ,
        // 保存拆单信息
        'order_split_create' =>array(
            'service_name' => 'order_split',
            'api' => 'OrderSplit/Create' ,
            'version'      => 'v1'
        ) ,
        // 创建订单
        'order_create' =>array(
            'service_name' => 'order',
            'api' => 'Order/Create' ,
            'version'      => 'v1'
        ) ,
        //
        'invoice_applyBatch' =>array(
            'service_name' => 'order',
            'api' => 'Order/Invoice/PreApplyBatch' ,
            'version'      => 'v1'
        ) ,
         // 释放资源
        'resource_release' => array(
            'service_name' => 'resource',
            'api' => 'Resource/Release' ,
            'version'      => 'v1'
        ) ,
        // 解锁运营活动
        'promotion_TimeBuy_UnLock' => array(
            'service_name' => 'promotion',
            'api' => 'Promotion/TimeBuy/UnLock' ,
            'version'      => 'v1'
        ) ,
        // 取消记录
        'credit_record_cancel' => array(
            'service_name' => 'credit',
            'api' => 'Credit/Bill/CancelRecord' ,
            'version'      => 'v1'
        ) ,
        // 归还信用额度
        'credit_bill_reply' => array(
            'service_name' => 'credit',
            'api' => 'Credit/Bill/Reply' ,
            'version'      => 'v1'
        ) ,
        //  Credit/Bill/Record
        'credit_bill_record' => array(
            'service_name' => 'credit',
            'api' => 'Credit/Bill/Record' ,
            'version'      => 'v1'
        ) ,

        // 'settlement', 'Settlement/Channel/OrderRefund', 'v1'
        'settlement_orderRefund' => array(
            'service_name' => 'settlement',
            'api' => 'Settlement/Channel/OrderRefund' ,
            'version'      => 'v1'
        ),
        'orderId_create' => array(
            'service_name' => 'order',
            'api' => 'OrderId/Create' ,
            'version'      => 'v1'
        ) ,
        'cat_tree_list' => array(
            'service_name' => 'search',
            'api' =>  'Cat/GetTreeList' ,
            'version'      => 'v3'
        ) ,
        'brand_list' => array(
            'service_name' => 'search',
            'api' =>  'Brand/SearchList' ,
            'version'      => 'v3'
        ) ,
        'order_update'  => array(
            'service_name' => 'order',
            'api' =>  'Order/Update' ,
            'version'      => 'v1'
        ) ,
        'promotion_get' => array(
            'service_name' => 'promotion',
            'api' => 'Promotion/Get' ,
            'version'      => 'v3'
        ),
        'get_product_info' => array(
            'service_name' => 'goods',
            'api'          => 'Product/Get',
            'version'      => 'v3'
        ),
        'get_shop_list' => array(
            'service_name' => 'goods',
            'api'          => 'Shop/GetList',
            'version'      => 'v3'
        ),
        // 解锁运营活动：售后商品库存
        'promotion_timeBuy_afterSaleUnLock' => array(
            'service_name' => 'promotion',
            'api' => 'Promotion/TimeBuy/AfterSaleUnLock' ,
            'version'      => 'v1'
        ) ,
        // 解锁运营活动：支付后取消商品库存
        'promotion_timeBuy_payedCancelUnLock' => array(
            'service_name' => 'promotion',
            'api' => 'Promotion/TimeBuy/PayedCancelUnLock' ,
            'version'      => 'v1'
        ) ,
        // 定价服务
        "create_pricing"  => array(
            'service_name' => 'price',
            'api'          => 'Price/createPricing',
            'version'      => 'v3'
        ) ,
        // 获取货品列表
        "get_product_list"  => array(
            'service_name' => 'goods',
            'api'          => 'Product/GetList',
            'version'      => 'v3'
        ) ,
);

    /*
     * 服务请求
     */
    public function ServiceCall($service_name, $send_data, $version = null, $uploads = [], $post = [])
    {
        $service_config = $this->_service_config[$service_name];
        $version        = is_null($version) ? $service_config['version'] : $version;
        $service_api    = $service_config['api'];
        $dispatcher     = app(Dispatcher::class);
        $start_time = microtime(true) ;
        $dispatcher->cookie(app('request')->cookie());
        //支持文件上传
        if (!empty($uploads) && is_array($uploads)) {
            foreach ($uploads as $k => $v) {
                $dispatcher->Uploads($k, $v);
            }
        }
        $response = $dispatcher->post('/' . $version . '/' . $service_api, json_encode($send_data), $post);
        $end_time = microtime(true) ;
//        \Neigou\Logger::Debug('service_servicecall', [
//            'action'  => '/' . $version . '/' . $service_api,
//            'service_name' => $service_name ,
//            'sparam1' => json_encode($send_data),
//            'sparam2' => json_encode($post),
//            'sparam3' => $response->getContent() ,
//            'sparam4' => round($end_time - $start_time , 4) ,
//        ]);

        return json_decode($response->getContent(), true);
    }

}
