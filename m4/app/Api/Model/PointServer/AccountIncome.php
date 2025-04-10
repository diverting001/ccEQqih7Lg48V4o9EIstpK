<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class AccountIncome
{
    /**
     * 添加入账信息
     */
    public static function Create($incomeInfo)
    {
        $incomeInfo = array(
            'bill_code' => $incomeInfo['bill_code'],
            'son_account_id' => $incomeInfo['son_account_id'],
            'point' => $incomeInfo['point'],
            'memo' => $incomeInfo['memo'],
            'created_at' => time()
        );
        try {
            $status = app('api_db')->table('server_new_point_account_income')->insert($incomeInfo);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
