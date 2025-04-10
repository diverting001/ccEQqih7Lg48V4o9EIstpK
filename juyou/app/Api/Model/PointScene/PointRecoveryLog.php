<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-12
 * Time: 17:39
 */

namespace App\Api\Model\PointScene;


class PointRecoveryLog
{
    public static function Create($data)
    {
        $data['created_at'] = time();
        $data['updated_at'] = time();
        try {
            $id = app('api_db')->table('server_new_point_recover_log')->insertGetId($data);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }
}
