<?php

namespace App\Api\Model\OrderSplit;

class SplitData
{

    /*
     * @todo 获取拆单信息
     */
    public static function GetInfoById($split_id)
    {
        if (empty($split_id)) {
            return array();
        }
        $sql = "select * from server_order_split where split_id = :split_id";
        $split_info = app('api_db')->selectOne($sql, ['split_id' => $split_id]);
        return $split_info;
    }

    /*
     * @todo 保存数据
     */
    public static function Save($save_data)
    {
        if (empty($save_data)) {
            return false;
        }
        $id = app('api_db')->table('server_order_split')->insertGetId($save_data);
        return $id;
    }


}
