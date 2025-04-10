<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2019-05-31
 * Time: 11:31
 */

namespace App\Api\V1\Service\Bill;

use App\Api\Model\Bill\Bill as BillMdl;


class Bill
{
    /**
     * 单据查询
     * @param $bill_id
     * @return bool|mixed
     */
    public function GetInfoByBillId($bill_id)
    {
        if (empty($bill_id)) {
            return false;
        }
        $bill_info = BillMdl::GetBillInfoById($bill_id);
        if (!$bill_info->bill_id) {
            return false;
        }
        return $this->_format_info_data($bill_info);
    }

    /**
     * 格式化单据信息
     * @param $data
     * @return mixed
     */
    private function _format_info_data($data)
    {
        $format['bill_id'] = $data->bill_id;
        $format['pay_app_id'] = $data->pay_app_id;
        $format['cur_money'] = $data->cur_money;
        $format['t_begin'] = $data->t_begin;
        $format['t_payed'] = $data->t_payed;
        $format['t_expire'] = $data->t_expire;
        $format['last_modified'] = $data->last_modified;
        $format['status'] = $data->status;
        $format['trade_no'] = $data->trade_no;
        $format['cost'] = $data->cost;
        $format['memo'] = $data->memo;
        $format['extend_data'] = json_decode($data->extend_data, true);
        $format['version'] = $data->version;
        $format['bill_type'] = $data->bill_type;
        $format['relation']['order_id'] = $data->order_id;
        $format['relation']['type'] = $data->type;
        $format['relation']['amount'] = $data->amount;
        $format['relate_bill_id'] = $data->relate_bill_id;
        $items = BillMdl::GetBillItemsByBillId($data->bill_id);
        foreach ($items as $key => $val) {
            $items[$key]->extend_data = json_decode($val->extend_data, true);
        }
        $format['items'] = $items;
        return $format;
    }

}
