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
        'get_freight'                    => array(
            'service_name' => 'delivery',
            'api'          => 'Delivery/Freight',
            'version'      => 'v1'
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
        'voucher_with_rule'              => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/GetWithRule',
            'version'      => 'v1'
        ),
        'dutyfree_with_rule'             => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/DutyFree/GetCouponWithRule',
            'version'      => 'v2'
        ),
        'freeshipping_with_rule'         => array(
            'service_name' => 'voucher',
            'api'          => 'Voucher/Freeshipping/GetCouponWithRule',
            'version'      => 'v2'
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
        'express_get'                    => array(
            'service_name' => 'tools',
            'api'          => 'Express/Get',
            'version'      => 'v3'
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

        $dispatcher->cookie(app('request')->cookie());
        //支持文件上传
        if (!empty($uploads) && is_array($uploads)) {
            foreach ($uploads as $k => $v) {
                $dispatcher->Uploads($k, $v);
            }
        }
        $response = $dispatcher->post('/' . $version . '/' . $service_api, json_encode($send_data), $post);

        \Neigou\Logger::Debug('service_servicecall', [
            'action'  => '/' . $version . '/' . $service_api,
            'sparam1' => json_encode($send_data),
            'sparam2' => json_encode($post),
            'sparam3' => $response->getContent()
        ]);

        return json_decode($response->getContent(), true);
    }

}
