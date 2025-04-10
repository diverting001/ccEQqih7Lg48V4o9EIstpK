<?php

namespace App\Api\Model\Order;

class OrderLog
{
    const LOG_TYPE_CREATE          = 1; //订单创建
    const LOG_TYPE_CONFIRM         = 2; //订单确认
    const LOG_TYPE_PAY             = 3; //订单支付
    const LOG_TYPE_DELIVERY        = 4; //订单发货
    const LOG_TYPE_DELIVERY_CHANGE = 5; //物流变更
    const LOG_TYPE_REFUND          = 6; //订单退款确认成功
    const LOG_TYPE_CANCEL          = 7; //订单取消成功

    /*
     * @todo 保存订单操作日志
     */
    public static function SaveLog($log_data)
    {
        if (empty($log_data)) {
            return false;
        }
        $sql = "INSERT INTO `server_order_log` (`" . implode('`,`', array_keys($log_data)) . "`)VALUES(" . implode(',',
                array_fill(0, count($log_data), '?')) . ")";
        $res = app('api_db')->insert($sql, array_values($log_data));
        return $res;
    }

    public static function getTypes()
    {
        return [
            self::LOG_TYPE_CREATE          => '订单创建',
            self::LOG_TYPE_CONFIRM         => '订单确认成功',
            self::LOG_TYPE_PAY             => '订单支付成功',
            self::LOG_TYPE_DELIVERY        => '订单发货',
            self::LOG_TYPE_DELIVERY_CHANGE => '物流变更',
            self::LOG_TYPE_REFUND          => '订单退款确认成功',
            self::LOG_TYPE_CANCEL          => '订单取消成功',
        ];
    }

    /**
     * Notes:根据订单号查询日志
     * User: mazhenkang
     * Date: 2022/9/13 10:28
     * @param $order_id
     */
    public function getLogs($params)
    {
        $model = app('api_db')->table('server_order_log');
        if (isset($params['order_id']) && !empty($params['order_id'])) {
            $model->where('order_id', $params['order_id']);
        }
        if (isset($params['type']) && !empty($params['type'])) {
            if (is_array($params['type'])) {
                $model->whereIn('type', $params['type']);
            } else {
                $model->where('type', $params['type']);
            }
        }

        $list = $model->orderBy('id', 'ASC')
            ->get()->toArray();
        if(!empty($list)){
            $types = self::getTypes();
            foreach ($list as &$info){
                $info->type_str = $types[$info->type] ?: '';
            }
        }
        return $list;
    }
}
