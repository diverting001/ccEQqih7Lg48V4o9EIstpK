<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Model\Order\OrderLog as OrderLog;
use App\Api\Model\Order\OrderRefundLog;
use App\Api\Logic\Mq as Mq;


/*
 * @todo 订单退款
 */

class Refund
{
    private $_mq_channel_name = 'order_refund';

    /*
     * @todo 订单退款申请
     */
    public function RefundApply($pay_order_data)
    {

    }

    /*
     * @todo 检查订单
     */
    private function CheckRefundApplyOrder($refund_order_data, &$msg)
    {
//        if(empty($refund_order_data['order_id'])){
//            $msg    = '订单退款错误';
//            return false;
//        }
//        //订单信息
//        $order_info = Order::GetOrderInfoById($refund_order_data['order_id']);
//        if(empty($order_info)){
//            $msg    = '订单不存在';
//            return false;
//        }
//        if($order_info->pay_status != 2){
//            $msg = '订单支付状态错误';
//            return false;
//        }
//        if($order_info->pid != 0 || $order_info->create_source !='main'){
//            $msg = '非主订单不能为进行支付';
//            return false;
//        }
//        if($refund_order_data['money']){
//            $msg = '订单退款金额不能为空';
//            return false;
//        }
//        return true;
    }


    /*
     * @todo 订单退款确认
     */
    public function RefundConfirm($reund_order_data)
    {
        //日志记录
        $funcgion_log = function ($method_name, $success, $msg) use ($reund_order_data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($reund_order_data),
                'bn' => $reund_order_data['order_id'],
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_refund_confirm', $log_array);
        };
        //检查订单数据
        $msg = '';
        $cherck_res = $this->CheckRefundConfirm($reund_order_data, $msg);
        if (!$cherck_res) {
            //发送消息
            $funcgion_log('order_check', false, $msg);
            return $this->Response(400, $msg);
        }
        $update_order_data = [
            'order_id' => $reund_order_data['order_id'],
        ];
        //数据保存
        $res = Order::OrderRefundConfirm($update_order_data);
        if (!$res) {
            $funcgion_log('refund_confirm_order_fail', false, '订单退款确认失败');
            return $this->Response(400, '支付失败');
        }
        //保存日志
        $this->SaveLog($reund_order_data);
        //发送消息
        Mq::OrderRefund($reund_order_data['order_id']);
        return $this->Response(200, '支付成功', []);
    }

    /*
     * @todo 检查订单
     */
    private function CheckRefundConfirm($refund_order_data, &$msg)
    {
        if (empty($refund_order_data['order_id'])) {
            $msg = '订单退款确认错误';
            return false;
        }
        //订单信息
        $order_info = Order::GetOrderInfoById($refund_order_data['order_id']);
        if (empty($order_info)) {
            $msg = '订单不存在';
            return false;
        }
        if ($order_info->pay_status != 2) {
            $msg = '订单支付状态错误';
            return false;
        }
        if ($order_info->confirm_status != 1) {
            $msg = '已确认订单不能进行退款';
            return false;
        }
        if ($order_info->pid != 0 || $order_info->create_source != 'main') {
            $msg = '非主订单不能为进行支付';
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
    protected function SaveLog($refund_order_data)
    {
        //保存订单操作日志
        $log_data = [
            'order_id' => $refund_order_data['order_id'],
            'title' => '订单退款确认成功',
            'content' => json_encode($refund_order_data),
            'create_time' => time()
        ];
        OrderLog::SaveLog($log_data);
        //保存支付日志
        $extend_data = json_decode($refund_order_data['extend_data'], true);
        $extend_data['refund_money'] = $refund_order_data['refund_money'];
        $extend_data['refund_time'] = $refund_order_data['refund_time'];
        $log_data = [
            'order_id' => $refund_order_data['order_id'],
            'refund_name' => $refund_order_data['refund_name'],
            'trade_no' => $refund_order_data['trade_no'],
            'refund_id' => $refund_order_data['refund_id'],
            'refund_system' => $refund_order_data['refund_system'],
            'create_time' => time(),
            'extend_data' => json_encode($extend_data),
        ];
        OrderRefundLog::SaveLog($log_data);
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
