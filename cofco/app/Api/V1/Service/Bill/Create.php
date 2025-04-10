<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-05-31
 * Time: 10:46
 */

namespace App\Api\V1\Service\Bill;


use App\Api\Model\Bill\Bill;

class Create
{

    /**
     * 创建单据
     * @param $data
     * @return bool
     */
    public static function CreateBill($data, &$msg)
    {
        $data = self::_format_data($data);
        //如果是退款 检查单据号金额
        if ($data['bill_type'] == 'refund') {
            if (empty($data['relate_bill_id'])) {
                return false;
            }
            $has_refund_money = self::_get_refund_money($data['relate_bill_id']);
            if ($has_refund_money - $data['cur_money'] > 0.0001) {
                $msg = '退款单金额大于实际可退金额';
                return false;
            }
        }
        $res = Bill::saveBill($data);
        return $res;
    }

    /**
     * 格式化数据
     * @param $data
     * @return mixed
     */
    private static function _format_data($data)
    {
        $data['t_begin'] = time();
        $data['t_expire'] = $data['expire_time'];
        unset($data['expire_time']);
        $data['status'] = 'ready';
        $data['extend_data'] = json_encode($data['extend_data']);
        foreach ($data['items'] as $key => $val) {
            $data['items'][$key]['extend_data'] = json_encode($val['extend_data']);
        }
        return $data;
    }

    /**
     * 获取已经退款金额
     * @param $bill_id
     * @return int
     */
    private static function _get_refund_money($bill_id)
    {
        return Bill::HasRefundMoney($bill_id);
    }
}
