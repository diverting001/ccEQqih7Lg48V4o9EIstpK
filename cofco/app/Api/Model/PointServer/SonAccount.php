<?php

namespace App\Api\Model\PointServer;

use Illuminate\Database\Query\Builder;

class SonAccount
{
    /**
     * 创建子账户
     */
    public static function Create($accountInfo)
    {
        $accountInfo = array(
            'account_id'   => $accountInfo['account_id'],
            'point'        => $accountInfo['point'],
            'used_point'   => isset($accountInfo['used_point']) ? $accountInfo['used_point'] : 0,
            'frozen_point' => isset($accountInfo['frozen_point']) ? $accountInfo['frozen_point'] : 0,
            'overdue_time' => $accountInfo['overdue_time'],
            'overdue_func' => $accountInfo['overdue_func'] ?? 'inaction',
            'status'       => 0,
            'created_at'   => time(),
            'updated_at'   => time()
        );
        try {
            $sonAccountId = app('api_db')->table('server_new_point_account_son')->insertGetId($accountInfo);
        } catch (\Exception $e) {
            $sonAccountId = false;
        }
        return $sonAccountId;
    }

    public static function QueryByAccountId($accountId)
    {
        return app('api_db')->table('server_new_point_account_son')
            ->where('account_id', $accountId)
            ->get();
    }


    public static function QueryByAccountBns($accounts)
    {
        foreach ($accounts as &$account) {
            $account = strval($account);
        }

        $list = app('api_db')->table('server_new_point_account as account')
            ->select("account.account", "sonAccount.*")
            ->leftJoin('server_new_point_account_son as sonAccount', 'account.account_id', '=', 'sonAccount.account_id')
            ->whereIn('account.account', $accounts)
            ->get();
        return $list;
    }

    /**
     * 变更账户数据-入账积分
     */
    public static function IncomePoint($sonAccountId, $pointData)
    {
        $sql = 'update `server_new_point_account_son` set `updated_at` = :updated_at , `point` = `point`+:point  where `son_account_id` = :son_account_id';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'point'          => $pointData['point'],
                'son_account_id' => $sonAccountId
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 变更账户数据-冻结积分
     */
    public static function FrozenPoint($sonAccountId, $pointData)
    {
        $sql = 'update `server_new_point_account_son` set `updated_at` = :updated_at , `point` = `point`-:frozen_point , `frozen_point` = `frozen_point`+:frozen_point1 where `son_account_id` = :son_account_id and `point` >= :frozen_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'frozen_point'   => $pointData['frozen_point'],
                'son_account_id' => $sonAccountId,
                'frozen_point1'  => $pointData['frozen_point'],
                'frozen_point2'  => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function ReleaseFrozenPoint($sonAccountId, $pointData)
    {
        $sql = 'update `server_new_point_account_son` set `updated_at` = :updated_at , `point` = `point`+:release_point , `frozen_point` = `frozen_point`-:release_point1 where `son_account_id` = :son_account_id and `frozen_point` >= :release_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'release_point'  => $pointData['release_point'],
                'son_account_id' => $sonAccountId,
                'release_point1' => $pointData['release_point'],
                'release_point2' => $pointData['release_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 变更账户数据
     */
    public static function ConsumePoint($sonAccountId, $pointData)
    {
        $sql = 'update `server_new_point_account_son` set `updated_at` = :updated_at , `point` = `point`-:point where `son_account_id` = :son_account_id and `point` >= :point1';
        try {
            $status = app('api_db')->update($sql, [
                'updated_at'     => time(),
                'point'          => $pointData['point'],
                'son_account_id' => $sonAccountId,
                'point1'         => $pointData['point'],
            ]);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 变更账户数据-从冻结积分中消费
     */
    public static function ConsumePointByFrozen($sonAccountId, $pointData)
    {
        $sql = 'update `server_new_point_account_son` set `updated_at` = :updated_at , `frozen_point` = `frozen_point`-:frozen_point , `used_point` = `used_point`+:frozen_point1 where `son_account_id` = :son_account_id and `frozen_point` >= :frozen_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'frozen_point'   => $pointData['frozen_point'],
                'son_account_id' => $sonAccountId,
                'frozen_point1'  => $pointData['frozen_point'],
                'frozen_point2'  => $pointData['frozen_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function Find($sonAccountId, $overdueTime = 0)
    {
        return app('api_db')->table('server_new_point_account_son')
            ->where('son_account_id', $sonAccountId)
            ->where('status', 0)
            ->when($overdueTime, function (Builder $builder) use ($overdueTime) {
                return $builder->where("overdue_time", ">=", $overdueTime);
            })
            ->first();
    }

    /**
     * 查询指定过期时间后有效的积分子账户
     */
    public static function QueryAvailable($accountId, $overdueTime)
    {
        return app('api_db')->table('server_new_point_account_son')
            ->select("son_account_id", "account_id", "point", "overdue_time", "status")
            ->where("account_id", $accountId)
            ->where("status", 0)
            ->when($overdueTime, function (Builder $builder) use ($overdueTime) {
                return $builder->where("overdue_time", ">=", $overdueTime);
            })
            ->where("point", ">", 0)
            ->get();
    }

    public static function GetAccountInfoBySonAccountId($sonAccountId)
    {
        return app('api_db')->table('server_new_point_account_son as sonAccount')
            ->leftJoin('server_new_point_account as account', "sonAccount.account_id", "=", "account.account_id")
            ->select("sonAccount.son_account_id", "account.*")
            ->where("sonAccount.son_account_id", $sonAccountId)
            ->first();
    }

    public static function GetSonAccountInfoBySonAccountId($sonAccountId)
    {
        return app('api_db')->table('server_new_point_account_son as sonAccount')
            ->leftJoin('server_new_point_account as account', "sonAccount.account_id", "=", "account.account_id")
            ->select("sonAccount.son_account_id", "account.account", "sonAccount.point", "sonAccount.used_point", "sonAccount.frozen_point")
            ->where("sonAccount.son_account_id", $sonAccountId)
            ->first();
    }

    public static function RefundPoint($sonAccountId, $pointData)
    {
        $sql = 'update `server_new_point_account_son` set `updated_at` = :updated_at , `point` = `point`+:refund_point , `used_point` = `used_point`-:refund_point1 where `son_account_id` = :son_account_id and `used_point` >= :refund_point2';
        try {
            $status = app('api_db')->update($sql, array(
                'updated_at'     => time(),
                'refund_point'   => $pointData['refund_point'],
                'son_account_id' => $sonAccountId,
                'refund_point1'  => $pointData['refund_point'],
                'refund_point2'  => $pointData['refund_point'],
            ));
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    public static function GetAccountTotalValidPoint($accountIds, $overdueTime = null)
    {
        $overdueTime    = $overdueTime ?? time();

        $selectStr      = 'son_account_id,account_id,point,used_point,frozen_point,overdue_time';
        $whereStr       = 'account_id in (' . implode(',', $accountIds) . ') ';
        $sql            = 'select  ' . $selectStr . ' from server_new_point_account_son where ' . $whereStr;
        $allAccountList = app('api_db')->select($sql);

        $returnData = [];
        foreach ($allAccountList as $account) {
            if (!isset($returnData[$account->account_id])) {
                if ($account->overdue_time > $overdueTime) {
                    $returnData[$account->account_id] = (object)array(
                        'point'         => $account->point,
                        'used_point'    => $account->used_point,
                        'frozen_point'  => $account->frozen_point,
                        'overdue_point' => 0,
                    );
                } else {
                    $returnData[$account->account_id] = (object)array(
                        'point'         => 0,
                        'used_point'    => $account->used_point,
                        'frozen_point'  => $account->frozen_point,
                        'overdue_point' => $account->point,
                    );
                }
            } else {
                $returnData[$account->account_id]->used_point   += $account->used_point;
                $returnData[$account->account_id]->frozen_point += $account->frozen_point;
                if ($account->overdue_time > $overdueTime) {
                    $returnData[$account->account_id]->point += $account->point;
                } else {
                    $returnData[$account->account_id]->overdue_point += $account->point;
                }
            }
        }

        return $returnData;
    }

    public static function GetEarliestOverdueTime($accountIds)
    {
        $selectStr   = 'account_id,min(overdue_time) as overdue_time';
        $whereStr    = 'account_id in (' . implode(',',
                $accountIds) . ') and status=0 and point>0 and overdue_time>= ' . time();
        $sql         = 'select  ' . $selectStr . ' from server_new_point_account_son where ' . $whereStr . ' group by account_id';
        $accountList = app('api_db')->select($sql);
        $returnData  = [];
        foreach ($accountList as $account) {
            $returnData[$account->account_id] = $account;
        }
        return $returnData;
    }

    public static function getOverdueAccountByFunc($overdueFunc, $page = 1, $pageSize = 100)
    {
        return app('api_db')->table('server_new_point_account_son as sonAccount')
            ->leftJoin('server_new_point_account as account', "sonAccount.account_id", "=", "account.account_id")
            ->select(
                'sonAccount.son_account_id',
                'sonAccount.account_id',
                'sonAccount.point',
                'sonAccount.frozen_point',
                'sonAccount.overdue_time',
                'account.account'
            )
            ->where('sonAccount.overdue_func', $overdueFunc)
            ->where('sonAccount.point', '>', 0)
            ->where('sonAccount.overdue_time', '<=', time())
            ->forPage($page, $pageSize)
            ->get();
    }
}
