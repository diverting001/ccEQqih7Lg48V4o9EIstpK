<?php

namespace app\Api\Model\Delivery;

class Delivery
{
    public function add($data = array())
    {
        $_db = app('api_db');
        return $_db->table('server_delivery')->insert($data);
    }

    public function del($where = array())
    {
        $_db = app('api_db');
        return $_db->table('server_delivery')->where($where)->delete();
    }

    public function save($where = array(), $data)
    {
        $_db = app('api_db');
        return $_db->table('server_delivery')->where($where)->update($data);
    }

    public function find($where)
    {
        $_db = app('api_db');
        return $_db->table('server_delivery')->where($where)->get();
    }

    public function getLastDtId()
    {
        $sql = "select `dt_id` from `sdb_b2c_dlytype` where `dt_status`='1' order by `ordernum` desc";
        $rs = app('api_db')->connection('neigou_store')->selectOne($sql);

        return is_object($rs) ? $rs->dt_id : null;
    }

    public function getDtIdByShopId($shop_id)
    {
        $sql = "select `dt_id` from `server_shop_delivery_rule` where `shop_id`=:shop_id and `is_enabled`=:is_enabled";
        $rs = app('api_db')->selectOne($sql, ['shop_id' => $shop_id, 'is_enabled' => 1]);
        return is_object($rs) ? $rs->dt_id : null;
    }

    public function getDlyTypeInfo($dt_id)
    {
        $sql = "select * from `sdb_b2c_dlytype` where `dt_id`=:dt_id order by `ordernum` desc";
        $rs = app('api_db')->connection('neigou_store')->selectOne($sql, ['dt_id' => $dt_id]);
        return is_object($rs) ? get_object_vars($rs) : null;
    }

    public function getRegionById($region_id)
    {
        $sql = "select * from `sdb_ectools_regions` where `region_id`=:region_id order by `ordernum` desc";
        $rs = app('api_db')->connection('neigou_store')->selectOne($sql, ['region_id' => $region_id]);
        return is_object($rs) ? get_object_vars($rs) : null;
    }

}
