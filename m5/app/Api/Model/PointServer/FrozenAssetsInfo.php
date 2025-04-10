<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class FrozenAssetsInfo
{
    /**
     * 添加入账信息
     */
    public static function Create($frozenInfo)
    {
        $frozenInfo = array(
            'frozen_assets_id' => $frozenInfo['frozen_assets_id'],
            "son_account_id" => $frozenInfo['son_account_id'],
            'frozen_point' => $frozenInfo['frozen_point'],
            'finish_point' => 0,
            'release_point' => 0,
            'created_at' => time(),
            'updated_at' => time()
        );
        try {
            $frozenInfoId = app('api_db')->table('server_new_point_frozen_assets_info')->insertGetId($frozenInfo);
        } catch (\Exception $e) {
            $frozenInfoId = false;
        }
        return $frozenInfoId;
    }

    /**
     * 获取可用的锁定资产信息
     */
    public static function QueryAvailable($frozenAssetsId)
    {
        return app('api_db')->table('server_new_point_frozen_assets_info')
            ->select("frozen_info_id", "frozen_assets_id", "son_account_id", "frozen_point", "finish_point", "release_point")
            ->where("frozen_assets_id", $frozenAssetsId)
            ->where("frozen_point", ">", 0)
            ->get();
    }

    /**
     * 变更账户数据-冻结积分
     */
    public static function Consume($frozenInfoId, $pointData)
    {
        $sql = 'update `server_new_point_frozen_assets_info` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:frozen_point , `finish_point` = `finish_point`+:frozen_point1 where `frozen_info_id` = :frozen_info_id and `frozen_point` >= :frozen_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'frozen_point' => $pointData['frozen_point'],
                'frozen_info_id' => $frozenInfoId,
                'frozen_point1' => $pointData['frozen_point'],
                'frozen_point2' => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Release($frozenInfoId, $pointData)
    {
        $sql = 'update `server_new_point_frozen_assets_info` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:release_point , `release_point` = `release_point`+:release_point1 where `frozen_info_id` = :frozen_info_id and `frozen_point` >= :release_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'release_point' => $pointData['release_point'],
                'frozen_info_id' => $frozenInfoId,
                'release_point1' => $pointData['release_point'],
                'release_point2' => $pointData['release_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }


}
