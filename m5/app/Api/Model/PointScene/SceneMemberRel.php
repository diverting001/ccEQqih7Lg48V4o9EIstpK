<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 18:11
 */

namespace App\Api\Model\PointScene;

use Illuminate\Database\Query\Builder;

class SceneMemberRel
{
    /**
     * 创建用户场景关联
     */
    public static function Create($relData)
    {
        $relData = array(
            'company_id'    => $relData['company_id'],
            'member_id'     => $relData['member_id'],
            'scene_id'      => $relData['scene_id'],
            'exchange_rate' => $relData['exchange_rate'],
            'account'       => '',
            'created_at'    => time(),
            'updated_at'    => time()
        );
        try {
            $relId = app('api_db')->table('server_new_point_member_scene_rel')->insertGetId($relData);
        } catch (\Exception $e) {
            $relId = false;
        }
        return $relId;
    }

    /**
     * 用户场景关联绑定底层积分账户
     */
    public static function BindAccount($relId, $account)
    {
        $where    = array(
            'id'      => $relId,
            'account' => ''
        );
        $saveData = array(
            "account" => strval($account)
        );
        try {
            $status = app('api_db')->table('server_new_point_member_scene_rel')->where($where)->update($saveData);
        } catch (\Exception $e) {
            $status = false;
        }
        return $status;
    }

    /**
     * 用户单个场景下账户
     */
    public static function FindByMemberAndScene($companyId, $memberId, $sceneId)
    {
        $where = array(
            'company_id' => $companyId,
            'member_id'  => $memberId,
            'scene_id'   => $sceneId
        );
        return app('api_db')->table('server_new_point_member_scene_rel')->where($where)->first();
    }

    /**
     * 查询用户多个场景账户
     */
    public static function FindByCompanyAndSceneList($companyId, $memberId, $sceneIds)
    {
        $where = array(
            'company_id' => $companyId,
            'member_id'  => $memberId,
        );
        return app('api_db')->table('server_new_point_member_scene_rel')
            ->select('id', "company_id", 'scene_id', "account")
            ->where($where)
            ->whereIn("scene_id", $sceneIds)
            ->get();
    }

    public static function QueryCountByMemberId($memberId)
    {
        $where = array(
            'rel.member_id' => $memberId
        );
        return app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->where($where)
            ->count();
    }

    public static function QueryCountByCompanyIdMemberId($companyId,$memberId)
    {
        $where = array(
            'rel.member_id' => $memberId,
            'rel.company_id' => $companyId
        );
        return app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->where($where)
            ->count();
    }
    public static function QueryByMemberId($memberId, $page = 1, $pageSize = 20)
    {
        $where       = array(
            'rel.member_id' => $memberId
        );
        $accountList = app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_scene as scene", "rel.scene_id", '=', 'scene.scene_id')
            ->select('rel.company_id', 'rel.member_id', 'rel.scene_id', 'rel.account', 'scene.name as account_name')
            ->where($where)
            ->forPage($page, $pageSize)
            ->get();

        $returnAccount = [];
        if ($accountList->count() > 0) {
            $sceneIds = [];
            $relArr   = [];
            foreach ($accountList as $item) {
                $sceneIds[] = $item->scene_id;
            }
            $relList = app('api_db')->table('server_new_point_scene_rule_rel')->whereIn('scene_id', $sceneIds)->get();
            if ($relList->count() > 0) {
                foreach ($relList as $rel) {
                    $relArr[$rel->scene_id][] = $rel->rule_bn;
                }
            }

            foreach ($accountList as $key => $account) {
                if (isset($relArr[$account->scene_id]) && $relArr[$account->scene_id]) {
                    $account->rule_bns = $relArr[$account->scene_id];
                } else {
                    $account->rule_bns = [];
                }
                $returnAccount[$account->company_id . '_' . $account->scene_id] = $account;
            }
        }
        return $returnAccount;
    }

    public static function QueryCompanyIdMemberId($companyId = 0,$memberId, $page = 1, $pageSize = 20)
    {
        $where       = array(
            'rel.member_id' => $memberId,
            'rel.company_id' => $companyId
        );
        $accountList = app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_scene as scene", "rel.scene_id", '=', 'scene.scene_id')
            ->select('rel.company_id', 'rel.member_id', 'rel.scene_id', 'rel.account', 'scene.name as account_name')
            ->where($where)
            ->forPage($page, $pageSize)
            ->get();

        $returnAccount = [];
        if ($accountList->count() > 0) {
            $sceneIds = [];
            $relArr   = [];
            foreach ($accountList as $item) {
                $sceneIds[] = $item->scene_id;
            }
            $relList = app('api_db')->table('server_new_point_scene_rule_rel')->whereIn('scene_id', $sceneIds)->get();
            if ($relList->count() > 0) {
                foreach ($relList as $rel) {
                    $relArr[$rel->scene_id][] = $rel->rule_bn;
                }
            }

            foreach ($accountList as $key => $account) {
                if (isset($relArr[$account->scene_id]) && $relArr[$account->scene_id]) {
                    $account->rule_bns = $relArr[$account->scene_id];
                } else {
                    $account->rule_bns = [];
                }
                $returnAccount[$account->company_id . '_' . $account->scene_id] = $account;
            }
        }
        return $returnAccount;
    }

    /**
     * 获取用户关联的所有场景
     */
    public static function QueryByMember($companyId, $memberId)
    {
        $where       = array(
            'rel.company_id' => $companyId,
            'rel.member_id'  => $memberId
        );
        $accountList = app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_scene as scene", "rel.scene_id", '=', 'scene.scene_id')
            ->select('rel.company_id', 'rel.member_id', 'rel.scene_id', 'rel.account', 'scene.name as account_name',
                'rel.exchange_rate','scene.extend_data', 'rel.id as member_account_id')
            ->where($where)
            ->get();

        $returnAccount = [];
        if ($accountList->count() > 0) {
            $sceneIds = [];
            $relArr   = [];
            foreach ($accountList as $item) {
                $sceneIds[] = $item->scene_id;
            }
            $relList = app('api_db')->table('server_new_point_scene_rule_rel')->whereIn('scene_id', $sceneIds)->get();
            if ($relList->count() > 0) {
                foreach ($relList as $rel) {
                    $relArr[$rel->scene_id][] = $rel->rule_bn;
                }
            }

            foreach ($accountList as $key => $account) {
                if (isset($relArr[$account->scene_id]) && $relArr[$account->scene_id]) {
                    $account->rule_bns = $relArr[$account->scene_id];
                } else {
                    $account->rule_bns = [];
                }
                $returnAccount[$account->scene_id] = $account;
            }
        }
        return $returnAccount;
    }

    public static function QueryByAccount($accounts)
    {
        foreach ($accounts as &$account) {
            $account = strval($account);
        }

        $list       = app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->select("rel.id", "rel.company_id", "rel.member_id", 'rel.scene_id', "rel.account", "rel.exchange_rate")
            ->whereIn('rel.account', $accounts)
            ->get();
        $returnData = [];
        foreach ($list as $accountInfo) {
            $returnData[$accountInfo->account] = $accountInfo;
        }
        return $returnData;
    }

    public static function FindByAccount($account)
    {
        $account = strval($account);
        return app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->select("rel.id", "rel.company_id", "rel.member_id", 'rel.scene_id', "rel.account")
            ->where('rel.account', $account)
            ->first();
    }

    public static function QueryCount($queryFilter)
    {
        return app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->select("rel.id", "rel.company_id", "rel.member_id", 'rel.scene_id', "rel.account")
            ->when($queryFilter['company_id'], function (Builder $builder) use ($queryFilter) {
                $builder->where('rel.company_id', $queryFilter['company_id']);
            })
            ->when($queryFilter['scene_id'], function (Builder $builder) use ($queryFilter) {
                if (is_array($queryFilter['scene_id'])) {
                    $builder->whereIn('rel.scene_id', $queryFilter['scene_id']);
                } else {
                    $builder->where('rel.scene_id', $queryFilter['scene_id']);
                }
            })
            ->when($queryFilter['member_ids'], function (Builder $builder) use ($queryFilter) {
                if ( is_array($queryFilter[ 'member_ids' ]) ) {
                    $builder->whereIn('rel.member_id', $queryFilter[ 'member_ids' ]);
                } else {
                    $builder->where('rel.member_id', $queryFilter[ 'member_ids' ]);
                }
            })
            ->count();
    }

    public static function Query($queryFilter, $page = 1, $pageSize = 10)
    {
        return app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_scene as scene", "rel.scene_id", '=', 'scene.scene_id')
            ->select("rel.id", "rel.company_id", "rel.member_id", 'rel.scene_id', "rel.account", "scene.name as account_name", "rel.exchange_rate")
            ->when($queryFilter['company_id'], function (Builder $builder) use ($queryFilter) {
                $builder->where('rel.company_id', $queryFilter['company_id']);
            })
            ->when($queryFilter['scene_id'], function (Builder $builder) use ($queryFilter) {
                if (is_array($queryFilter['scene_id'])) {
                    $builder->whereIn('rel.scene_id', $queryFilter['scene_id']);
                } else {
                    $builder->where('rel.scene_id', $queryFilter['scene_id']);
                }
            })
            ->when($queryFilter['member_ids'], function (Builder $builder) use ($queryFilter) {
                $builder->whereIn('rel.member_id', $queryFilter['member_ids']);
            })
            ->forPage($page, $pageSize)
            ->get();
    }

    /**
     * @param $accounts
     * @return mixed
     */
    public static function QuerySceneAccount($accounts)
    {
        foreach ($accounts as &$account) {
            $account = strval($account);
        }

        return app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_scene as scene", "rel.scene_id", '=', 'scene.scene_id')
            ->select("rel.id", "rel.company_id", "rel.member_id", 'rel.scene_id', "rel.account", "scene.name as account_name", "rel.exchange_rate")
            ->whereIn('rel.account', $accounts)
            ->get()->toArray();
    }

    public static function QueryOverdueByMemberIdOrCount($queryFilter, $count = false)
    {
        $page = $queryFilter['page'] ?? 1;
        $pageSize = $queryFilter['pageSize'] ?? 10;
        $query = app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_account as account", 'rel.account', '=', 'account.account')
            ->rightJoin("server_new_point_account_son as son", "account.account_id", '=', 'son.account_id')
            ->select("rel.member_id")
            ->where('rel.company_id', $queryFilter['companyId'])
            ->where('son.point', '>', 0)
            ->where('son.overdue_time', strtotime($queryFilter['overdueDate']))
            ->groupBy('rel.member_id')
            ->orderBy('rel.member_id', 'asc');
        if ($count) {
            $result = count($query->get()->all());
        } else {
            $result = $query->forPage($page, $pageSize)->get()->pluck('member_id');
        }
        return $result;
    }

    public static function QueryOverdueByMemberIdsList($companyId, $memberIds, $overdueDate)
    {
        $memberList = [];
        app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_account as account", 'rel.account', '=', 'account.account')
            ->rightJoin("server_new_point_account_son as son", "account.account_id", '=', 'son.account_id')
            ->leftJoin("server_new_point_scene as scene", 'scene.scene_id', '=', 'rel.scene_id')
            ->select(
                "son.point",
                "rel.id",
                "scene.name",
                "rel.company_id",
                "rel.member_id",
                'rel.scene_id',
                "rel.account",
                "rel.exchange_rate"
            )
            ->where('rel.company_id', $companyId)
            ->whereIn('rel.member_id', $memberIds)
            ->where('son.point', '>', 0)
            ->where('son.overdue_time', strtotime($overdueDate))
            ->get()
            ->each(function ($item, $key) use (&$memberList) {
                $memberList[$item->member_id][$item->scene_id]['scene_id']=$item->scene_id;
                $memberList[$item->member_id][$item->scene_id]['scene_name']=$item->name;
                $memberList[$item->member_id][$item->scene_id]['member_id']=$item->member_id;
                $memberList[$item->member_id][$item->scene_id]['point'] +=$item->point;
            });
        return $memberList;
    }

    /**
     * 获取场景积分相关子账户信息列表
     *
     * @param  array   $queryFilter
     * @param  boolean $count
     * @return mixed
     */
    public static function QueryPointAccountSon($queryFilter, $count = false)
    {
        $page = $queryFilter['page'] ?? 1;
        $pageSize = $queryFilter['pageSize'] ?? 10;

        $fields = array (
            'rel.company_id',
            'rel.member_id',
            'rel.scene_id',
            'rel.account',
            'rel.exchange_rate',
            'scene.name as scene_name',
            'son.point',
            'son.used_point',
            'son.overdue_time',
            'son.created_at'
        );

        $query = app('api_db')
            ->table('server_new_point_member_scene_rel as rel')
            ->leftJoin("server_new_point_scene as scene", "rel.scene_id", '=', 'scene.scene_id')
            ->leftJoin("server_new_point_account as account", 'rel.account', '=', 'account.account')
            ->rightJoin("server_new_point_account_son as son", "account.account_id", '=', 'son.account_id')
            ->select($fields)
            ->where('rel.company_id', $queryFilter['company_id']);

        if (!empty($queryFilter['scene_id'])) {
            $query = $query->whereIn('scene.scene_id', $queryFilter['scene_id']);
        }

        $filterTime = $queryFilter['filter_time'] ?? array();
        if (!empty($filterTime) && is_array($filterTime)) {
            $query = $query->where(function ($query) use ($filterTime) {
                foreach ($filterTime as $key => $item) {
                    if (empty($item['minTime']) || empty($item['maxTime'])) continue;

                    $orWhere = function ($query) use($item) {
                        $query->where('overdue_time', '>=', $item['minTime'])->where('overdue_time', '<', $item['maxTime']);
                    };
                    $query->orWhere($orWhere);
                }
            });
        }

        $query->orderBy('son.son_account_id', 'asc');

        if ($count) {
            $result = count($query->get()->all());
        } else {
            $result = $query->forPage($page, $pageSize)->get();
        }

        return $result;
    }
}
