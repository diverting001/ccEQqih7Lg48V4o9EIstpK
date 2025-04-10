<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-05-30
 * Time: 16:08
 */

namespace App\Api\V1\Service\Bill;

class Pay
{

    /**
     * 设置支付
     * @param $bill_id
     * @param $set
     * @param $msg
     * @return bool
     */
    public function doPay($bill_id, $set, &$msg)
    {
        $bill_info = \App\Api\Model\Bill\Bill::GetBillInfoById($bill_id);
        if (empty($bill_info) || $bill_info->status != 'ready') {
            $msg = '单据已支付或不存在';
            return false;
        }
        //扩展信息保存
        $req_extend = $set['extend_data'];
        unset($set['extend_data']);
        $ori_extend_data = json_decode($bill_info->extend_data, true);
        $extend = array_merge($ori_extend_data, $req_extend);

        $set['extend_data'] = json_encode($extend);
        $set['bill_id'] = $bill_id;

        //数据保存
        $res = \App\Api\Model\Bill\Bill::BillPay($set);
        if (!$res) {
            $msg = '信息保存失败';
            return false;
        }
        return true;
    }
}
