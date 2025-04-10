<?php

namespace App\Api\Logic\Point;

use App\Api\Model\Point\Point as PointModel;

class SceneConfig
{
    //第三方积分平台配置
    private static $point_source_config = [
        'SCENENEIGOU' => [
            'class'                           => 'App\Api\Logic\Point\ScenePoint\AdapterPoint',
            'get_member_point_uri'            => 'get_member_scene_point',  //获取用户积分接口
            'lock_member_point_uri'           => 'lock_member_scene_point',  //锁定用户积分接口
            'cancel_member_point_uri'         => 'cancel_member_scene_point',  //取消锁定用户积分接口
            'confirm_member_point_uri'        => 'confirm_member_scene_point',  //确认锁定用户积分接口
            'refund_member_point_uri'         => 'refund_member_scene_point',  //返还锁定用户积分接口
            'member_record_uri'               => 'member_scene_point_record',  //获取用户积分记录
            'get_member_point_by_overdue_uri' => 'get_member_scene_point_overdue',

            'get_company_point_uri'        => 'get_company_scene_point',  //获取公司积分接口
            'get_company_point_record_uri' => 'get_company_scene_point_record',  //获取公司积分接口
            'lock_company_point_uri'       => 'lock_company_scene_point',  //锁定公司积分接口
            'un_lock_company_point_uri'    => 'un_lock_company_scene_point',
            'company_assign_point_uri'     => 'company_assign_point',  //公司发放积分

            'get_lock_member_point_uri' => '',  //查询锁定用户积分接口
            'get_point_name'            => '',  //获取channel积分名称
            'channel'                   => ''   //原始渠道
        ],

        'ACCOUNT_TRUSTEESHIP' => [
            'class'                    => 'App\Api\Logic\Point\ScenePoint\AccountTrusteeshipPoint',
            'get_member_point_uri'     => '/openapi/trusteeshipPoint/Get',  //获取用户积分接口
            'lock_member_point_uri'    => '/openapi/trusteeshipPoint/Lock',  //锁定用户积分接口
            'cancel_member_point_uri'  => '/openapi/trusteeshipPoint/CancelLock',  //取消锁定用户积分接口
            'confirm_member_point_uri' => '/openapi/trusteeshipPoint/ConfirmLock',  //确认锁定用户积分接口
            'refund_member_point_uri'  => '/openapi/trusteeshipPoint/Refund',  //返还锁定用户积分接口
        ],

        'ACCOUNT_ADAPTER' => [
            'class'                    => 'App\Api\Logic\Point\ScenePoint\AccountAdapterPoint',
            'get_member_point_uri'     => 'scene_point_v3_get',  //获取用户积分接口
            'lock_member_point_uri'    => 'scene_point_v3_lock',  //锁定用户积分接口
            'cancel_member_point_uri'  => 'scene_point_v3_cancel',  //取消锁定用户积分接口
            'confirm_member_point_uri' => 'scene_point_v3_confirm',  //确认锁定用户积分接口
            'refund_member_point_uri'  => 'scene_point_v3_refund',  //返还锁定用户积分接口
            'member_record_uri'        => 'scene_point_v3_get_record',  //返还锁定用户积分接口
        ],

        'OPENAPI' => [
            'class'                    => 'App\Api\Logic\Point\ScenePoint\OpenapiPoint',
            'get_member_point_uri'     => '/ChannelInterop/V1/Standard/ThirdScenePoint/getUserPoint',  //获取用户积分接口
            'lock_member_point_uri'    => '/ChannelInterop/V1/Standard/ThirdScenePoint/lockUserPoint',  //锁定用户积分接口
            'cancel_member_point_uri'  => '/ChannelInterop/V1/Standard/ThirdScenePoint/unlockUserPoint',  //取消锁定用户积分接口
            'confirm_member_point_uri' => '/ChannelInterop/V1/Standard/ThirdScenePoint/confirmUserPoint',  //确认锁定用户积分接口
            'refund_member_point_uri'  => '/ChannelInterop/V1/Standard/ThirdScenePoint/refundUserPoint',  //返还锁定用户积分接口
            'member_record_uri'        => '/ChannelInterop/V1/Standard/ThirdScenePoint/getUserRecord',  //返还锁定用户积分接口
        ],

    ];


    /*
     * @todo 第三方积分平台配置
     */
    public static function GetPointSourceConfig($channel)
    {
        $adapterType = PointModel::GetAdapterTypeByChannel($channel);
        if (!isset(self::$point_source_config[$adapterType->adapter_type])) {
            return false;
        }
        self::$point_source_config[$adapterType->adapter_type]['channel'] = $channel;
        return self::$point_source_config[$adapterType->adapter_type];
    }

}
