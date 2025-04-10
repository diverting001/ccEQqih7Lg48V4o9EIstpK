<?php

namespace App\Api\Logic\Point;
use App\Api\Model\Point\Point as PointModel;

class Config
{
    //第三方积分平台配置
    private static $point_source_config = [
        'NEIGOU'  => [
            'class'                     => 'App\Api\Logic\Point\Point\DefaultPoint',
            'get_member_point_uri'      => '/openapi/point/get',  //获取用户积分接口
            'lock_member_point_uri'     => '/openapi/point/lock',  //锁定用户积分接口
            'cancel_member_point_uri'   => '/openapi/point/unlock',  //取消锁定用户积分接口
            'confirm_member_point_uri'  => '/openapi/point/confirm',  //确认锁定用户积分接口
            'get_lock_member_point_uri' => '/openapi/point/orders',  //查询锁定用户积分接口
            'refund_member_point_uri'   => '/openapi/point/refund',  //返还锁定用户积分接口
            'member_record_uri'         => '/openapi/point/getMemberRecordList',  //获取用户积分记录
            'get_point_name'            => '/openapi/point/getPointName',  //获取channel积分名称
            'channel'                  => ''   //原始渠道
        ],
        'OPENAPI' => [
            'class'                    => 'App\Api\Logic\Point\Point\OpenapiPoint',
            'get_member_point_uri'     => '/ChannelInterop/V1/Standard/ThirdPoint/getUserPoint',  //获取用户积分接口
            'lock_member_point_uri'    => '/ChannelInterop/V1/Standard/ThirdPoint/lockPointOrder',  //锁定用户积分接口
            'cancel_member_point_uri'  => '/ChannelInterop/V1/Standard/ThirdPoint/unlockPointOrder',  //取消锁定用户积分接口
            'confirm_member_point_uri' => '/ChannelInterop/V1/Standard/ThirdPoint/deductPointOrder',  //确认锁定用户积分接口
            'refund_member_point_uri'  => '/ChannelInterop/V1/Standard/ThirdPoint/refundPointOrder',  //返还锁定用户积分接口
            'get_point_ratio'          => '/ChannelInterop/V1/Standard/ThirdPoint/getUserRatio',  //积分比例查询
            'channel'                  => ''   //原始渠道
        ],
        'JIUGANG' => [
            'class'                     => 'App\Api\Logic\Point\Point\DefaultPoint',
            'get_member_point_uri'      => '/openapi/jiugangPoint/get',  //获取用户积分接口
            'lock_member_point_uri'     => '/openapi/jiugangPoint/lock',  //锁定用户积分接口
            'cancel_member_point_uri'   => '/openapi/jiugangPoint/unlock',  //取消锁定用户积分接口
            'confirm_member_point_uri'  => '/openapi/jiugangPoint/confirm',  //确认锁定用户积分接口
            'get_lock_member_point_uri' => '/openapi/jiugangPoint/orders',  //查询锁定用户积分接口
            'refund_member_point_uri'   => '/openapi/jiugangPoint/refund',  //返还锁定用户积分接口
            'member_record_uri'         => '/openapi/jiugangPoint/getMemberRecordList',  //获取用户积分记录
            'channel'                  => ''   //原始渠道
        ],
        'ADAPTER' => [ //适配层, 增加积分兑换比例
            'class'                     => 'App\Api\Logic\Point\Point\AdapterPoint',
            'get_member_point_uri'      => '/openapi/adapterPoint/adapterGet',  //获取用户积分接口
            'lock_member_point_uri'     => '/openapi/adapterPoint/adapterLock',  //锁定用户积分接口
            'cancel_member_point_uri'   => '/openapi/adapterPoint/adapterUnlock',  //取消锁定用户积分接口
            'confirm_member_point_uri'  => '/openapi/adapterPoint/adapterConfirm',  //确认锁定用户积分接口
            'get_lock_member_point_uri' => '/openapi/adapterPoint/adapterOrders',  //查询锁定用户积分接口
            'refund_member_point_uri'   => '/openapi/adapterPoint/adapterRefund',  //返还锁定用户积分接口
            'member_record_uri'         => '/openapi/adapterPoint/adapterGetMemberRecordList',  //获取用户积分记录
            'get_point_name'            => '/openapi/adapterPoint/adapterGetPointName',  //获取channel积分名称
            'channel'                   => ''   //原始渠道
        ],

    ];


    /*
     * @todo 第三方积分平台配置
     */
    public static function GetPointSourceConfig($channel)
    {
        $adapterType = PointModel::GetAdapterTypeByChannel($channel);
        if (!isset(self::$point_source_config[$adapterType->adapter_type])) return false;
        self::$point_source_config[$adapterType->adapter_type]['channel'] = $channel;
        return self::$point_source_config[$adapterType->adapter_type];
    }

}
