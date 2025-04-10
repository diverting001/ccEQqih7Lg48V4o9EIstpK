<?php

namespace App\Api\Model\PreOrder;

class PreOrder
{

    /*
     * @todo 获取预下单信息
     */
    public static function GetPreOrder($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $sql = "select * from server_pre_order_info where order_id = :order_id";
        $order_id_info = app('api_db')->selectOne($sql, ['order_id' => $order_id]);
        if (!empty($order_id_info)) {
            $order_id_info->data = json_decode($order_id_info->data, true);
        }
        return $order_id_info;
    }

    /*
     * @todo 保存预下单信息
     */
    public static function SavePreOrder($order_id, $data = array(), $expire_after_seconds = 7200)
    {
        $sql = "INSERT INTO `server_pre_order_info` (`order_id`,`data`, `create_at`,`expire_after_seconds`)VALUES(:order_id,:data,:create_at,:expire_after_seconds);";
        $res = app('api_db')->insert($sql, [
            'order_id' => $order_id,
            'data' => json_encode($data),
            'create_at' => time(),
            'expire_after_seconds' => $expire_after_seconds
        ]);
        return $res;
    }
}
