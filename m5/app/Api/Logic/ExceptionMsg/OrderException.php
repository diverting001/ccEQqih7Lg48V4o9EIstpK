<?php

namespace App\Api\Logic\ExceptionMsg;

use App\Api\Logic\Service as Service;
use App\Api\Model\Order\Order as OrderModel;

class OrderException
{
    // 判断订单是否超时发货
    public static function TimeOutShipCheckIn($order_info)
    {
        // 开始发货时间超过3天
        if ($order_info['pay_status'] == 2
            && $order_info['status'] == 1
            && $order_info['ship_status'] == 1
            && $order_info['pay_time'] <= time() - 3 * 86400
        ) {
            return true;
        }
        return false;
    }

    public static function TimeOutDayShipCheckIn($order_info)
    {
        // 开始发货时间超过24小时
        return $order_info['pay_status'] == 2
            && $order_info['status'] == 1
            && $order_info['ship_status'] == 1
            && $order_info['pay_time'] <= time() -  86400;
    }

    // 判断订单是否 可删除 超时发货
    public static function TimeOutShipCheckOut($msg_info)
    {
        $order_info = self::getOrderInfo($msg_info['data_id']);
        if (empty($order_info)) {
            return false;
        }
        if ($order_info['ship_status'] != 1) {
            return true;
        }
        // 已拆单则删除
        if ($order_info['split'] == 2) {
            return true;
        }
        // 订单取消或者完成则删除
        if (in_array($order_info['status'], array(2, 3))) {
            return true;
        }

        return false;
    }

    // 判断订单是否超时完成
    public static function TimeOutCompleteCheckIn($order_info)
    {
        // 配送时间超过7天
        if ($order_info['pay_status'] == 2
            && $order_info['status'] == 1
            && $order_info['ship_status'] == 2
            && $order_info['delivery_time'] <= time() - 7 * 86400
        ) {
            return true;
        }
        return false;
    }

    public static function TimeOutFiveCompleteCheckIn($order_info)
    {
        // 配送时间超过5天
        return $order_info['pay_status'] == 2
            && $order_info['status'] == 1
            && $order_info['ship_status'] == 2
            && $order_info['delivery_time'] <= time() - 5 * 86400;
    }

    // 判断订单是否 删除 超时完成
    public static function TimeOutCompleteCheckOut($msg_info)
    {
        $order_info = self::getOrderInfo($msg_info['data_id']);
        if (empty($order_info)) {
            return false;
        }
        // 订单状态已完成
        if ($order_info['status'] == 3) {
            return true;
        }
        return false;
    }

    public static function GetListForException($page = 1, $page_size = 10)
    {
        $service_logic = new Service();
        $pars = array(
            'filter' => [
                'create_time' => array(
                    'start_time' => time() - (86400 * 30), // 30天内的订单
                    'end_time' => time()
                ),
                'split' => 1,// 未拆分
                'pay_status' => 2,// 未拆分
                'status' => 1,// 正常订单
//                'order_id' => 202007161416358780
            ],
            'page_index' => $page,
            'page_size' => $page_size,
            'output_format' => 'valid_split_order',
            'order_by' => ['create_time' => 'asc']
        );
        $ret = $service_logic->ServiceCall('order_list', $pars, 'v2');
        $return = [];
        foreach ($ret['data']['order_list'] as $item) {
            if (!empty($item['split_orders'])) {
                foreach ($item['split_orders'] as $split_order_info) {
                    if ($split_order_info['split'] != 1) {
                        continue;
                    }
                    $return[$split_order_info['order_id']] = $split_order_info;
                }
                continue;
            }
            if ($item['split'] != 1) {
                continue;
            }
            $return[$item['order_id']] = $item;
        }
        return $return;
    }

    public static function getOrderInfo($order_id)
    {
        //检查订单是否存在
        $service_logic = new Service();
        $res = $service_logic->ServiceCall('order_info', ['order_id' => $order_id]);
        return $res['data'];
    }
}
