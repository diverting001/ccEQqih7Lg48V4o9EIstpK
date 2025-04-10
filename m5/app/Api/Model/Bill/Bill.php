<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-05-30
 * Time: 14:47
 */

namespace App\Api\Model\Bill;

use Neigou\RedisNeigou;

class Bill
{
    private $_redis_incr_key = 'bill:pay_index';

    /**
     * 生产单据号
     * @return string
     */
    public function GetBillId()
    {
        do {
            $index = $this->_inc_key();
            $bill_id = time() . rand(1000, 9999) . $index;
            $bill_id_isset = self::CheckBillId($bill_id);
        } while (!$bill_id_isset);
        return $bill_id;
    }

    /**
     * 获取自增ID
     * @return int
     */
    private function _inc_key()
    {
        $redis = new RedisNeigou();
        $redis->_prefix = 'service:';
        $index = $redis->_redis_connection->get($this->_redis_incr_key);
        if ($index > 99 || $index < 10) {
            $index = 10;
            $redis->_redis_connection->set($this->_redis_incr_key, 10);
        }
        $redis->_redis_connection->incr($this->_redis_incr_key, 1);
        return $index;
    }

    /**
     * 检验bill_id是否存在
     * @param $bill_id
     * @return bool
     */
    public static function CheckBillId($bill_id)
    {
        if (empty($bill_id)) {
            return false;
        }
        $sql = "select count(1) as total from server_bills where bill_id = :bill_id";
        $total = app('api_db')->selectOne($sql, ['bill_id' => $bill_id]);
        if (empty($total) || empty($total->total)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 查询一条支付单
     * @param $bill_id
     * @return mixed
     */
    public static function GetBillInfoById($bill_id)
    {
        $sql = "select * from server_bills sb left join server_bills_relation sbr on sb.bill_id = sbr.bill_id where sb.bill_id = :bill_id";
        $bill_info = app('api_db')->selectOne($sql, ['bill_id' => $bill_id]);
        return $bill_info;
    }

    /**
     * 查询一条支付单 by order_id
     * @param $order_id
     * @return array
     */
    public static function GetBillInfoByOrderId($order_id)
    {
        if (empty($order_id)) {
            return [];
        }
        $sql = "SELECT * FROM server_bills_relation sbr left join server_bills sb on sbr.bill_id = sb.bill_id where sbr.order_id = :order_id";
        $list = app('api_db')->selectOne($sql, ['order_id' => $order_id]);
        return $list;
    }

    /**
     * 获取支付单 详情列表
     * @param $bill_id
     * @return array|mixed
     */
    public static function GetBillItemsByBillId($bill_id)
    {
        if (empty($bill_id)) {
            return [];
        }
        $item_list = self::GetBillItems([$bill_id]);
        if (!isset($item_list[$bill_id])) {
            return [];
        }
        return $item_list[$bill_id];
    }

    /**
     * 【批量】获取支付单item
     * @param $bill_id_list
     * @return array
     */
    public static function GetBillItems($bill_id_list)
    {
        $bill_item_list = [];
        if (empty($bill_id_list) || !is_array($bill_id_list)) {
            return $bill_item_list;
        }
        $sql = "select * from server_bills_items where bill_id in(" . implode(',',
                array_fill(0, count($bill_id_list), '?')) . ")";
        $item_list = app('api_db')->select($sql, array_values($bill_id_list));
        if (empty($item_list)) {
            return $bill_item_list;
        }
        foreach ($item_list as $item) {
            $bill_item_list[$item->bill_id][] = $item;
        }
        return $bill_item_list;
    }

    /**
     * 新增保存一张单据
     * @param $data
     * @return bool
     */
    public static function saveBill($data)
    {
        if (empty($data)) {
            return false;
        }
        //开启事务
        app('db')->beginTransaction();
        //保存流水
        $res = self::AddBill($data);
        if (!$res) {
            app('db')->rollBack();
            return false;
        }
        //提交订单
        app('db')->commit();
        return true;
    }

    /**
     * 保存单据SQL
     * @param $bill_data
     * @return bool
     */
    private static function AddBill($bill_data)
    {
        if (empty($bill_data)) {
            return false;
        }
        //保存流水信息
        $sql = "INSERT INTO server_bills_relation set bill_id = :bill_id,order_id = :order_id,type=:type,amount=:amount";
        $res = app('db')->insert($sql, [
            'bill_id' => $bill_data['bill_id'],
            'order_id' => $bill_data['order_id'],
            'type' => $bill_data['bill_type'],
            'amount' => $bill_data['cur_money']
        ]);
        if (!$res) {
            return false;
        }

        $items = $bill_data['items'];
        unset($bill_data['items']);
        unset($bill_data['order_id']);
        $sql = "INSERT INTO `server_bills` (`" . implode('`,`', array_keys($bill_data)) . "`)VALUES(" . implode(',',
                array_fill(0, count($bill_data), '?')) . ")";
        $res = app('db')->insert($sql, array_values($bill_data));
        if (!$res) {
            return false;
        }
        //保存订单明细
        foreach ($items as $item) {
            $item['bill_id'] = $bill_data['bill_id'];
            $sql = "INSERT INTO `server_bills_items` (`" . implode('`,`',
                    array_keys($item)) . "`)VALUES(" . implode(',', array_fill(0, count($item), '?')) . ")";
            $res = app('db')->insert($sql, array_values($item));
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    /**
     * 单据信息变更
     * @param $where
     * @param $update_data
     * @return bool
     */
    public static function BillUpdate($where, $update_data)
    {
        if (empty($where) || empty($update_data)) {
            return false;
        }
        if (!isset($update_data['last_modified'])) {
            $update_data['last_modified'] = time();
        }
        $res = app('api_db')->table('server_bills')->where($where)->update($update_data);
        return $res;
    }

    /**
     * 支付单据
     * @param $data
     * @return bool
     */
    public static function BillPay($data)
    {
        if (empty($data)) {
            return false;
        }
        $set['t_payed'] = time();
        $set['trade_no'] = $data['trade_no'];
        $set['pay_account'] = $data['pay_account'];
        $set['status'] = 'succ';
        $set['extend_data'] = $data['extend_data'];
        $where = [
            'bill_id' => $data['bill_id'],
            'status' => 'ready',
        ];
        $res = self::BillUpdate($where, $set);
        if (!$res) {
            return false;
        }
        return true;
    }

    /**
     * 退款
     * @param $bill_id
     * @param $data
     * @return bool
     */
    public static function setRefunded($bill_id, $data)
    {
        if (empty($data)) {
            return false;
        }
        $set['t_payed'] = time();
        $set['trade_no'] = $data['trade_no'];
        $set['status'] = 'succ';
        $where = [
            'bill_id' => $bill_id,
            'status' => 'ready',
        ];
        $res = self::BillUpdate($where, $set);
        if (!$res) {
            return false;
        }
        return true;
    }

    public static function HasRefundMoney($bill_id)
    {
        $sql = "SELECT sum(cur_money) as money from server_bills where status in ('succ','ready') and bill_type = 'refund' and relate_bill_id =:bill_id";
        $rzt = app('api_db')->selectOne($sql, ['bill_id' => $bill_id]);
        if ($rzt->money > 0) {
            return $rzt->money;
        }
        return 0;
    }
}
