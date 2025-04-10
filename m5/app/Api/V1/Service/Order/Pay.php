<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Model\Order\OrderLog as OrderLog;
use App\Api\Model\Order\OrderPayLog;
use App\Api\Logic\Mq as Mq;
use App\Api\Logic\Service as Service;


/*
 * @todo 订单支付
 */

class Pay
{
    private $_mq_channel_name = 'order_pay';

    /*
     * @todo 进行订单支付
     */
    public function DoPay($pay_order_data)
    {
        //日志记录
        $funcgion_log = function ($method_name, $success, $msg) use ($pay_order_data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($pay_order_data),
                'bn' => $pay_order_data['order_id'],
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_dopay', $log_array);
        };
        //检查订单数据
        $cherck_res = $this->CheckPayOrder($pay_order_data, $msg);
        if (!$cherck_res) {
            //发送消息
            Mq::OrderPay($pay_order_data['order_id']);
            $funcgion_log('order_check', false, $msg);
            return $this->Response(400, $msg);
        }

        // 检查资产状态
        $service_logic = new Service();
        $result = $service_logic->ServiceCall('asset_get', array('use_type' => 'ORDER', 'use_obj' => $pay_order_data['order_id'], 'sync_query' => 1));
        if ($result['error_code'] != 'SUCCESS' OR empty($result['data']) OR ! in_array($result['data']['status'], array(1, 3)))
        {
            $funcgion_log('asset_check', false, json_encode($result));
        }

        $update_order_data = [
            'order_id' => $pay_order_data['order_id'],
            'payment' => $pay_order_data['pay_name'],
            'pay_money' => $pay_order_data['pay_money'],
        ];
        //数据保存
        $res = Order::OrderPay($update_order_data);
        if (!$res) {
            //发送消息
            Mq::OrderPay($pay_order_data['order_id']);
            $funcgion_log('pay_order_fail', false, '订单支付失败');
            return $this->Response(400, '支付失败');
        }
        //保存日志
        $this->SaveLog($pay_order_data);
        //发送消息
        Mq::OrderPay($pay_order_data['order_id']);
        return $this->Response(200, '支付成功', []);
    }

    /*
     * @todo 检查订单
     */
    private function CheckPayOrder($pay_order_data, &$msg)
    {
        if (empty($pay_order_data['order_id'])) {
            $msg = '订单支付错误';
            return false;
        }
        //检查支付方式
        if (empty($pay_order_data['pay_name']) || empty($pay_order_data['trade_no'])) {
            $msg = '支付方式错误';
            return false;
        }
        if (!isset($pay_order_data['pay_money'])) {
            $msg = '支付金额错误';
            return false;
        }
        //订单信息
        $order_info = Order::GetOrderInfoById($pay_order_data['order_id']);
        if (empty($order_info)) {
            $msg = '订单不存在';
            return false;
        }
        if ($order_info->pay_status != 1) {
            $msg = '订单支付状态错误';
            return false;
        }
        if ($order_info->pid != 0 || $order_info->create_source != 'main') {
            $msg = '非主订单不能为进行支付';
            return false;
        }
        if ($order_info->cur_money != $pay_order_data['pay_money']) {
            $msg = '订单支付金额错误';
            return false;
        }
        return true;
    }

    /*
     * @todo 发送支付消息
     */
    protected function SendMessage($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $order_info = Order::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        $send_data = [
            'order_id' => $order_info->order_id,
            'member_id' => $order_info->member_id,
            'company_id' => $order_info->company_id,
            'time' => time(),
        ];
        $res = $mq->PublishFanoutMessage($this->_mq_channel_name, $send_data);
        return $res;
    }

    /*
     * @todo 保存日志
     */
    protected function SaveLog($pay_order_data)
    {
        //保存订单操作日志
        $log_data = [
            'order_id' => $pay_order_data['order_id'],
            'title' => '订单支付成功',
            'content' => json_encode($pay_order_data),
            'create_time' => time(),
            'type' => OrderLog::LOG_TYPE_PAY
        ];
        OrderLog::SaveLog($log_data);
        //保存支付日志
        $extend_data = json_decode($pay_order_data['extend_data'], true);
        $extend_data['pay_money'] = $pay_order_data['pay_money'];
        $extend_data['pay_time'] = $pay_order_data['pay_time'];
        $log_data = [
            'order_id' => $pay_order_data['order_id'],
            'pay_name' => $pay_order_data['pay_name'],
            'trade_no' => $pay_order_data['trade_no'],
            'payment_id' => $pay_order_data['payment_id'],
            'payment_system' => $pay_order_data['payment_system'],
            'create_time' => time(),
            'extend_data' => json_encode($extend_data),
        ];
        OrderPayLog::SaveLog($log_data);
    }

    private function Response($error_code, $error_msg, $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }

}
