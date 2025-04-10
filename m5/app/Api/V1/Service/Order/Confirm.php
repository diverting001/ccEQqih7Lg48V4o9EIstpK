<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Order as Order;
use App\Api\Model\Order\OrderLog as OrderLog;
use App\Api\Logic\Mq as Mq;
use App\Api\Model\Order\ConfirmConfig as OrderConfirmConfig;

/*
 * @todo 订单确认
 */

class Confirm
{

    /*
     * @todo 订单确认
     */
    public function Confirm($confirm_data)
    {
        //日志记录
        $funcgion_log = function ($method_name, $success, $msg) use ($confirm_data) {
            $log_array = array(
                'action' => $method_name,
                'success' => $success ? 1 : 0,
                'data' => json_encode($confirm_data),
                'bn' => $confirm_data['order_id'],
                'remark' => $msg,
            );
            \Neigou\Logger::General('service_order_confirm', $log_array);
        };
        $msg = '';
        //检查订单数据
        $cherck_res = $this->CheckConfirmOrder($confirm_data, $msg);
        if (!$cherck_res) {
            $funcgion_log('order_check', false, $msg);
            return $this->Response(400, $msg);
        }
        //订单状态修改
        $res = Order::OrderConfirm(['order_id' => $confirm_data['order_id']]);
        if (!$res) {
            $funcgion_log('confirm_order_fail', false, '订单确认失败');
            return $this->Response(400, '确认失败');
        }
        //发送消息
        Mq::OrderConfirm($confirm_data['order_id']);????????
        return $this->Response(200, '成功');
    }

    /*
     * @todo 检查订单
     */
    private function CheckConfirmOrder($confirm_data, &$msg)
    {
        if (empty($confirm_data['order_id'])) {
            $msg = '订单确认错误';
            return false;
        }
        //订单信息
        $order_info = Order::GetOrderInfoById($confirm_data['order_id']);
        if (empty($order_info)) {
            $msg = '订单不存在';
            return false;
        }
        if ($order_info->confirm_status == 2) {
            $msg = '订单已确认';
            return false;
        }
        //子订单不可以单独取消
        if ($order_info->p_id != 0 || $order_info->create_source != 'main') {
            $msg = '子订单不需要确认';
            return false;
        }
        // 验证&保存定制订单数据
        if ($order_info->extend_info_code == 'customization') {
            $confirm_extend_data = $confirm_data['confirm_data'];
            $orderExtend = $order_info->extend_data ? json_decode($order_info->extend_data, true) : array();
            if (empty($confirm_extend_data['customization_data']) && empty($orderExtend['customization_data'])) {
                $msg = '定制订单缺少数据';
                return false;
            }
            if ( ! empty($confirm_extend_data['customization_data'])) {
                $orderExtend['customization_data'] = $confirm_extend_data['customization_data'];
                $update_order_data = ['extend_data' => json_encode($orderExtend)];
                Order::OrderUpdate(array('order_id' => $confirm_data['order_id']), $update_order_data);
            }
        }
        return true;
    }

    /*
    * @todo 获取配置信息
    */
    public function getConfirmStatus($data)
    {
        $return = array(
            'auto_confirm' => 1, // 自动确认状态 0:无法自动确认 1:自动确认
            'is_manual' => 1, // 允许手动确认 1:允许 0:不允许
        );
        $orderConfirmConfigModel = new OrderConfirmConfig();

        $configList = $orderConfirmConfigModel->getConfigList();

        if (empty($configList)) {
            return $return;
        }

        $isMatch = false;
        foreach ($configList as $v) {
            // 备注
            if ($v['type'] == 'MEMO') {
                if ($v['value'] == 'ALL') {
                    if ( ! empty($data['memo']) && !in_array($data['system_code'],array('neigou','gallywix'))){
                        $isMatch = true;
                    }
                }
            } elseif ($v['type'] == 'POINT') {
                $values = explode(',', $v['value']);
                if (in_array($data['point_channel'], $values)) {
                    $isMatch = true;
                }
            } elseif ($v['type'] == 'PAYMENT') {
                $values = explode(',', $v['value']);
                if (in_array($data['payment'], $values)) {
                    $isMatch = true;
                }
            } elseif ($v['type'] == 'CATEGORY') {
                $values = explode(',', $v['value']);
                if (in_array($data['order_category'], $values)) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                $return['auto_confirm'] = $v['auto_confirm'] == 1 ? 1 : 0;
                $return['is_manual'] = $v['is_manual'] == 1 ? 1 : 0;
                break;
            }
        }

        return $return;
    }

    /*
     * @todo 保存订单日志
     */
    protected function SaveLog($confirm_data)
    {
        //保存订单操作日志
        $log_data = [
            'order_id' => $confirm_data['order_id'],
            'title' => '订单确认成功',
            'content' => json_encode($confirm_data),
            'create_time' => time(),
            'type' => OrderLog::LOG_TYPE_CONFIRM
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
