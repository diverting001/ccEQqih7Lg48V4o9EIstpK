<?php

namespace App\Api\Model\PointServer;

class Account
{
    public static function UpdateAccountPoint($accountId, $pointInfo)
    {
        $pointInfo = json_decode(json_encode($pointInfo), true);
        $saveData = array(
            "point" => isset($pointInfo['point']) ? $pointInfo['point'] : 0,
            "used_point" => isset($pointInfo['used_point']) ? $pointInfo['used_point'] : 0,
            "frozen_point" => isset($pointInfo['frozen_point']) ? $pointInfo['frozen_point'] : 0,
            "overdue_point" => isset($pointInfo['overdue_point']) ? $pointInfo['overdue_point'] : 0,
            "updated_at" => time()
        );
        $status = app('api_db')
            ->table('server_new_point_account')
            ->where('account_id', $accountId)
            ->update($saveData);
        return $status;
    }

    /**
     * 查询账户
     */
    public static function QueryBatch($accountList)
    {
        foreach ($accountList as &$item){
            $item = strval($item);
        }

        $where = array(
            'disabled' => 0
        );
        return app('api_db')
            ->table('server_new_point_account')
            ->select("account_id", "account", "point", "used_point", "frozen_point", "overdue_point")
            ->whereIn('account', $accountList)
            ->where($where)
            ->get();
    }

    public static function QueryByOverdueTime($accountList, $queryFilter)
    {
        foreach ($accountList as &$item){
            $item = strval($item);
        }

        return app('api_db')
            ->table('server_new_point_account as account')
            ->leftJoin('server_new_point_account_son as sonAccount', 'account.account_id', '=', 'sonAccount.account_id')
            ->select("account.account_id", "account.account", "sonAccount.point", "sonAccount.used_point",
                "sonAccount.frozen_point", "sonAccount.overdue_time")
            ->whereIn('account.account', $accountList)
            ->where('account.disabled', 0)
            ->where('sonAccount.point', '>', 0)
            ->where('sonAccount.overdue_time', '>=', $queryFilter['start_time'])
            ->where('sonAccount.overdue_time', '<', $queryFilter['end_time'])
            ->orderBy('sonAccount.overdue_time', 'asc')
            ->get();
    }

    /**
     * 查询账户
     */
    public static function Find($account)
    {
        $where = array(
            'account' => strval($account)
        );
        return app('api_db')
            ->table('server_new_point_account')
            ->where($where)
            ->first();
    }

    /**
     * 创建账户
     */
    public static function Create($accountInfo)
    {
        $accountInfo = array(
            'account' => strval($accountInfo['account']),
            'point' => 0,
            'used_point' => 0,
            'frozen_point' => 0,
            'overdue_point' => 0,
            'disabled' => 0,
            'created_at' => time(),
            'updated_at' => time()
        );
        try {
            $id = app('api_db')->table('server_new_point_account')->insertGetId($accountInfo);
        } catch (\Exception $e) {
            $id = false;
        }
        return $id;
    }

    /**
     * 变更账户数据-冻结积分
     */
    public static function FrozenPoint($accountId, $pointData)
    {
        $sql = 'update `server_new_point_account` set `updated_at` = :updated_at , `point` = `point`-:frozen_point , `frozen_point` = `frozen_point`+:frozen_point1 where `account_id` = :account_id and `point` >= :frozen_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'frozen_point' => $pointData['frozen_point'],
                'account_id' => $accountId,
                'frozen_point1' => $pointData['frozen_point'],
                'frozen_point2' => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function ReleaseFrozenPoint($accountId, $pointData)
    {
        $sql = 'update `server_new_point_account` set `updated_at` = :updated_at , `point` = `point`+:release_point , `frozen_point` = `frozen_point`-:release_point1 where `account_id` = :account_id and `frozen_point` >= :release_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'release_point' => $pointData['release_point'],
                'account_id' => $accountId,
                'release_point1' => $pointData['release_point'],
                'release_point2' => $pointData['release_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }


    /**
     * 变更账户数据-收入积分
     */
    public static function IncomePoint($accountId, $pointData)
    {
        $sql = 'update `server_new_point_account` set `updated_at` = :updated_at , `point` = `point`+:point where `account_id` = :account_id';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'point' => $pointData['point'],
                'account_id' => $accountId
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 变更账户数据-锁定积分消费
     */
    public static function ConsumePointByFrozen($accountId, $pointData)
    {
        $sql = 'update `server_new_point_account` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:frozen_point , `used_point` = `used_point`+:frozen_point1 where `account_id` = :account_id and `frozen_point` >= :frozen_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'frozen_point' => $pointData['frozen_point'],
                'account_id' => $accountId,
                'frozen_point1' => $pointData['frozen_point'],
                'frozen_point2' => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 变更账户数据-积分退化
     */
    public static function RefundPoint($accountId, $pointData)
    {
        $sql = 'update `server_new_point_account` set `updated_at` = :updated_at , `point` = `point`+:refund_point , `used_point` = `used_point`-:refund_point1 where `account_id` = :account_id and `used_point` >= :refund_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at' => time(),
                'refund_point' => $pointData['refund_point'],
                'account_id' => $accountId,
                'refund_point1' => $pointData['refund_point'],
                'refund_point2' => $pointData['refund_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }
}
