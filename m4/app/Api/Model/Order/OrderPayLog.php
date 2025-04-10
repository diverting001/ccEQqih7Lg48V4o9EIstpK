<?php

namespace App\Api\Model\Order;

class OrderPayLog
{

    /*
     * @todo 订单支付日志
     */
    public static function SaveLog($log_data)
    {
        if (empty($log_data)) {
            return false;
        }
        $sql = "INSERT INTO `server_order_pay_log` (`" . implode('`,`',
                array_keys($log_data)) . "`)VALUES(" . implode(',', array_fill(0, count($log_data), '?')) . ")";
        $res = app('api_db')->insert($sql, array_values($log_data));
        return $res;
    }

    public static function GetPayLog($order_id)
    {
        if (empty($order_id)) {
            return [];
        }
        $pay_log = self::GetPayLogList([$order_id]);
        if (!empty($pay_log)) {
            return $pay_log[0];
        } else {
            return [];
        }
    }

    /*
     * @todo 订单支付日志列表
     */
    public static function GetPayLogList($order_id_list)
    {
        if (empty($order_id_list)) {
            return [];
        }
        $in_data = [];
        foreach ($order_id_list as $k => $data) {
            $in_data['order_id_' . $k] = "{$data}";
        }
        $sql = "select * from `server_order_pay_log` where order_id in (:" . implode(',:', array_keys($in_data)) . ")";
        $pay_log_list = app('api_db')->select($sql, $in_data);
        return $pay_log_list;
    }

    /**
     * 获取支付日志记录
     * @param type $params
     */
    public static function GetPayLogByParams($params)
    {
        $sql = "select * from `server_order_pay_log` where ";
        if (isset($params['trade_no']) && isset($params['payment_id'])) {
            $sql .= "`payment_id`=:payment_id and `trade_no`=:trade_no";
        } elseif (isset($params['payment_id'])) {
            $sql .= "`payment_id`=:payment_id";
        } elseif (isset($params['trade_no'])) {
            $sql .= "`trade_no`=:trade_no";
        } else {
            return null;
        }
        return app('api_db')->selectOne($sql, $params);
    }

    /**
     * 获取order_id数组 格式['201708161225321115','201708171131533820']
     * @param type $params
     * @return []
     */
    public static function GetOrderIdsPayLogByParams($params)
    {
        $sql = "select `order_id` from `server_order_pay_log` where ";
        if (isset($params['trade_no']) && isset($params['payment_id'])) {
            $sql .= "`payment_id`=:payment_id and `trade_no`=:trade_no";
        } elseif (isset($params['payment_id'])) {
            $sql .= "`payment_id`=:payment_id";
        } elseif (isset($params['trade_no'])) {
            $sql .= "`trade_no`=:trade_no";
        } else {
            return [];
        }
        $data = [];
        $results = app('api_db')->select($sql, $params);
        if (!empty($results)) {
            foreach ($results as $row) {
                $data[] = $row->order_id;
            }
        }
        return $data;
    }
}
