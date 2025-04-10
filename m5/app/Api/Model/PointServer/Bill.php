<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 11:15
 */

namespace App\Api\Model\PointServer;


class Bill
{
    /**
     * 创建积分账单
     */
    public static function Create($billInfo)
    {
        $businessInfo = array(
            'bill_code' => strval($billInfo['bill_code']),
            'bill_type' => $billInfo['bill_type'],
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_bill')->insert($businessInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 根据业务流水号查询业务流水
     */
    public static function FindByBillCode($billCode)
    {
        $where = array(
            'bill_code' => strval($billCode),
        );
        return app('api_db')->table('server_new_point_bill')->where($where)->first();
    }

    public static function QueryByTransfer($billList)
    {
        foreach ($billList as &$item){
            $item = strval($item);
        }
        $billInfoList = app('api_db')->table('server_new_point_bill as bill')
            ->leftJoin('server_new_point_account_transfer as transfer', 'bill.bill_code', '=', 'transfer.bill_code')
            ->leftJoin('server_new_point_account_son as accountson', 'transfer.son_account_id', '=', 'accountson.son_account_id')
            ->leftJoin('server_new_point_account as account', 'accountson.account_id', '=', 'account.account_id')
            ->leftJoin('server_new_point_account_son as toaccountson', 'transfer.to_son_account_id', '=', 'toaccountson.son_account_id')
            ->leftJoin('server_new_point_account as toaccount', 'toaccountson.account_id', '=', 'toaccount.account_id')
            ->select('bill.bill_code', 'account.account', 'toaccount.account as to_account', 'toaccountson.point as toaccountson_point', 'toaccountson.used_point as toaccountson_used_point', 'toaccountson.frozen_point as toaccountson_frozen_point','toaccountson.overdue_time as overdue_time','accountson.overdue_time as accountson_overdue_time', 'transfer.point', 'transfer.memo', 'transfer.created_at')
            ->whereIn('bill.bill_code' , $billList)
            ->get();
        return $billInfoList;
    }
}
