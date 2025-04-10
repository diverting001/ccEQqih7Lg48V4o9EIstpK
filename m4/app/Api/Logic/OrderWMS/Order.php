<?php

namespace App\Api\Logic\OrderWMS;


abstract class Order
{

    /*
     * @todo 创建订单
     */
    abstract public function Create($order_data);

    /*
     * @todo 获取订单详情
     */
    abstract public function GetInfo($order_id);

}
