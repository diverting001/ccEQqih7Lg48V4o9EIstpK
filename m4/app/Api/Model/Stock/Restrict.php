<?php

namespace App\Api\Model\Stock;

class Restrict
{

    //查询货品库存限制
    public static function GetProductRestrict(array $product_bn, $channel, $time)
    {
        $product_restrict_list = [];
        if (empty($product_bn) || empty($channel) || empty($time)) {
            return false;
        }
        $sql = "select id,product_bn,max_stock,freez from server_stock_restrict 
                where product_bn in (" . implode(',',
                array_fill(0, count($product_bn), '?')) . ") and start_time <= ? and end_time >= ?";
        $where_data = array_merge($product_bn, [$time, $time]);
        $restrict_list = app('api_db')->select($sql, $where_data);
        if (!empty($restrict_list)) {
            foreach ($restrict_list as $item) {
                $product_restrict_list[$item->product_bn] = array(
                    'id' => $item->id,
                    'bn' => $item->product_bn,
                    'stock' => max(0, ($item->max_stock - $item->freez)),
                );
            }
        }
        return $product_restrict_list;
    }

    //活动限制库存锁定
    public static function Lock($id, $freez)
    {
        $freez = intval($freez);
        $sql = "update server_stock_restrict set freez = freez+ {$freez} where freez+{$freez} <= max_stock and id = :id";
        $res = app('api_db')->update($sql, ['id' => $id]);
        return $res;
    }

    //保存库存限制
    public static function Save($save_data)
    {
        if (empty($save_data)) {
            return false;
        }
        $id = app('api_db')->table('server_stock_restrict')->insertGetId($save_data);
        return $id;
    }


    //检查限制时间是否可以用
    public static function CheckTime($product_bn, $channel, $start_time, $end_time)
    {
        if (empty($product_bn) || empty($channel) || empty($start_time) || empty($end_time)) {
            return false;
        }
        $start_time = intval($start_time);
        $end_time = intval($end_time);
        $sql = "select count(1) as num from server_stock_restrict where product_bn = :product_bn and channel = :channel 
                and ((start_time <= $start_time and end_time >= $start_time) or (start_time <= $end_time and end_time >= $end_time) or (start_time >= $start_time and end_time <= $end_time)) ";
        $res = app('api_db')->selectOne($sql, ['product_bn' => $product_bn, 'channel' => $channel]);
        return $res->num;
    }

    //删除限制
    public static function Delete($id)
    {
        if (empty($id)) {
            return false;
        }
        $sql = "delete from server_stock_restrict where id = :id";
        $res = app('api_db')->delete($sql, ['id' => $id]);
        return $res;
    }

}




