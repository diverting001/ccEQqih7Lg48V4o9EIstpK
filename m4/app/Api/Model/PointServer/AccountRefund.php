<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class AccountRefund
{
    /**
     * 添加退款信息
     */
    public static function Create($refundInfo)
    {
        $incomeInfo = array(
            'bill_code' => $refundInfo['bill_code'],
            'son_account_id' => $refundInfo['son_account_id'],
            'consume_bill_code' => $refundInfo['consume_bill_code'],
            'point' => $refundInfo['point'],
            'memo' => $refundInfo['memo'],
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_account_refund')->insert($incomeInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
