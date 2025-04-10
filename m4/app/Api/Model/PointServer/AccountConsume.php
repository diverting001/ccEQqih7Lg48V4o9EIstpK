<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class AccountConsume
{
    /**
     * 添加入账信息
     */
    public static function Create($transferInfo)
    {
        $transferInfo = array(
            'bill_code' => $transferInfo['bill_code'],
            'son_account_id' => $transferInfo['son_account_id'],
            'point' => $transferInfo['point'],
            'refund_point' => 0,
            'memo' => $transferInfo['memo'],
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_account_consume')->insert($transferInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function GetConsumeByBillCode($billCode)
    {
        $consumeList = app('api_db')
            ->table('server_new_point_account_consume as consume')
            ->leftJoin('server_new_point_account_son as sonAccount', "consume.son_account_id", "=", "sonAccount.son_account_id")
            ->select("consume.id as consume_id", "sonAccount.son_account_id", "consume.point", "consume.refund_point", "sonAccount.point as accont_point", "sonAccount.frozen_point as accont_frozen_point")
            ->where("consume.bill_code", strval($billCode))
            ->orderBy("sonAccount.overdue_time", "desc")
            ->get();
        return $consumeList;
    }

    public static function RefundPoint($consumeId, $pointData)
    {
        $sql = 'update `server_new_point_account_consume` set `refund_point` = `refund_point`+:refund_point where `id` = :id';
        try {
            $status = app('api_db')->update($sql, array(
                'id' => $consumeId,
                'refund_point' => $pointData['refund_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}

