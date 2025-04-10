<?php


namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\OrderLog;
use App\Api\Logic\Mq as Mq;

class OrderChangeLog
{

    public function getLogs($params)
    {

        return (new OrderLog())->getLogs($params);
    }

    /**
     * Notes:处理订单发货，物流变更日志存储
     * User: mazhenkang
     * Date: 2022/9/8 9:48
     * @param $data
     */
    public function saveOrderLog($data)
    {
        if (empty($data)) {
            return false;
        }
        foreach ($data as $dataInfo) {
            $log_data  = [];
            $save_data = $dataInfo['save_data'] ?: []; //物流单号等信息
            $order     = $dataInfo['order'] ?: []; //订单信息

            if (empty($save_data) || empty($order)) {
                continue;
            }
            if (!isset($save_data['logi_name']) || empty($save_data['logi_name']) || !isset($save_data['logi_no']) || empty($save_data['logi_no']) || !isset($save_data['logi_code']) || empty($save_data['logi_code'])) {
                continue;
            }

            if (empty($order['logi_name']) && empty($order['logi_no']) && empty($order['logi_code'])) { //发货
                //保存订单操作日志
                $log_data = [
                    'order_id'    => $order['order_id'],
                    'title'       => '订单发货',
                    'content'     => json_encode($save_data),
                    'create_time' => time(),
                    'type'        => OrderLog::LOG_TYPE_DELIVERY,
                ];
            } else { //物流修改
                if ($save_data['logi_name'] == $order['logi_name'] && $save_data['logi_no'] == $order['logi_no'] && $save_data['logi_code'] == $order['logi_code']) {
                    continue;
                }
                $log_data = [
                    'order_id'    => $order['order_id'],
                    'title'       => '物流信息变更',
                    'content'     => json_encode($save_data),
                    'create_time' => time(),
                    'type'        => OrderLog::LOG_TYPE_DELIVERY_CHANGE,
                ];
                //发送物流变更消息
                Mq::LogisticsChange($order['order_id']);
            }

            if (!empty($log_data)) {
                OrderLog::SaveLog($log_data);
            }
        }

        return true;
    }
}
