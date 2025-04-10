<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/8/3
 * Time: 19:16
 */

namespace App\Api\Model\Order;


class OrderCalcLog
{
    private $_db;

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function addCalcLog($data)
    {

    }

    public function addVoucherLog($data)
    {

    }

    //获取订单优惠券记录
    public function getVoucherLog($where)
    {
        return $this->_db->table('server_order_calc_voucher')->where($where)->get()->toArray();
    }

}
