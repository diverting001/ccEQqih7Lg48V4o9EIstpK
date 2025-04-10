<?php

namespace App\Api\Model\Promotion;

class CalculateLog
{
    /*
     * @todo 保存结算服务日志
     */
    public static function save($log_data)
    {
        if (empty($log_data)) {
            return false;
        }
        $sql = "INSERT INTO `server_calculate_log` (`" . implode('`,`', array_keys($log_data)) . "`) VALUES(" . implode(',', array_fill(0, count($log_data), '?')) . ")";
        $res = app('api_db')->insert($sql, array_values($log_data));
        return $res;
    }

    public static function getCalculateLogByMainOrderId($order_id)
    {
        $sql = "select * from server_calculate_log where order_id = :order_id";
        $order_info = app('api_db')->selectOne($sql, ['order_id' => $order_id]);
        return $order_info;
    }
}
