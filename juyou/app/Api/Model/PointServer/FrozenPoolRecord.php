<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class FrozenPoolRecord
{
    /**
     * 添加账户流水
     */
    public static function Create($recordInfo)
    {
        $recordInfo = array(
            'frozen_info_id' => $recordInfo['frozen_info_id'],
            'record_type' => $recordInfo['record_type'],
            'before_point' => $recordInfo['before_point'],
            'change_point' => $recordInfo['change_point'],
            'after_point' => $recordInfo['after_point'],
            'memo' => $recordInfo['memo'],
            'created_at' => time()
        );
        try {
            $recordId = app('api_db')->table('server_new_point_frozen_assets_info_record')->insertGetId($recordInfo);
        } catch (\Exception $e) {
            $recordId = false;
        }
        return $recordId;
    }
}
