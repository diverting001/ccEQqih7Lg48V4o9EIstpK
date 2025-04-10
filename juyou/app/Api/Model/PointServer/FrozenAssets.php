<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 14:39
 */

namespace App\Api\Model\PointServer;


class FrozenAssets
{
    /**
     * 添加入账信息
     */
    public static function Create($frozenInfo)
    {
        $frozenInfo = array(
            'frozen_pool_id' => $frozenInfo['frozen_pool_id'],
            "account_id"     => $frozenInfo['account_id'],
            'frozen_point'   => $frozenInfo['frozen_point'],
            'finish_point'   => 0,
            'release_point'  => 0,
            'created_at'     => time(),
            'updated_at'     => time()
        );
        try {
            $frozenId = app('api_db')->table('server_new_point_frozen_assets')->insertGetId($frozenInfo);
        } catch (\Exception $e) {
            $frozenId = false;
        }
        return $frozenId;
    }

    /**
     * 获取可用的锁定资产信息
     */
    public static function Query($frozenPoolId)
    {
        return app('api_db')->table('server_new_point_frozen_assets as assets')
            ->leftJoin("server_new_point_account as account", "assets.account_id", '=', 'account.account_id')
            ->select(
                "assets.frozen_assets_id",
                "assets.frozen_pool_id",
                "assets.account_id",
                "account.account",
                "assets.frozen_point",
                "assets.finish_point",
                "assets.release_point"
            )
            ->where("assets.frozen_pool_id", $frozenPoolId)
            ->where("assets.frozen_point", ">", 0)
            ->get();
    }

    /**
     * 获取锁定资产信息
     */
    public static function QueryByPoolList($poolIdList)
    {
        return app('api_db')
            ->table('server_new_point_frozen_pool as pool')
            ->leftJoin('server_new_point_frozen_assets as assets', 'pool.frozen_pool_id', '=', 'assets.frozen_pool_id')
            ->leftJoin("server_new_point_account as account", "assets.account_id", '=', 'account.account_id')
            ->select(
                "pool.frozen_pool_code",
                "assets.frozen_assets_id",
                "pool.status",
                "assets.frozen_pool_id",
                "assets.account_id",
                "account.account",
                "assets.frozen_point",
                "assets.finish_point",
                "assets.release_point"
            )
            ->whereIn("assets.frozen_pool_id", $poolIdList)
            ->get();
    }

    /**
     * 获取可用的锁定资产信息
     */
    public static function QueryAvailableListByPoolAndAccount($frozenPoolId, $accountId)
    {
        return app('api_db')->table('server_new_point_frozen_assets')
            ->select(
                "frozen_assets_id",
                "frozen_pool_id",
                "account_id",
                "frozen_point",
                "finish_point",
                "release_point"
            )
            ->where("frozen_pool_id", $frozenPoolId)
            ->where("account_id", $accountId)
            ->where("frozen_point", ">", 0)
            ->get();
    }

    /**
     * 变更账户数据-冻结积分
     */
    public static function Consume($frozenAssetsId, $pointData)
    {
        $sql = 'update `server_new_point_frozen_assets` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:frozen_point , `finish_point` = `finish_point`+:frozen_point1 where `frozen_assets_id` = :frozen_assets_id and `frozen_point` >= :frozen_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'       => time(),
                'frozen_point'     => $pointData['frozen_point'],
                'frozen_point1'    => $pointData['frozen_point'],
                'frozen_assets_id' => $frozenAssetsId,
                'frozen_point2'    => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Release($frozenAssetsId, $pointData)
    {
        $sql = 'update `server_new_point_frozen_assets` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:release_point , `release_point` = `release_point`+:release_point1 where `frozen_assets_id` = :frozen_assets_id and `frozen_point` >= :release_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'       => time(),
                'release_point'    => $pointData['release_point'],
                'release_point1'   => $pointData['release_point'],
                'frozen_assets_id' => $frozenAssetsId,
                'release_point2'   => $pointData['release_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
