<?php

namespace App\Api\Logic\ExceptionMsg;

use App\Api\Logic\Service as Service;
use App\Api\Model\Express\V2\Express;

class AfterSaleException
{
    //判断售后单超时未处理【超时未审核】
    public static function TimeOutApplyPassCheckIn($after_info)
    {
        //2024-03-29 由24小时改成12小时 12 * 3600 = 43200；2024-04-10 判断依据有更新时间修改为创建时间
        if ($after_info['create_time'] < (time() - 43200) && $after_info['status'] == 1) {
            return true;
        }
        return false;
    }

    //判断售后超时未处理-删除
    public static function TimeOutApplyPassCheckOut( $msg_info )
    {
        $after_info = self::getAfterSaleInfo( $msg_info['data_id'] );
        if ( empty( $after_info ) )
        {
            return false;
        }
        return !self::TimeOutApplyPassCheckIn( $after_info );
    }

    // 判断售后单是否超时未寄回
    public static function TimeOutShipCheckIn($after_info)
    {
        if ($after_info['update_time'] < (time() - 2 * 24 * 3600) && $after_info['status'] == 2) {
            return true;
        }
        return false;
    }

    // 判断售后单是否 删除 超时未寄回
    public static function TimeOutShipCheckOut($msg_info)
    {
        $after_info = self::getAfterSaleInfo($msg_info['data_id']);
        if (empty($after_info)) {
            return false;
        }
        return !self::TimeOutShipCheckIn($after_info);
    }

    // 判断售后单是否超时寄回未入库
    public static function TimeOutPutInCheckIn($after_info)
    {
        $max_time = 259200;//3*24*60*60; 签收后72小时
        $send_back_max_time = 604800;//7*24*3600 寄回7✖️24小时后 2024-03-29 添加
        if ($after_info['status'] != 4) {
            return false;
        }
        $time = time();
        //获取物流信息，express_get 升级 express_get_v4 2024-03-29
        $service_logic = new Service();
        $express_info = $service_logic->ServiceCall('express_get_v4', ['express_com' => $after_info['express_code'], 'express_no' => $after_info['express_no']]);

        if ($express_info['data']) {
            if (empty($express_info['data']['content'][0]['time'])) {
                return false;
            }
            $express_diff_time = $time - strtotime($express_info['data']['content'][0]['time']);
            if ($express_info['data']['status'] == 3 && $express_diff_time > $max_time) {
                return true;
            }
            if (in_array($express_info['data']['status'], [
                    Express::STATUS_COLLECT,// 1 揽收
                    Express::STATUS_UNDERWAY,// 2 在途
                    Express::STATUS_DELIVERY,// 5 派送中
                    Express::STATUS_FORWARDING,// 7 转投
                    Express::STATUS_CUSTOMS_CLEARANCE,// 8 清关
                ]) && $express_diff_time > $send_back_max_time) {
                return true;
            }
        }
        return false;
    }

    // 判断售后单是否超时寄回未入库
    public static function TimeOutPutInCheckOut($msg_info)
    {
        $after_info = self::getAfterSaleInfo($msg_info['data_id']);
        return !self::TimeOutPutInCheckIn($after_info);
    }

    // 判断是否超时未退款
    public static function TimeOutRefundCheckIn($after_info)
    {
        // 判断是否超时未退款
        if ($after_info['status'] == 10 && (time() - $after_info['update_time']) > (48 * 60 * 60)) {
            return true;
        }
        return false;
    }

    // 判断是否超时未退款 删除
    public static function TimeOutRefundCheckOut($msg_info)
    {
        $after_info = self::getAfterSaleInfo($msg_info['data_id']);
        return !self::TimeOutRefundCheckIn($after_info);
    }

    public static function GetListForException($page = 1, $page_size = 10)
    {
        $service_logic = new Service();
        $pars = array(
            'filter_data' => [
                'status' => array('type' => 'in', 'value' => [1, 2, 4, 10]),
            ],
            'page' => $page,
            'limit' => $page_size,
            'order_data' => ['create_time' => 'asc']
        );
        $ret = $service_logic->ServiceCall('aftersale_getlist_v2', $pars, 'v2');
        $return = [];
        foreach ($ret['data']['after_sale_list'] as $item) {
            $return[$item['after_sale_bn']] = $item;
        }
        return $return;
    }

    public static function getAfterSaleInfo($after_sale_bn)
    {
        $service_logic = new Service();
        $res = $service_logic->ServiceCall('aftersale_get_v2', [
            'filter_data' => [
                'after_sale_bn' => [
                    'type' => 'eq',
                    'value' => $after_sale_bn
                ]
            ]
        ]);
        return $res['data'];
    }
}
