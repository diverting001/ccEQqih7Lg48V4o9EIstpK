<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Model\Order\OrderLog as OrderLog;
use App\Api\Logic\Mq as Mq;

/*
 * @todo 订单列表
 */

class Cancel
{

    /*
     * @todo 订单取消
     */
    public function Cancel($cancel_data)
    {
        //日志记录
        $funcgion_log = function ($method_name, $success, $msg) use ($cancel_data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($cancel_data),
                'bn' => $cancel_data['order_id'],
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_cancel', $log_array);
        };
        //检查订单数据
        $cherck_res = $this->CheckCancelOrder($cancel_data, $msg);
        if (!$cherck_res) {
            $funcgion_log('order_check', false, $msg);
            //发送消息
            Mq::OrderCancel($cancel_data['order_id']);
            return $this->Response(400, $msg);
        }
        //订单状态修改

        $res = Order::OrderCancel(['order_id' => $cancel_data['order_id']]);
        if (!$res) {

            $funcgion_log('pay_order_fail', false, '订单取消失败');
            return $this->Response(400, '取消失败');
        }
        //查询订单订单数据
        $order_list = Order::GetOrderListByRootPId($cancel_data['order_id']);
        foreach ($order_list as $v){
            //已支付订单发送订单支付后取消消息
            if($v->pay_status == 2 ){
                Mq::OrderPayedCancel($v->order_id);
            }
        }
        //发送消息
        Mq::OrderCancel($cancel_data['order_id']);
        return $this->Response(200, '成功');
    }

    /*
     * @todo 检查订单
     */
    private function CheckCancelOrder($pay_order_data, &$msg)
    {
        if (empty($pay_order_data['order_id'])) {
            $msg = '订单支付错误';
            return false;
        }
        //订单信息
        $order_info = Order::GetOrderInfoById($pay_order_data['order_id']);
        if (empty($order_info)) {
            $msg = '订单不存在';
            return false;
        }
        //子订单不可以单独取消
        if ($order_info->p_id != 0 || $order_info->create_source != 'main') {
            $msg = '子订单不可以单独取消';
            return false;
        }
        if ($order_info->status != 1 || $order_info->confirm_status != 1) {
            $msg = '订单不可以取消';
            return false;
        }
        return true;
    }


    /*
     * @todo 保存订单日志
     */
    protected function SaveLog($cancel_data)
    {
        //保存订单操作日志
        $log_data = [
            'order_id' => $cancel_data['order_id'],
            'title' => '订单取消成功',
            'content' => json_encode($cancel_data),
            'create_time' => time(),
            'type' => OrderLog::LOG_TYPE_CANCEL
        ];
        OrderLog::SaveLog($log_data);
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
