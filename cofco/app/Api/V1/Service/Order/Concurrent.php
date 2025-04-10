<?php

namespace App\Api\V1\Service\Order;

use App\Api\Model\Order\Concurrent as MdlConcurrent;

/*
 * @todo 订单号发配
 */

class Concurrent
{


    /*
     * @todo 获取可用的订单号
     */
    public function GetOrderId()
    {
        do {
            $order_id = date('YmdHis') . rand(1000, 9999);
            $order_id_isset = MdlConcurrent::GetOrderId($order_id);
        } while ($order_id_isset);
        //保存记录
        MdlConcurrent::SaveOrderId($order_id);
        return $order_id;
    }

    /*
     * @todo 检查订单是否存在
     */
    public function CherckOrderId($order_id)
    {
        $order_info = MdlConcurrent::GetOrderId($order_id);
        return $order_info ? true : false;
    }


}
