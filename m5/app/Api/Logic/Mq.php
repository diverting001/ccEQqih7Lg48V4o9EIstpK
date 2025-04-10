<?php

namespace App\Api\Logic;

use App\Api\Model\Bill\Bill;
use App\Api\Model\Order\Order as OrderModel;
use App\Api\Model\AfterSale\AfterSale as AfterSaleModel;

/*
 * @todo 订单消息
 */

class Mq
{
    private static $_channel_name = 'service';

    /*
     * @todo 订单支付
     */
    public static function OrderPay($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        $result = $order_info->pay_status == 1 ? 'fail' : ($order_info->pay_status == 2 ? 'success' : 'unknown');
        $routing_key = 'order.pay.' . $result;
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'order_id' => $order_info->order_id,
                'member_id' => $order_info->member_id,
                'company_id' => $order_info->company_id,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        return $res;
    }

    /**
     * 单据支付成功
     * @param $bill_id
     * @return bool
     */
    public static function BillPay($bill_id)
    {
        if (empty($bill_id)) {
            return false;
        }
        $bill_info = Bill::GetBillInfoById($bill_id);
        $mq = new \Neigou\AMQP();
        if ($bill_info->status == 'succ') {
            $routing_key = 'bill.pay.succ';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'bill_id' => $bill_info->bill_id,
                    'time' => time(),
                ]
            ];
            $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
            return $res;
        }
        return true;
    }

    /*
     * @todo 订单取消
     */
    public static function OrderCancel($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        $result = $order_info->status == 2 ? 'success' : ($order_info->pay_status == 1 ? 'fail' : 'unknown');
        $routing_key = 'order.cancel.' . $result;
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'order_id' => $order_info->order_id,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        return $res;
    }

    /*
     * @todo 已支付订单取消
     */
    public static function OrderPayedCancelForRefund($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        $routing_key = 'order.payed_cancel_for_refund.success';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'order_id' => $order_info->order_id,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        return $res;
    }

    /*
     * @todo 订单创建订单
     */
    public static function OrderCreate($order_id)
    {??????
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        $result = !empty($order_info) ? 'success' : 'fail';
        $routing_key = 'order.create.' . $result;
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'order_id' => $order_info->order_id,
                'time' => time(),
            ]
        ];
        $res = $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        return $res;
    }

    /*
     * @todo 订单发货
     */
    public static function OrderDelivery($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        if ($order_info->ship_status == 2) {
            $routing_key = 'order.delivery.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'order_id' => $order_info->order_id,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 物流变更
     */
    public static function LogisticsChange($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        $routing_key = 'order.logistics.success';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'order_id' => $order_info->order_id,
                'time' => time(),
            ]
        ];
        $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);

        return true;
    }

    /*
     * @todo 订单支付后取消
     */
    public static function OrderPayedCancel($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        if ($order_info->pay_status == 2 && $order_info->status == 2) { // 已支付&已取消
            $routing_key = 'order.payedcancel.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'order_id' => $order_info->order_id,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 订单支付后取消
     */
    public static function ScenePointRefund($mq_data)
    {
        $mq = new \Neigou\AMQP();
        $routing_key = 'scene_point.refund.success';
        $mq_data['time'] = time();
        $send_data = [
            'routing_key' => $routing_key,
            'data' => $mq_data
        ];
        $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        return true;
    }

    /*
     * @todo 订单完成
     */
    public static function OrderFinish($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        if ($order_info->confirm_status == 2) {
            $routing_key = 'order.finish.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'order_id' => $order_info->order_id,
                    'wms_order_bn' => $order_info->wms_order_bn,
                    'wms_delivery_bn' => $order_info->wms_delivery_bn,
                    'create_source' => $order_info->create_source,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 订单确认
     */
    public static function OrderConfirm($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        if ($order_info->confirm_status == 2) { //如果订单已确认
            $routing_key = 'order.confirm.success';?????????
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'order_id' => $order_info->order_id,
                    'create_source' => $order_info->create_source,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 订单退款确认
     */
    public static function OrderRefund($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        if ($order_info->pay_status == 3) {
            $routing_key = 'order.refund.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'order_id' => $order_info->order_id,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 订单库存释放成功
     */
    public static function OrderLockCancel($order_id)
    {
        $routing_key = 'order.lockcancel.success';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'order_id' => $order_id,
                'time' => time(),
            ]
        ];
        $mq = new \Neigou\AMQP();
        $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
    }

    /*
     * @todo 订单库存释放成功
     */
    public static function OrderUpdate($wms_order_bn, $wms_code)
    {
        $routing_key = 'order.update.success';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'wms_order_bn' => (string)$wms_order_bn,
                'wms_code' => $wms_code,
            ]
        ];
        $mq = new \Neigou\AMQP();
        $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
    }

    /*
     * @todo 订单拆单
     */
    public static function OrderSplit($order_id)
    {
        $order_info = OrderModel::GetOrderInfoById($order_id);
        $mq = new \Neigou\AMQP();
        if ($order_info->split == 2) {
            $routing_key = 'order.split.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'order_id' => $order_info->order_id,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 订单售后
     */
    public static function AfterSaleFinish($afterSaleBn)
    {
        $afterSaleInfo = AfterSaleModel::GetAfterSaleInfoByBn($afterSaleBn);
        $mq = new \Neigou\AMQP();
        if ($afterSaleInfo->status == 6) {
            $routing_key = 'aftersale.finish.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'after_sale_bn' => $afterSaleInfo->after_sale_bn,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
    * @todo 订单售后
    */
    public static function AfterSaleReview($afterSaleBn)
    {
        $afterSaleInfo = AfterSaleModel::GetAfterSaleInfoByBn($afterSaleBn);
        $mq = new \Neigou\AMQP();
        if (in_array($afterSaleInfo->status, array(2, 3))) {
            $routing_key = 'aftersale.review.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'after_sale_bn' => $afterSaleInfo->after_sale_bn,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }


    /*
  * @todo 订单售后 售后入库
  */
    public static function AfterSaleWarehouse($afterSaleBn)
    {
        $afterSaleInfo = AfterSaleModel::GetAfterSaleInfoByBn($afterSaleBn);
        $mq = new \Neigou\AMQP();
        if (in_array($afterSaleInfo->status, array(5, 7,14))) {
            $routing_key = 'aftersale.warehouse.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'after_sale_bn' => $afterSaleInfo->after_sale_bn,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }

    /*
     * @todo 物流状态发生变更
     */
    public static function ExpressUpdate($expressCom, $expressNo)
    {
        $routing_key = 'express.update.success';
        $send_data = [
            'routing_key' => $routing_key,
            'data' => [
                'express_com' => $expressCom,
                'express_no' => $expressNo,
            ]
        ];

        $mq = new \Neigou\AMQP();
        $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
    }

    /**
     * 售后取消
     * @param $afterSaleBn
     * @return true
     */
    public static function AfterSaleCannel($afterSaleBn)
    {
        $afterSaleInfo = AfterSaleModel::GetAfterSaleInfoByBn($afterSaleBn);
        $mq = new \Neigou\AMQP();
        if ($afterSaleInfo->status == 8) {
            $routing_key = 'aftersale.cancel.success';
            $send_data = [
                'routing_key' => $routing_key,
                'data' => [
                    'after_sale_bn' => $afterSaleInfo->after_sale_bn,
                    'time' => time(),
                ]
            ];
            $mq->PublishMessage(self::$_channel_name, $routing_key, $send_data);
        }
        return true;
    }
}
