<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-23
 * Time: 11:15
 */

namespace App\Api\Model\PointServer;

use Illuminate\Database\Query\Builder;

class FrozenPool
{
    /**
     * 创建锁定池
     */
    public static function Create($frozenPoolInfo)
    {
        $businessInfo = array(
            'frozen_pool_code' => $frozenPoolInfo['frozen_pool_code'],
            'frozen_point'     => $frozenPoolInfo['frozen_point'],
            'finish_point'     => 0,
            'release_point'    => 0,
            'overdue_time'     => $frozenPoolInfo['overdue_time'],
            'status'           => 0,
            'created_at'       => time(),
            'updated_at'       => time()
        );
        try {
            $poolId = app('api_db')->table('server_new_point_frozen_pool')->insertGetId($businessInfo);
        } catch (\Exception $e) {
            $poolId = false;
        }
        return $poolId;
    }

    /**
     * 查询锁定池
     */
    public static function Find($frozenPoolCode)
    {
        $where = array(
            'frozen_pool_code' => strval($frozenPoolCode)
        );
        return app('api_db')->table('server_new_point_frozen_pool')->where($where)->first();
    }

    /**
     * 查询锁定池
     */
    public static function QueryByPoolCodeList($frozenPoolCodeList)
    {
        foreach ($frozenPoolCodeList as &$item) {
            $item = strval($item);
        }
        return app('api_db')->table('server_new_point_frozen_pool')
            ->whereIn('frozen_pool_code', $frozenPoolCodeList)
            ->get();
    }

    /**
     * 查询锁定池列表中中指定账户
     */
    public static function QueryAvailableList($frozenPoolCodeList, $overdueTime = 0)
    {
        foreach ($frozenPoolCodeList as &$item) {
            $item = strval($item);
        }
        return app('api_db')->table('server_new_point_frozen_pool')
            ->select('frozen_pool_id', 'frozen_pool_code', 'frozen_point', 'finish_point', 'release_point',
                'overdue_time')
            ->whereIn("frozen_pool_code", $frozenPoolCodeList)
            ->whereIn("status", array(0, 1))
            ->when($overdueTime, function (Builder $builder) use ($overdueTime) {
                return $builder->where("overdue_time", ">=", $overdueTime);
            })
            ->get();
    }

    /**
     * 查询锁定池列表中中指定账户
     */
    public static function QueryAvailable($frozenPoolCode, $overdueTime = 0)
    {
        return app('api_db')->table('server_new_point_frozen_pool')
            ->select('frozen_pool_id', 'frozen_pool_code', 'frozen_point', 'finish_point', 'release_point',
                'overdue_time')
            ->where("frozen_pool_code", strval($frozenPoolCode))
            ->whereIn("status", array(0, 1))
            ->when($overdueTime, function (Builder $builder) use ($overdueTime) {
                return $builder->where("overdue_time", ">=", $overdueTime);
            })
            ->first();
    }

    /**
     * 变更账户数据-冻结积分
     */
    public static function Consume($frozenPoolId, $status, $pointData)
    {
        $sql = 'update `server_new_point_frozen_pool` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:frozen_point , `finish_point` = `finish_point`+:frozen_point1 , `status` = :status where `frozen_pool_id` = :frozen_pool_id and `frozen_point` >= :frozen_point2 and `status` in (0 , 1)';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'frozen_point'   => $pointData['frozen_point'],
                'frozen_point1'  => $pointData['frozen_point'],
                'status'         => $status,
                'frozen_pool_id' => $frozenPoolId,
                'frozen_point2'  => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Release($frozenPoolId, $pointData)
    {
        $sql = 'update `server_new_point_frozen_pool` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:release_point , `release_point` = `release_point`+:release_point1 , `status` = 2 where `frozen_pool_id` = :frozen_pool_id and `frozen_point` >= :release_point2 and `status` = 0';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'release_point'  => $pointData['release_point'],
                'release_point1' => $pointData['release_point'],
                'frozen_pool_id' => $frozenPoolId,
                'release_point2' => $pointData['release_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
