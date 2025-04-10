<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class AccountRecord
{
    /**
     * 添加账户流水
     */
    public static function Create($recordInfo)
    {
        $recordInfo = array(
            'son_account_id' => $recordInfo['son_account_id'],
            'bill_code' => $recordInfo['bill_code'],
            'frozen_record_id' => $recordInfo['frozen_record_id'],
            'record_type' => $recordInfo['record_type'],
            'before_point' => $recordInfo['before_point'],
            'change_point' => $recordInfo['change_point'],
            'after_point' => $recordInfo['after_point'],
            'memo' => $recordInfo['memo'],
            'created_at' => time()
        );
        try {
            $recordId = app('api_db')->table('server_new_point_account_record')->insertGetId($recordInfo);
        } catch (\Exception $e) {
            $recordId = false;
        }
        return $recordId;
    }
}
