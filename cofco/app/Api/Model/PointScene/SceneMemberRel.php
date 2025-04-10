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
            'company_id' => $relData['company_id'],
            'member_id'  => $relData['member_id'],
            'scene_id'   => $relData['scene_id'],
            'account'    => '',
            'created_at' => time(),
            'updated_at' => time()
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
                'rel.exchange_rate')
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
                $builder->where('rel.scene_id', $queryFilter['scene_id']);
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
                $builder->where('rel.scene_id', $queryFilter['scene_id']);
            })
            ->forPage($page, $pageSize)
            ->get();
    }
}
