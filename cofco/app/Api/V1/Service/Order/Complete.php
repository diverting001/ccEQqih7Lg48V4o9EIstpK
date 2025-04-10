<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Logic\Mq as Mq;


/*
 * @todo 订单完成
 */

class Complete
{
    public function CompleteOrderByOrderId($order_id, &$msg = '')
    {
        if (empty($order_id)) {
            return false;
        }
        $order_info = Order::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $log_array = array(
                'action' => 'order.complete',
                'success' => 0,
                'data' => json_encode($order_id),
                'bn' => $order_id,
                'remark' => '订单不存在',
            );
            \Neigou\Logger::General('service_order_complete_err', $log_array);
            $msg = '订单不存在';
            return false;
        }
        $pay_status = $order_info->pay_status;
        $status = $order_info->status;
        $confirm_status = $order_info->confirm_status;
        $ship_status = $order_info->ship_status;

        if ($pay_status == 2 && $status == 1 && $confirm_status == 2) {
            $where = [
                'order_id' => $order_id,
                'status' => 1,
                'pay_status' => 2,
            ];
            $update_order_data = [
                'status' => 3,
                'finish_time' => time()
            ];
            if ($ship_status == 1) {
                $update_order_data['ship_status'] = 2;
            }

            $res = Order::OrderUpdate($where, $update_order_data);
            if ($res === false) {
                $log_array = array(
                    'action' => 'order.complete',
                    'success' => 0,
                    'data' => json_encode($where),
                    'bn' => $order_id,
                    'remark' => '订单设置失败',
                );
                \Neigou\Logger::General('service_order_complete_err', $log_array);
                $msg = '订单设置失败';
                return false;
            }
        } else {
            $log_array = array(
                'action' => 'order.complete',
                'success' => 0,
                'data' => json_encode($order_id),
                'bn' => $order_id,
                'remark' => '订单状态错误',
            );
            \Neigou\Logger::General('service_order_complete_err', $log_array);
            $msg = '订单状态错误';
            return false;
        }

        if ($ship_status == 1) {
            Mq::OrderDelivery($order_id);
        }

        Mq::OrderFinish($order_id);
        $msg = '';
        return true;
    }
}
