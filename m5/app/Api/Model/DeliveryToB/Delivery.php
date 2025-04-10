<?php

namespace app\Api\Model\DeliveryToB;

class Delivery
{

    public function find($where)
    {
        $_db = app('api_db');
        return $_db->table('server_tob_delivery')->where($where)->get();
    }

    /** shop查询运费
     *
     * @param $where
     * @return mixed
     * @author liuming
     */
    public function shopFind($where)
    {
        $_db = app('api_db');
        return $_db->table('server_delivery')->where($where)->get();
    }

}
