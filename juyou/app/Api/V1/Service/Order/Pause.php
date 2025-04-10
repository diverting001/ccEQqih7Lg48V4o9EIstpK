<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Logic\Mq as Mq;

class Pause
{
    /*
     * @todo 订单暂停by发货单号
     */
    public function PauseByWmsDeliveryBn($order_id, $wms_delivery_bn, $wms_code, $need_check = true)
    {
        $data = [
            'order_id' => $order_id,
            'wms_order_bn' => $wms_delivery_bn,
            'wms_code' => $wms_code,
        ];
        $funcgion_log = function ($method_name, $success, $msg) use ($order_id, $data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($data),
                'bn' => $order_id,
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_pause_wms_delivery_bn', $log_array);
        };
        $msg = '';
        //检查订单数据
        if ($need_check) {
            $cherck_res = $this->CheckPauseOrder($order_id, $msg);
            if (!$cherck_res) {
                $funcgion_log('order_check', false, $msg);
                return $this->Response(400, $msg);
            }
        }

        $class_name = 'App\\Api\\Logic\\OrderWMS\\' . ucfirst(strtolower($wms_code));
        if (!class_exists($class_name)) {
            $msg = '处理类不存在';
            return $this->Response(401, $msg);
        }
        $class_obj = new $class_name();

        $ret = $class_obj->CancelOrderByWmsDeliveryBn($wms_delivery_bn);
        if ($ret == false) {
            return $this->Response(402, '取消订单失败');
        }

        return $this->Response(200, '成功');
    }

    /*
    * @todo 订单暂停by履约单号
    */
    public function PauseByWmsOrderBn($order_id, $wms_order_bn, $wms_code, $need_check = true)
    {
        //日志记录
        $data = [
            'order_id' => $order_id,
            'wms_order_bn' => $wms_order_bn,
            'wms_code' => $wms_code,
        ];
        $funcgion_log = function ($method_name, $success, $msg) use ($order_id, $data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($data),
                'bn' => $order_id,
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_pause_wms_order_bn', $log_array);
        };
        $msg = '';
        if ($need_check) {
            //检查订单数据
            $cherck_res = $this->CheckPauseOrder($order_id, $msg);
            if (!$cherck_res) {
                $funcgion_log('order_check', false, $msg);
                return $this->Response(400, $msg);
            }
        }

        $class_name = 'App\\Api\\Logic\\OrderWMS\\' . ucfirst(strtolower($wms_code));
        if (!class_exists($class_name)) {
            $msg = '处理类不存在';
            return $this->Response(401, $msg);
        }
        $class_obj = new $class_name();

        $ret = $class_obj->CancelOrderByWmsOrderBn($wms_order_bn);
        if ($ret == false) {
            return $this->Response(402, '取消订单失败');
        }

        return $this->Response(200, '成功');
    }

    public function CheckPauseOrder($order_id, &$msg)
    {
        //订单信息
        $order_info = Order::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $msg = '订单不存在';
            return false;
        }
        if ($order_info->hung_up_status != 3) {
            $msg = '订单未被锁定,取消履约平台订单终止处理';
            return false;
        }
        return true;
    }


    /**
     * 暂停订单重新履约
     */
    public function RetryWms($order_id, &$msg = '')
    {
        //获取订单信息
        $order_info = Order::GetOrderInfoById($order_id);
        if (empty($order_info)) {
            $msg = '订单信息未找到';
            return false;
        }

        if ($order_info->pay_status != 2) {
            $msg = '订单未支付';
            return false;
        }

        if ($order_info->status == 3) {
            $msg = '订单已完成';
            return false;
        } elseif ($order_info->status == 2) {
            $msg = '订单已取消';
            return false;
        } elseif ($order_info->hung_up_status != 1) {
            $msg = '非暂停订单';
            return false;
        } elseif ($order_info->hung_up_status == 3) {
            $msg = '订单处理中';
            return false;
        } else {
            //
        }

        $current_order_id = $order_info->order_id;
        $is_locked = Order::LockOrderByIds([$current_order_id]);
        if (!$is_locked) {
            $msg = '锁定订单失败';
            goto RET_FAIL;
        }

        $hung_up_status = 3;
        $old_hung_up_status = 1;
        $ret = Order::UpdateOrderHungupStatus($order_id, $hung_up_status, $old_hung_up_status);
        if (!$ret) {
            $msg = '更新订单暂停状态未处理中失败';
            goto RET_FAIL_WITH_UNLOCK;
        }

        //如果是该订单已存在履约单号,清除掉
        if (!empty($order_info->wms_order_bn)) {
            $where = [
                'order_id' => $order_info->order_id,
            ];
            $update_data = [
                'wms_order_bn' => '',
            ];
            Order::OrderUpdate($where, $update_data);
        }

        $hung_up_status = 2;
        $old_hung_up_status = 3;
        $ret = Order::UpdateOrderHungupStatus($order_id, $hung_up_status, $old_hung_up_status);
        if ($ret) {
            //如果订单未确认,尝试确认
            if ($order_info->confirm_status == 1) {
                Order::OrderConfirm(['order_id' => $order_info->order_id]);
            }
            Mq::OrderConfirm($order_id);
            //重新履约,从拦截视图删除
            Order::delInterceptOrder($order_id);
            goto RET_SUCC_WITH_UNLOCK;
        }

        RET_SUCC_WITH_UNLOCK:
        Order::UnLockOrderByIds([$current_order_id]);
        return true;
        RET_FAIL_WITH_UNLOCK:
        Order::UnLockOrderByIds([$current_order_id]);
        return false;
        RET_FAIL:
        return false;
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
