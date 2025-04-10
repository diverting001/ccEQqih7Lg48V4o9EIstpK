<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-22
 * Time: 17:27
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Model\PointScene\CompanyBusinessRecord;
use App\Api\Model\PointScene\MemberBusinessRecord;
use App\Api\Model\PointScene\SceneCompanyRel as SceneCompanyRelModel;
use App\Api\Model\PointScene\SceneMemberRel;
use App\Api\V1\Service\PointServer\Account as AccountServer;
use App\Api\V1\Service\PointScene\BusinessFlow as BusinessFlowServer;
use App\Api\V1\Service\PointScene\BusinessFrozenFlow as BusinessFrozenFlowServer;
use App\Api\V1\Service\PointScene\MemberAccount as MemberAccountServer;

class CompanyAccount extends Account
{
    public function FindByCompanyAndSceneId($companyId, $sceneId)
    {
        $companyAccount = SceneCompanyRelModel::FindByCompanyAndScene($companyId, $sceneId);
        return $companyAccount;
    }

    /**
     * 查询公司下所有场景积分账户
     */
    public function QueryAll($queryFilter)
    {
        $companyId = $queryFilter['company_id'];
        $accountRel = SceneCompanyRelModel::FindByCompany($companyId);
        if ($accountRel->count() <= 0) {
            return $this->Response(false, '公司尚未关联场景', array());
        }

        $accountInfoArr = array();
        $sceneAccountList = array();
        foreach ($accountRel as $relInfo) {
            //初始化账户数据
            $accountInfoArr[$relInfo->scene_id] = array(
                'scene_id' => $relInfo->scene_id,
                'scene_name' => $relInfo->scene_name,
                'point' => 0,
                'used_point' => 0,
                'frozen_point' => 0,
                'overdue_point' => 0
            );
            //获取所有account
            if ($relInfo->account) {
                $sceneAccountList[$relInfo->scene_id] = $relInfo->account;
            }
        }

        $accountServer = new AccountServer();
        $accountRes = $accountServer->QueryBatch($sceneAccountList);
        if ($accountRes['data']) {
            foreach ($accountRes['data'] as $accountInfo) {
                $sceneId = array_search($accountInfo->account, $sceneAccountList);
                $accountInfoArr[$sceneId]['point'] = $accountInfo->point;
                $accountInfoArr[$sceneId]['used_point'] = $accountInfo->used_point;
                $accountInfoArr[$sceneId]['frozen_point'] = $accountInfo->frozen_point;
                $accountInfoArr[$sceneId]['overdue_point'] = $accountInfo->overdue_point;
            }
        }

        return $this->Response(true, '查询成功', $accountInfoArr);
    }

    /**
     * 公司场景积分冻结
     */
    public function Frozen($frozenData)
    {
        $businessType = $frozenData['business_type'];
        $businessBn = $frozenData['business_bn'];
        $systemCode = $frozenData['system_code'];
        $companyId = $frozenData['company_id'];
        $frozenInfo = $frozenData['frozen_info'];
        $overdueTime = $frozenData['overdue_time'];
        $totalFrozenPoint = $frozenData['total_point'];
        $memo = $frozenData['memo'] ? $frozenData['memo'] : "";

        $frozenInfoArr = array();
        $sceneIdArr = array();
        $listFrozenTotalPoint = 0;
        foreach ($frozenInfo as $frozenData) {
            $sceneIdArr[] = $frozenData['scene_id'];
            $listFrozenTotalPoint += $frozenData['point'];
            $frozenInfoArr[$frozenData['scene_id']] = $frozenData;
        }
        if ($listFrozenTotalPoint != $totalFrozenPoint) {
            return $this->Response(false, "锁定积分总数核对错误");
        }

        //查询账户
        $accountListRel = SceneCompanyRelModel::FindByCompanyAndSceneList($companyId, array_unique($sceneIdArr));
        if (!$accountListRel || $accountListRel->count() != count($frozenInfo)) {
            return $this->Response(false, "公司场景账户余额不足");
        }

        $frozenAccountList = array();
        foreach ($accountListRel as $accountInfo) {
            if (!$accountInfo->account) {
                return $this->Response(false, "公司场景账户余额不足");
            }
            $frozenInfoArr[$accountInfo->scene_id]['company_account_id'] = $accountInfo->id;
            $frozenInfoArr[$accountInfo->scene_id]['account'] = $accountInfo->account;
            $frozenAccountList[$accountInfo->account] = $frozenInfoArr[$accountInfo->scene_id];
        }


        app('db')->beginTransaction();
        //添加业务流水
        foreach ($frozenAccountList as $accountId => $frozenInfo) {
            $status = CompanyBusinessRecord::Create(array(
                'system_code' => $systemCode,
                'business_type' => $businessType,
                'business_bn' => $businessBn,
                'company_account_id' => $frozenInfo['company_account_id'],
                'record_type' => 'reduce',
                'point' => $frozenInfo['point'],
                'memo' => $memo,
                'created_at' => time()
            ));
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "业务流水添加失败");
            }
        }

        /**
         * 锁定积分
         * 锁定过期时间 = 用户积分过期时间
         */
        $accountServer = new AccountServer();
        //生成业务流水
        $businessFrozenFlowServer = new BusinessFrozenFlowServer();
        $bfCreateRes = $businessFrozenFlowServer->Create(array(
            "business_type" => $businessType,
            "business_bn" => $businessBn,
            "system_code" => $systemCode
        ));
        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }
        $businessFrozenCode = $bfCreateRes['data']['business_frozen_code'];

        //业务处理
        $frozenRes = $accountServer->Operation('create_frozen', array(
            "total_point" => $totalFrozenPoint,
            "overdue_time" => $overdueTime,
            "frozen_account_list" => $frozenAccountList,
            "memo" => $memo
        ));
        if (!$frozenRes['status']) {
            app('db')->rollBack();
            return $frozenRes;
        }
        $frozenPoolCode = $frozenRes['data']['frozen_pool_code'];

        //业务流水与内部流水绑定
        $bindRes = $businessFrozenFlowServer->BindFrozenPoolCode($businessFrozenCode, $frozenPoolCode);
        if (!$bindRes['status']) {
            app('db')->rollBack();
            return $bindRes;
        }

        app('db')->commit();
        return $this->Response(true, "冻结成功", array('flow_code' => $businessFrozenCode));
    }

    /**
     * 公司场景积分分配给用户
     */
    public function AssignToMembers($assignData)
    {
        $businessType = $assignData['business_type'];
        $businessBn = $assignData['business_bn'];
        $systemCode = $assignData['system_code'];
        $companyId = $assignData['company_id'];
        $sceneId = $assignData['scene_id'];
        $overdueTime = $assignData['overdue_time'];
        $overdueFunc = $assignData['overdue_func'];
        $totalPoint = $assignData['total_point'];
        $businessFrozenCode = $assignData['frozen_flow_code'];
        $memberList = $assignData['member_list'];
        $memo = $assignData['memo'] ? $assignData['memo'] : "";
        $listTotalPoint = 0;

        if (count($memberList) > 300) {
            return $this->Response(false, '单次操作最大300人');
        }

        foreach ($memberList as $memberInfo) {
            $listTotalPoint += $memberInfo['point'];
        }
        if ($totalPoint != $listTotalPoint) {
            return $this->Response(false, '总数核对错误');
        }

        $companyAccountRes = $this->GetCompanySceneAccount($companyId, $sceneId);
        if (!$companyAccountRes['status']) {
            return $companyAccountRes;
        }
        $companyAccount = $companyAccountRes['data']['account'];

        //根据锁定流水获取锁定池
        $businessFrozenServer = new BusinessFrozenFlowServer();
        $frozenCodeRes = $businessFrozenServer->GetBusinessFrozenPoolCode($businessFrozenCode);
        if (!$frozenCodeRes['status']) {
            return $this->Response(false, '锁定流水错误');
        }

        $frozenPoolCodeList = array();
        foreach ($frozenCodeRes['data'] as $frozenPoolRel) {
            $frozenPoolCodeList[] = $frozenPoolRel->frozen_pool_code;
        }

        //创建业务流水
        $businessFlowServer = new BusinessFlowServer();
        $bfCreateRes = $businessFlowServer->Create(array(
            "business_type" => $businessType,
            "business_bn" => $businessBn,
            "system_code" => $systemCode
        ));
        if (!$bfCreateRes['status']) {
            return $bfCreateRes;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        //获取用户场景账户，没有则创建
        $falseMemberList = array();
        $memberAccountServer = new MemberAccountServer();
        $accountServer = new AccountServer();
        foreach ($memberList as $pointData) {
            $memberId = $pointData['member_id'];
            $pointNum = $pointData['point'];

            $memberAccountRes = $memberAccountServer->GetMemberSceneAccount($companyId, $memberId, $sceneId);
            if (!$memberAccountRes['status']) {
                $falseMemberList[] = array(
                    "member_id" => $memberId,
                    "point" => $pointNum,
                    "msg" => $memberAccountRes['msg'],
                );
                continue;
            }
            $memberAccount = $memberAccountRes['data']['account'];

            app('db')->beginTransaction();

            $transferRes = $accountServer->Transaction('transfer', array(
                "frozen_pool_code_list" => $frozenPoolCodeList,
                "account" => $companyAccount,
                "to_account" => $memberAccount,
                "point" => $pointNum,
                "overdue_time" => $overdueTime,
                "overdue_func" => $overdueFunc,
                "memo" => $memo,
            ));
            if (!$transferRes['status']) {
                app('db')->rollBack();
                $falseMemberList[] = array(
                    "member_id" => $memberId,
                    "point" => $pointNum,
                    "msg" => $transferRes['msg'],
                );
                continue;
            }

            //添加业务流水
            $status = MemberBusinessRecord::Create(array(
                "business_type" => $businessType,
                "business_bn" => $businessBn,
                "system_code" => $systemCode,
                'member_account_id' => $memberAccountRes['data']['member_account_id'],
                'record_type' => 'add',
                'point' => $pointNum,
                'memo' => $memo,
                'created_at' => time()
            ));
            if (!$status) {
                app('db')->rollBack();
                $falseMemberList[] = array(
                    "member_id" => $memberId,
                    "point" => $pointNum,
                    "msg" => '业务流水添加失败',
                );
                continue;
            }


            $billCode = $transferRes['data']['bill_code'];
            $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);
            if (!$bindBillRes['status']) {
                app('db')->rollBack();
                $falseMemberList[] = array(
                    "member_id" => $memberId,
                    "point" => $pointNum,
                    "msg" => $bindBillRes['msg'],
                );
                continue;
            }

            app('db')->commit();
        }

        if (count($falseMemberList) == count($memberList)) {
            $businessFlowServer->Delete($businessFlowCode);
            return $this->Response(false, "发放失败", $falseMemberList);
        }

        if ($falseMemberList) {
            return $this->Response(true, "发放成功，部分失败", $falseMemberList);
        }

        return $this->Response(true, "发放成功", array('flow_code' => $businessFlowCode));
    }

    public function GetCompanySceneAccount($companyId, $sceneId)
    {
        //查询账户
        $accountRel = SceneCompanyRelModel::FindByCompanyAndScene($companyId, $sceneId);
        if (!$accountRel) {
            return $this->Response(false, "公司未关联场景");
        }
        $accountServer = new AccountServer();
        if ($accountRel->account) {
            $companyAccountId = $accountRel->id;
            $account = $accountRel->account;
        } else {
            //如果没有生成过账户则生成并绑定账户
            app('db')->beginTransaction();
            $createRes = $accountServer->Create();
            if (!$createRes['status']) {
                app('db')->rollBack();
                return $this->Response(false, $createRes['msg']);
            }
            $companyAccountId = $createRes['data']['company_account_id'];
            $account = $createRes['data']['account'];
            $bindRes = SceneCompanyRelModel::BindAccount($accountRel->id, $account);
            if (!$bindRes) {
                app('db')->rollBack();
                return $this->Response(false, "公司场景账户绑定失败");
            }
            app('db')->commit();
        }
        return $this->Response(true, "获取成功", array(
            "account" => $account,
            'company_account_id' => $companyAccountId
        ));
    }

    /**
     * 公司场景积分账户入账
     */
    public function Income($incomeData)
    {
        $companyId = $incomeData['company_id'];
        $sceneId = $incomeData['scene_id'];
        $accountRel = $this->GetCompanySceneAccount($companyId, $sceneId);
        if (!$accountRel['status']) {
            return $accountRel;
        }
        $account = $accountRel['data']['account'];

        $accountServer = new AccountServer();
        app('db')->beginTransaction();

        //添加业务流水
        $status = CompanyBusinessRecord::Create(array(
            'system_code' => $incomeData['system_code'],
            'business_type' => $incomeData['business_type'],
            'business_bn' => $incomeData['business_bn'],
            'company_account_id' => $accountRel['data']['company_account_id'],
            'record_type' => 'add',
            'point' => $incomeData['point'],
            'memo' => $incomeData['memo'] ? $incomeData['memo'] : '',
            'created_at' => time()
        ));
        if (!$status) {
            app('db')->rollBack();
            return $this->Response(false, "业务流水添加失败");
        }


        $incomeRes = $accountServer->Transaction('income', array(
            "account" => $account,
            "point" => $incomeData['point'],
            "overdue_time" => $incomeData['overdue_time'],
            "memo" => $incomeData['memo'] ? $incomeData['memo'] : '',
        ));
        if (!$incomeRes['status']) {
            app('db')->rollBack();
            return $incomeRes;
        }
        $businessFlowServer = new BusinessFlowServer();
        $bfCreateRes = $businessFlowServer->Create(array(
            "business_type" => $incomeData['business_type'],
            "business_bn" => $incomeData['business_bn'],
            "system_code" => $incomeData['system_code']
        ));
        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $bindBillRes = $businessFlowServer->BindBillCode($bfCreateRes['data']['business_flow_code'],
            $incomeRes['data']['bill_code']);
        if (!$bindBillRes['status']) {
            app('db')->rollBack();
            return $bindBillRes;
        }
        app('db')->commit();
        return $this->Response(true, '入账成功', array('flow_code' => $bfCreateRes['data']['business_flow_code']));
    }

    public function RecordList($queryData)
    {
        $systemCode = $queryData['system_code'];
        $companyIds = $queryData['company_ids'];
        $sceneIds = $queryData['scene_ids'];

        $recordCount = CompanyBusinessRecord::QueryCount($companyIds, $sceneIds, array(
            'system_code' => $systemCode,
            'begin_time' => isset($queryData['begin_time']) ? $queryData['begin_time'] : 0,
            'end_time' => isset($queryData['end_time']) ? $queryData['end_time'] : 0,
            'search_key' => isset($queryData['search_key']) ? $queryData['search_key'] : '',
            'record_type' => isset($queryData['record_type']) ? $queryData['record_type'] : 'all'
        ));

        if (!$recordCount) {
            return $this->Response(true, '无数据', array(
                'base' => array(
                    "totalPage" => 0,
                    "totalNum" => 0
                ),
                'data' => array()
            ));
        }

        $recordList = CompanyBusinessRecord::Query(
            $companyIds,
            $sceneIds,
            array(
                'system_code' => $systemCode,
                'begin_time' => isset($queryData['begin_time']) ? $queryData['begin_time'] : 0,
                'end_time' => isset($queryData['end_time']) ? $queryData['end_time'] : 0,
                'search_key' => isset($queryData['search_key']) ? $queryData['search_key'] : '',
                'record_type' => isset($queryData['record_type']) ? $queryData['record_type'] : 'all'
            ),
            $queryData['page'],
            $queryData['page_size']
        );

        return $this->Response(true, '查询成功', array(
            'base' => array(
                "totalPage" => ceil($recordCount / $queryData['page_size']),
                "totalNum" => $recordCount
            ),
            'data' => $recordList
        ));
    }

    /**
     * 释放锁定
     */
    public function ReleaseFrozen($frozenData)
    {
        $businessType = $frozenData['business_type'];
        $businessBn = $frozenData['business_bn'];
        $systemCode = $frozenData['system_code'];
        $memo = isset($frozenData['memo']) && $frozenData['memo'] ? $frozenData['memo'] : "";

        $businessFrozenServer = new BusinessFrozenFlowServer();
        $frozenCodeRes = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn($businessType, $businessBn,
            $systemCode);
        if (!$frozenCodeRes['status']) {
            return $this->Response(false, '锁定流水错误');
        }

        $frozenPoolCodeList = array();
        foreach ($frozenCodeRes['data'] as $frozenPoolRel) {
            $frozenPoolCodeList[] = $frozenPoolRel->frozen_pool_code;
        }

        $accountServer = new AccountServer();

        app('db')->beginTransaction();
        $returnRelease = array();
        $allAccount = array();
        foreach ($frozenPoolCodeList as $poolCode) {
            $releaseRes = $accountServer->Operation('release_frozen', array(
                "frozen_pool_code" => $poolCode,
                'memo' => $memo
            ));
            if (!$releaseRes['status']) {
                app('db')->rollBack();
                return $releaseRes;
            }
            foreach ($releaseRes['data'] as $account => $releaseData) {
                if (isset($returnRelease[$account])) {
                    $returnRelease[$account]['point'] += $releaseData['point'];
                } else {
                    $returnRelease[$account] = array(
                        'point' => $releaseData['point'],
                        'account' => $account
                    );
                }
                $allAccount[] = $account;
            }
        }

        $returnSceneRelease = array();
        $accountList = SceneCompanyRelModel::QueryByAccount(array_unique($allAccount));
        foreach ($returnRelease as $account => $releaseData) {
            $accountInfo = $accountList[$account];
            if (!$accountInfo) {
                app('db')->rollBack();
                return $this->Response(false, '账户信息错误');
            }

            //添加业务流水
            $status = CompanyBusinessRecord::Create(array(
                'system_code' => $systemCode,
                'business_type' => $businessType,
                'business_bn' => $businessBn,
                'company_account_id' => $accountInfo->id,
                'record_type' => 'add',
                'point' => $releaseData['point'],
                'memo' => $memo,
                'created_at' => time()
            ));
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "业务流水添加失败");
            }

            $returnSceneRelease[$accountInfo->scene_id] = array(
                'scene_id' => $accountInfo->scene_id,
                'point' => $releaseData['point'],
            );
        }

        app('db')->commit();
        return $this->Response(true, '锁定释放成功', $returnSceneRelease);
    }

    /**
     * 获取发放详情
     */
    public function AssignList($queryData)
    {
        $sourceBusinessType = $queryData['business_type'];
        $sourceBusinessBn = $queryData['business_bn'];
        $systemCode = $queryData['system_code'];

        $businessFlowServer = new BusinessFlowServer();
        $billListRes = $businessFlowServer->GetBillCodeByBusiness($sourceBusinessType, $sourceBusinessBn, $systemCode);
        if (!$billListRes['status']) {
            return $billListRes;
        }
        $billArr = array();
        foreach ($billListRes['data'] as $billInfo) {
            $billArr[] = $billInfo->bill_code;
        }

        $accountServer = new AccountServer();
        $transferListRes = $accountServer->TransactionQuery('transfer', $billArr);
        if (!$transferListRes['status']) {
            return $transferListRes;
        }

        $memberAccountList = array();
        foreach ($transferListRes['data'] as $transferInfo) {
            $memberAccountList[] = $transferInfo->to_account;
        }

        $busInfo = CompanyBusinessRecord::QueryByBussiness($systemCode, $sourceBusinessType, $sourceBusinessBn);
        $accountRelList = SceneMemberRel::QueryByAccount(array_unique($memberAccountList));

        $returnData = array();
        foreach ($transferListRes['data'] as $transferInfo) {
            $memberInfo = $accountRelList[$transferInfo->to_account];
            $returnData[] = array(
                "system_code" => $busInfo->system_code,
                "business_type" => $busInfo->business_type,
                "business_bn" => $busInfo->business_bn,
                'bill_code' => $transferInfo->bill_code,
                'company_id' => $busInfo->company_id,
                'scene_id' => $busInfo->scene_id,
                'scene_name' => $busInfo->scene_name,
                'member_id' => $memberInfo ? $memberInfo->member_id : '',
                'point' => $transferInfo->point,
                'memo' => $transferInfo->memo,
                'created_at' => $transferInfo->created_at,
            );
        }
        return $this->Response(true, '获取发放详情', array(
            'business_info' => $busInfo,
            'assign_list' => $returnData
        ));
    }
}
