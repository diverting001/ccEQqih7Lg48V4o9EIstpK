<?php

namespace App\Api\Model\Order;

class Concurrent
{

    /*
     * @todo 获取订单id
     */
    public static function GetOrderId($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $sql = "select * from server_order_concurrent where order_id = :order_id";
        $order_id_info = app('api_db')->selectOne($sql, ['order_id' => $order_id]);
        return $order_id_info;
    }

    /*
     * @todo 保存分配订单id
     */
    public static function SaveOrderId($order_id)
    {
        $sql = "INSERT INTO `server_order_concurrent` (`order_id`, `create_time`)VALUES(:order_id, :create_time);";
        $res = app('api_db')->insert($sql, ['order_id' => $order_id, 'create_time' => time()]);
        return $res;
    }


}
