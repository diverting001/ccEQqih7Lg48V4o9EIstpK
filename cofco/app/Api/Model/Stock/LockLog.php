<?php

namespace App\Api\Model\Stock;

class LockLog
{

    //获取未确认的货品订单
    public static function GetOrderByProductBn($product_bn)
    {
        $sql = "select product_bn,lock_obj,`count` from server_stock_lock_log where `lock_type` = 'order'  and `status` in(1,3) and product_bn = :product_bn";
        $product_list = app('api_db')->select($sql, ['product_bn' => $product_bn]);
        return $product_list;
    }

    //获取货品冰结记录信息
    public static function GetPudoctLockInfoByOrder($product_bn, $lock_type, $lock_obj)
    {
        if (empty($product_bn) || empty($lock_type) || empty($lock_obj)) {
            return false;
        }
        $sql = "select * from server_stock_lock_log where product_bn = :product_bn and lock_type = :lock_type and lock_obj = :lock_obj limit 1";
        $lock_info = app('api_db')->selectOne($sql,
            ['product_bn' => $product_bn, 'lock_type' => $lock_type, 'lock_obj' => $lock_obj]);
        return $lock_info;
    }

    //更新货品冰结库存数
    public static function UpdataAmountUsed($lock_id, $amount_used, $data)
    {
        if (empty($lock_id) || empty($data)) {
            return false;
        }
        $sql = "update server_stock_lock_log set `amount_used` = " . intval($data['amount_used']) . ",status = " . intval($data['status']) . ",last_modified = " . time() . " where id = :lock_id and `amount_used` = :amount_used";
        $res = app('api_db')->update($sql, ['lock_id' => $lock_id, 'amount_used' => $amount_used]);
        return $res;
    }

    //保存订单锁定记录
    public static function SaveLockLog($save_data)
    {
        if (empty($save_data)) {
            return false;
        }
        $id = app('api_db')->table('server_stock_lock_log')->insertGetId($save_data);
        return $id;
    }

    //库存记录列表
    public static function GetLockLogList($where)
    {
        if (empty($where)) {
            return false;
        }
        $list = app('api_db')->table('server_stock_lock_log')->where($where)->get()->toarray();
        return $list;
    }

    //更新锁库记录状态
    public static function UpdateStatus($lock_id, $status, $old_status)
    {
        if (empty($lock_id) || empty($status) || empty($old_status)) {
            return false;
        }
        $sql = "update server_stock_lock_log set `status` = '{$status}',last_modified=" . time() . " where id = :lock_id and `status` = :old_status";
        $res = app('api_db')->update($sql, ['lock_id' => $lock_id, 'old_status' => $old_status]);
        return $res;
    }

    //更新锁定数据
    public static function Updata($where, $save_data)
    {
        if (empty($where) || empty($save_data)) {
            return false;
        }
        $res = app('api_db')->table('server_stock_lock_log')->where($where)->update($save_data);
        return $res;
    }

    /*
     * @todo 获取锁定记录
     */
    public static function GetLockInfoById($lock_id)
    {
        if (empty($lock_id)) {
            return false;
        }
        $sql = "select * from server_stock_lock_log where id = :lock_id";
        $lock_info = app('api_db')->selectOne($sql, ['lock_id' => $lock_id]);
        return $lock_info;
    }
}
