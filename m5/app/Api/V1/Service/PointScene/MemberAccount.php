<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-29
 * Time: 14:50
 */

namespace App\Api\V1\Service\PointScene;

use App\Api\Logic\PointServer\AdaperPoint;
use App\Api\Model\PointScene\MemberBusinessRecord;
use App\Api\Model\PointScene\SceneCompanyRel as SceneCompanyRelModel;
use App\Api\Model\PointScene\SceneMemberRel as SceneMemberRelModel;
use App\Api\Logic\PointScene\RuleAnalysis\ProductRuleAnalysis as ProductRuleAnalysis;
use App\Api\V1\Service\PointServer\Account as AccountServer;
use App\Api\V1\Service\PointScene\BusinessFrozenFlow as BusinessFrozenFlowServer;
use App\Api\Model\PointScene\Scene as SceneModel;
use App\Api\V1\Service\PointScene\BusinessFlow as BusinessFlowServer;
use App\Api\V1\Service\PointServer\FrozenPool as FrozenPoolServer;
use App\Api\Model\PointScene\PointSceneConsume as PointSceneConsumeModel;

class MemberAccount extends Account
{
    public function QueryByAccountList($accountList)
    {
        $accountList = SceneMemberRelModel::QueryByAccount($accountList);
        if (!$accountList) {
            return $this->Response(false, '账户查询失败');
        }
        return $this->Response(true, '成功', $accountList);
    }

    /**
     * 查询公司用户下所有场景积分账户
     */
    public function QueryAll($queryFilter)
    {
        $companyId  = $queryFilter['company_id'];
        $memberId   = $queryFilter['member_id'];
        $companyRel = SceneCompanyRelModel::FindByCompany($companyId);
        if ($companyRel->count() <= 0) {
            return $this->Response(false, '尚未关联场景', array());
        }

        $accountInfoArr = array();
        foreach ($companyRel as $relInfo) {
            //初始化账户数据
            $accountInfoArr[$relInfo->scene_id] = array(
                'account'               => $relInfo->scene_id,
                'account_name'          => $relInfo->scene_name,
                'money'                 => 0,
                'used_money'            => 0,
                'frozen_money'          => 0,
                'overdue_money'         => 0,
                'point'                 => 0,
                'used_point'            => 0,
                'frozen_point'          => 0,
                'overdue_point'         => 0,
                'rule_bns'              => [],
                'earliest_overdue_time' => 0
            );
        }

        $sceneAccountList = [];

        $accountRel = SceneMemberRelModel::QueryByMember($companyId, $memberId);
        foreach ($accountRel as $relInfo) {
            //获取所有account
            if ($relInfo->account) {
                $sceneAccountList[$relInfo->scene_id] = $relInfo->account;
            }
        }

        $accountServer = new AccountServer();
        $accountRes    = $accountServer->QueryBatch($sceneAccountList);
        if ($accountRes['data']) {
            foreach ($accountRes['data'] as $accountInfo) {
                $sceneId = array_search($accountInfo->account, $sceneAccountList);

                $accountInfoArr[$sceneId]['money']                 = $this->point2money($accountInfo->point);
                $accountInfoArr[$sceneId]['used_money']            = $this->point2money($accountInfo->used_point);
                $accountInfoArr[$sceneId]['frozen_money']          = $this->point2money($accountInfo->frozen_point);
                $accountInfoArr[$sceneId]['overdue_money']         = $this->point2money($accountInfo->overdue_point);
                $accountInfoArr[$sceneId]['point']                 = $accountInfo->point;
                $accountInfoArr[$sceneId]['used_point']            = $accountInfo->used_point;
                $accountInfoArr[$sceneId]['frozen_point']          = $accountInfo->frozen_point;
                $accountInfoArr[$sceneId]['overdue_point']         = $accountInfo->overdue_point;
                $accountInfoArr[$sceneId]['rule_bns']              = $accountRel[$sceneId]->rule_bns;
                $accountInfoArr[$sceneId]['earliest_overdue_time'] = $accountInfo->earliest_overdue_time;
            }
        }

        return $this->Response(true, '查询成功', $accountInfoArr);
    }

    public function QueryByOverdueTime($queryFilter)
    {
        $companyId  = $queryFilter['company_id'];
        $memberId   = $queryFilter['member_id'];
        $accountRel = SceneMemberRelModel::QueryByMember($companyId, $memberId);
        if (!$accountRel) {
            return $this->Response(false, '用户尚未关联场景', array());
        }

        $sceneInfoList    = array();
        $sceneAccountList = array();
        foreach ($accountRel as $relInfo) {
            if ($relInfo->account) {
                $sceneInfoList[$relInfo->scene_id]    = $relInfo->account_name;
                $sceneAccountList[$relInfo->scene_id] = $relInfo->account;
            }
        }

        $accountServer  = new AccountServer();
        $accountRes     = $accountServer->QueryByOverdueTime($sceneAccountList, array(
            'start_time' => isset($queryFilter['start_time']) && $queryFilter['start_time'] ? $queryFilter['start_time'] : time(),
            'end_time'   => $queryFilter['end_time']
        ));
        $accountInfoArr = array();
        if ($accountRes['data']) {
            foreach ($accountRes['data'] as $accountInfo) {
                $sceneId          = array_search($accountInfo->account, $sceneAccountList);
                $accountInfoArr[] = array(
                    'scene_id'     => $sceneId,
                    'scene_name'   => $sceneInfoList[$sceneId],
                    'money'        => $this->point2money($accountInfo->point),
                    'used_money'   => $this->point2money($accountInfo->used_point),
                    'frozen_money' => $this->point2money($accountInfo->frozen_point),
                    'point'        => $accountInfo->point,
                    'used_point'   => $accountInfo->used_point,
                    'frozen_point' => $accountInfo->frozen_point,
                    'overdue_time' => $accountInfo->overdue_time,
                );

            }
        }

        return $this->Response(true, '查询成功', $accountInfoArr);

    }

    public function GetMemberSceneAccount($companyId, $memberId, $sceneId)
    {
        //查询账户
        $memberAccountRel = SceneMemberRelModel::FindByMemberAndScene($companyId, $memberId, $sceneId);
        $accountServer    = new AccountServer();
        if ($memberAccountRel && $memberAccountRel->account) {
            $account         = $memberAccountRel->account;
            $memberAccountId = $memberAccountRel->id;
        } else {
            if ($memberAccountRel) {
                $relId = $memberAccountRel->id;
            } else {
                //如果用户没有场景关联，查看公司是否有关联，有的话直接创建场景关联
                $companyAccountRel = SceneCompanyRelModel::FindByCompanyAndScene($companyId, $sceneId);
                if (!$companyAccountRel) {
                    return $this->Response(false, "公司未关联场景");
                }

                $relId = SceneMemberRelModel::Create(array(
                    'company_id'    => $companyId,
                    'member_id'     => $memberId,
                    'scene_id'      => $sceneId,
                    'exchange_rate' => $companyAccountRel->exchange_rate
                ));
                if (!$relId) {
                    return $this->Response(false, "用户关联场景关联失败");
                }
            }

            //如果没有生成过账户则生成并绑定账户
            app('db')->beginTransaction();
            $createRes = $accountServer->Create();
            if (!$createRes['status']) {
                app('db')->rollBack();
                return $this->Response(false, $createRes['msg']);
            }
            $account = $createRes['data']['account'];
            $bindRes = SceneMemberRelModel::BindAccount($relId, $account);
            if (!$bindRes) {
                app('db')->rollBack();
                return $this->Response(false, "用户场景账户绑定失败");
            }

            app('db')->commit();
            $memberAccountId = $relId;
        }
        return $this->Response(true, "获取成功", array(
            "account"           => $account,
            'member_account_id' => $memberAccountId
        ));
    }

    public function CreateOrder($frozenData)
    {
        $businessType     = $frozenData['business_type'];
        $businessBn       = $frozenData['business_bn'];
        $systemCode       = $frozenData['system_code'];
        $companyId        = $frozenData['company_id'];
        $memberId         = $frozenData['member_id'];
        $accountList      = $frozenData['account_list'];
        $overdueTime      = $frozenData['overdue_time'];
        $totalFrozenPoint = $frozenData['point'];
        $totalFrozenMoney = $frozenData['money'];
        $memo             = $frozenData['memo'] ? $frozenData['memo'] : "";
        $sceneIdArr       = [];
        $frozenInfoArr    = [];

        $point_tmp        = $this->money2point($totalFrozenMoney);
        $point_tmp        = round($point_tmp, $frozenData['float_lenght']);
        $totalFrozenPoint = round($totalFrozenPoint, $frozenData['float_lenght']);
        if ($point_tmp != $totalFrozenPoint) {
            return $this->Response(false, "现金与积分不匹配");
        }

        foreach ($accountList as $account) {
            $sceneIdArr[]                       = $account['account'];
            $frozenInfoArr[$account['account']] = $account;
        }

        //查询账户
        $accountListRel = SceneMemberRelModel::FindByCompanyAndSceneList(
            $companyId,
            $memberId,
            array_unique($sceneIdArr)
        );
        if (!$accountListRel || count($accountListRel) != count($sceneIdArr)) {
            return $this->Response(false, "用户场景账户余额不足");
        }

        $frozenAccountList = array();
        foreach ($accountListRel as $accountInfo) {
            if (!$accountInfo->account) {
                return $this->Response(false, "用户场景账户余额不足");
            }
            $frozenInfoArr[$accountInfo->scene_id]['member_account_id'] = $accountInfo->id;
            $frozenInfoArr[$accountInfo->scene_id]['account']           = $accountInfo->account;

            $frozenAccountList[$accountInfo->account] = $frozenInfoArr[$accountInfo->scene_id];
        }

        $accountServer    = new AccountServer();
        $frozenReturnData = array();
        app('db')->beginTransaction();

        //添加业务流水
        foreach ($frozenAccountList as $account => $frozenInfo) {
            $frozenReturnData[$frozenInfo['account']] = [
                'account' => $frozenInfo['account'],
                'money'   => $this->point2money($frozenInfo['point']),
                'point'   => $frozenInfo['point'],
            ];

            $accountInfo = $accountServer->GetAccountInfo($frozenInfo['account']);

            $status = MemberBusinessRecord::Create([
                'system_code'       => $systemCode,
                'business_type'     => $businessType,
                'business_bn'       => $businessBn,
                'member_account_id' => $frozenInfo['member_account_id'],
                'record_type'       => 'reduce',
                'before_point'      => $accountInfo->point,
                'point'             => $frozenInfo['point'],
                'after_point'       => $accountInfo->point - $frozenInfo['point'],
                'memo'              => $memo,
                'created_at'        => time()
            ]);
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
        $frozenRes     = $accountServer->Operation('create_frozen', array(
            "total_point"         => $totalFrozenPoint,
            "overdue_time"        => $overdueTime,
            "frozen_account_list" => $frozenAccountList,
            "memo"                => $memo
        ));
        if (!$frozenRes['status']) {
            app('db')->rollBack();
            return $frozenRes;
        }

        $frozenPoolCode = $frozenRes['data']['frozen_pool_code'];

        $businessFrozenFlowServer = new BusinessFrozenFlowServer();
        $bfCreateRes              = $businessFrozenFlowServer->Create(array(
            "business_type" => $businessType,
            "business_bn"   => $businessBn,
            "system_code"   => $systemCode
        ));
        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $businessFrozenCode = $bfCreateRes['data']['business_frozen_code'];

        $bindRes = $businessFrozenFlowServer->BindFrozenPoolCode($businessFrozenCode, $frozenPoolCode);
        if (!$bindRes['status']) {
            app('db')->rollBack();
            return $bindRes;
        }

        app('db')->commit();
        return $this->Response(true, "冻结成功", array(
            'trade_no'    => $businessFrozenCode,
            'frozen_data' => $frozenReturnData
        ));
    }

    public function OrderConfirm($confirmData)
    {
        $businessType       = $confirmData['business_type'];
        $businessBn         = $confirmData['business_bn'];
        $sourceBusinessType = $confirmData['source_business_type'];
        $sourceBusinessBn   = $confirmData['source_business_bn'];
        $systemCode         = $confirmData['system_code'];
        $companyId          = $confirmData['company_id'];
        $memberId           = $confirmData['member_id'];
        $accountList        = $confirmData['account_list'];
        $memo               = $confirmData['memo'] ? $confirmData['memo'] : '';
        $totalPoint         = $confirmData['point'];
        $listTotalPoint     = 0;
        foreach ($accountList as $consumeInfo) {
            $listTotalPoint += $consumeInfo['point'];
        }
        if ($totalPoint != $listTotalPoint) {
            return $this->Response(false, '总数核对错误');
        }

        $businessFrozenServer = new BusinessFrozenFlowServer();
        $frozenCodeRes        = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn(
            $sourceBusinessType,
            $sourceBusinessBn,
            $systemCode
        );

        if (!$frozenCodeRes['status']) {
            return $this->Response(false, '锁定流水错误');
        }

        $frozenPoolCodeList = array();
        foreach ($frozenCodeRes['data'] as $frozenPoolRel) {
            $frozenPoolCodeList[] = $frozenPoolRel->frozen_pool_code;
        }

        if (!$frozenPoolCodeList) {
            return $this->Response(false, '锁定池获取失败');
        }

        $accountServer = new AccountServer();
        app('db')->beginTransaction();
        //创建业务流水
        $businessFlowServer = new BusinessFlowServer();
        $bfCreateRes        = $businessFlowServer->Create(array(
            "business_type" => $businessType,
            "business_bn"   => $businessBn,
            "system_code"   => $systemCode
        ));
        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        foreach ($accountList as $consumeInfo) {
            $sceneId          = $consumeInfo['account'];
            $consumePoint     = $consumeInfo['point'];
            $memberAccountRes = $this->GetMemberSceneAccount($companyId, $memberId, $sceneId);
            if (!$memberAccountRes['status']) {
                app('db')->rollBack();
                return $memberAccountRes;
            }
            $memberAccount = $memberAccountRes['data']['account'];
            $consumeRes    = $accountServer->Transaction('consume', array(
                "frozen_pool_code_list" => $frozenPoolCodeList,
                "account"               => $memberAccount,
                "point"                 => $consumePoint,
                "memo"                  => $memo,
            ));
            if (!$consumeRes['status']) {
                app('db')->rollBack();
                return $consumeRes;
            }
            $billCode    = $consumeRes['data']['bill_code'];
            $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);
            if (!$bindBillRes['status']) {
                app('db')->rollBack();
                return $bindBillRes;
            }
        }

        app('db')->commit();
        return $this->Response(true, "消费成功", array('flow_code' => $businessFlowCode));
    }

    public function OrderRefund($refundData)
    {
        $businessType       = $refundData['business_type'];
        $businessBn         = $refundData['business_bn'];
        $systemCode         = $refundData['system_code'];
        $sourceBusinessType = $refundData['source_business_type'];
        $sourceBusinessBn   = $refundData['source_business_bn'];
        $accountList        = $refundData['account_list'];
        $companyId          = $refundData['company_id'];
        $memberId           = $refundData['member_id'];
        $memo               = $refundData['memo'] ? $refundData['memo'] : "";

        $businessFlowServer = new BusinessFlowServer();
        $relListRes         = $businessFlowServer->GetBillCodeByBusiness($sourceBusinessType, $sourceBusinessBn,
            $systemCode);
        if (!$relListRes['status']) {
            return $relListRes;
        }

        $accountRefundData = array();
        foreach ($accountList as $refund) {
            $sceneId          = $refund['account'];
            $memberAccountRes = $this->GetMemberSceneAccount($companyId, $memberId, $sceneId);
            if (!$memberAccountRes['status']) {
                return $memberAccountRes;
            }

            $memberAccount = $memberAccountRes['data']['account'];

            $accountRefundData[$memberAccount] = [
                'scene_id'          => $sceneId,
                'point'             => $refund['point'],
                'money'             => $refund['money'],
                'member_account_id' => $memberAccountRes['data']['member_account_id'],
            ];
        }

        $accountServer = new AccountServer();
        app('db')->beginTransaction();
        //创建业务流水
        $businessFlowServer = new BusinessFlowServer();
        $bfCreateRes        = $businessFlowServer->Create(array(
            "business_type" => $businessType,
            "business_bn"   => $businessBn,
            "system_code"   => $systemCode
        ));
        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        foreach ($accountRefundData as $account => $refundDataItem) {
            foreach ($relListRes['data'] as $billRel) {
                $memberPointAccount = $accountServer->GetAccountInfo($account);

                $consumeBillCode = $billRel->bill_code;
                $returnRes       = $accountServer->Transaction('refund', array(
                    "consume_bill_code" => $consumeBillCode,
                    "account"           => $account,
                    "refund_data"       => $refundDataItem,
                    "memo"              => $memo
                ));
                if (!$returnRes['status'] && $returnRes['code'] == 10000) {
                    continue;
                }
                if ($returnRes['status']) {

                    //添加业务流水
                    $status = MemberBusinessRecord::Create([
                        'system_code'       => $systemCode,
                        'business_type'     => $businessType,
                        'business_bn'       => $businessBn,
                        'member_account_id' => $refundDataItem['member_account_id'],
                        'record_type'       => 'add',
                        'before_point'      => $memberPointAccount->point,
                        'point'             => $refundDataItem['point'],
                        'after_point'       => $memberPointAccount->point + $refundDataItem['point'],
                        'memo'              => (!empty($memo)) ? $memo:"订单退款,订单号：" . $sourceBusinessBn,
                        'created_at'        => time()
                    ]);
                    if (!$status) {
                        app('db')->rollBack();
                        return $this->Response(false, "业务流水添加失败");
                    }

                    $billCode    = $returnRes['data']['bill_code'];
                    $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);
                    if (!$bindBillRes['status']) {
                        app('db')->rollBack();
                        return $bindBillRes;
                    }
                    break;
                } else {
                    app('db')->rollBack();
                    return $returnRes;
                }
            }
        }
        app('db')->commit();
        return $this->Response(true, "退款成功", array(
            "trade_no" => $businessFlowCode
        ));
    }

    public function RecordList($queryData)
    {
        $systemCode = $queryData['system_code'];
        $companyIds = $queryData['company_ids'];
        $memberIds  = $queryData['member_ids'];
        $sceneIds   = $queryData['scene_ids'];
        $businessType   = $queryData['business_type'];

        $where = array(
            'begin_time'  => isset($queryData['begin_time']) ? $queryData['begin_time'] : 0,
            'end_time'    => isset($queryData['end_time']) ? $queryData['end_time'] : 0,
            'search_key'  => isset($queryData['search_key']) ? $queryData['search_key'] : '',
            'record_type' => isset($queryData['record_type']) ? $queryData['record_type'] : 'all'
        );
        if ($systemCode) {
            $where['system_code'] = $systemCode;
        }
        if ($businessType) {
            $where['business_type'] = $businessType;
        }

        $where['scene_ids'] = $sceneIds;

        $recordCount = MemberBusinessRecord::QueryCount($companyIds, $memberIds, $where);

        if (!$recordCount) {
            return $this->Response(true, '无数据', array(
                'base' => array(
                    "totalPage" => 0,
                    "totalNum"  => 0
                ),
                'data' => []
            ));
        }

        $recordList = MemberBusinessRecord::Query(
            $companyIds,
            $memberIds,
            $where,
            $queryData['page'],
            $queryData['page_size']
        );

        foreach ($recordList as &$item) {
            $item['money'] = $this->point2money($item['point']);
            $item['before_money'] = $this->point2money($item['before_point']);
            $item['after_money'] = $this->point2money($item['after_point']);
        }

        return $this->Response(true, '查询成功', array(
            'base' => array(
                "totalPage" => ceil($recordCount / $queryData['page_size']),
                "totalNum"  => $recordCount
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
        $businessBn   = $frozenData['business_bn'];
        $systemCode   = $frozenData['system_code'];
        $memo         = isset($frozenData['memo']) && $frozenData['memo'] ? $frozenData['memo'] : "";

        $businessFrozenServer = new BusinessFrozenFlowServer();
        $frozenCodeRes        = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn(
            $businessType,
            $businessBn,
            $systemCode
        );
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
        $allAccount    = array();
        foreach ($frozenPoolCodeList as $poolCode) {
            $releaseRes = $accountServer->Operation('release_frozen', array(
                "frozen_pool_code" => $poolCode
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
                        'point'   => $releaseData['point'],
                        'account' => $account
                    );
                }
                $allAccount[] = $account;
            }
        }

        $returnSceneRelease = array();
        $accountList        = SceneMemberRelModel::QueryByAccount(array_unique($allAccount));
        foreach ($returnRelease as $account => $releaseData) {
            $accountInfo = $accountList[$account];
            if (!$accountInfo) {
                app('db')->rollBack();
                return $this->Response(false, '账户信息错误');
            }

            $accountInfo = $accountServer->GetAccountInfo($account);

            //添加业务流水
            $status = MemberBusinessRecord::Create(array(
                'system_code'       => $systemCode,
                'business_type'     => 'cancelOrder',
                'business_bn'       => $businessBn,
                'member_account_id' => $accountInfo->id,
                'record_type'       => 'add',
                'before_point'      => $accountInfo->point - $releaseData['point'],
                'point'             => $releaseData['point'],
                'after_point'       => $accountInfo->point,
                'memo'              => $memo,
                'created_at'        => time()
            ));
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "业务流水添加失败");
            }

            $returnSceneRelease[$accountInfo->scene_id] = array(
                'account' => $accountInfo->scene_id,
                'point'   => $releaseData['point'],
                'money'   => $this->point2money($releaseData['point']),
            );
        }

        app('db')->commit();
        return $this->Response(true, '锁定释放成功', $returnSceneRelease);
    }

    /**
     * 获取锁定记录
     * @param $frozenData
     * @return array
     */
    public function getFrozen($frozenData)
    {
        $businessType = $frozenData['business_type'];
        $businessBn   = $frozenData['business_bn'];
        $systemCode   = $frozenData['system_code'];

        $businessFrozenServer = new BusinessFrozenFlowServer();
        $frozenCodeRes        = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn(
            $businessType,
            $businessBn,
            $systemCode
        );
        if (!$frozenCodeRes['status']) {
            return $this->Response(false, '锁定流水错误');
        }

        $frozenPoolCodeList = [];
        $poolArr            = [];
        foreach ($frozenCodeRes['data'] as $frozenPoolRel) {
            $poolArr[$frozenPoolRel->frozen_pool_code] = $frozenPoolRel->business_frozen_code;
            $frozenPoolCodeList[]                      = $frozenPoolRel->frozen_pool_code;
        }

        $frozenPoolServer = new FrozenPoolServer();

        $frozenRes = $frozenPoolServer->getFrozenPoolAssets($frozenPoolCodeList);
        if (!$frozenRes['status']) {
            return $frozenRes;
        }
        $frozenData = [];
        foreach ($frozenRes['data'] as $frozenInfo) {
            if (isset($frozenData[$frozenInfo->account_id])) {
                $frozenData[$frozenInfo->account_id]['frozen_point']  += $frozenInfo->frozen_point;
                $frozenData[$frozenInfo->account_id]['finish_point']  += $frozenInfo->finish_point;
                $frozenData[$frozenInfo->account_id]['release_point'] += $frozenInfo->release_point;
                $frozenData[$frozenInfo->account_id]['frozen_money']  = $this->point2money($frozenData[$frozenInfo->account_id]['frozen_point']);
                $frozenData[$frozenInfo->account_id]['finish_money']  = $this->point2money($frozenData[$frozenInfo->account_id]['finish_point']);
                $frozenData[$frozenInfo->account_id]['release_money'] = $this->point2money($frozenData[$frozenInfo->account_id]['release_point']);
            } else {
                $frozenData[$frozenInfo->account_id] = [
                    "business_frozen_code" => $poolArr[$frozenInfo->frozen_pool_code],
                    "account_id"           => $frozenInfo->account_id,
                    "account"              => $frozenInfo->account,
                    "status"               => $frozenInfo->status,
                    "frozen_point"         => $frozenInfo->frozen_point,
                    "finish_point"         => $frozenInfo->finish_point,
                    "release_point"        => $frozenInfo->release_point,
                    'frozen_money'         => $this->point2money($frozenInfo->frozen_point),
                    'finish_money'         => $this->point2money($frozenInfo->finish_point),
                    'release_money'        => $this->point2money($frozenInfo->release_point),
                ];
            }
        }
        return $this->Response(true, '', array_values($frozenData));
    }

    /**
     * 查询公司用户下所有场景积分账户
     */
    public function QueryAllCompany($queryFilter, $page = 1, $pageSize = 10)
    {
        $memberId = $queryFilter['member_id'];

        $count = SceneMemberRelModel::QueryCountByMemberId($memberId);
        if ($count <= 0) {
            return $this->Response(true, '查询成功', [
                'total_page' => 0,
                'total_num'  => 0,
                'list'       => []
            ]);
        }

        $accountRel       = SceneMemberRelModel::QueryByMemberId($memberId, $page, $pageSize);
        $sceneAccountList = [];
        foreach ($accountRel as $relInfo) {
            //获取所有account
            if ($relInfo->account) {
                $sceneAccountList[$relInfo->company_id . '_' . $relInfo->scene_id] = $relInfo->account;
            }
        }

        $accountInfoArr = [];
        $accountServer  = new AccountServer();
        $accountRes     = $accountServer->QueryBatch($sceneAccountList);
        if ($accountRes['data']) {
            foreach ($accountRes['data'] as $accountInfo) {
                $sceneId = array_search($accountInfo->account, $sceneAccountList);

                $accountInfoArr[$sceneId]['company_id']            = $accountRel[$sceneId]->company_id;
                $accountInfoArr[$sceneId]['member_id']             = $accountRel[$sceneId]->member_id;
                $accountInfoArr[$sceneId]['scene_id']              = $accountRel[$sceneId]->scene_id;
                $accountInfoArr[$sceneId]['account']               = $accountRel[$sceneId]->account;
                $accountInfoArr[$sceneId]['account_name']          = $accountRel[$sceneId]->account_name;
                $accountInfoArr[$sceneId]['money']                 = $this->point2money($accountInfo->point);
                $accountInfoArr[$sceneId]['used_money']            = $this->point2money($accountInfo->used_point);
                $accountInfoArr[$sceneId]['frozen_money']          = $this->point2money($accountInfo->frozen_point);
                $accountInfoArr[$sceneId]['overdue_money']         = $this->point2money($accountInfo->overdue_point);
                $accountInfoArr[$sceneId]['point']                 = $accountInfo->point;
                $accountInfoArr[$sceneId]['used_point']            = $accountInfo->used_point;
                $accountInfoArr[$sceneId]['frozen_point']          = $accountInfo->frozen_point;
                $accountInfoArr[$sceneId]['overdue_point']         = $accountInfo->overdue_point;
                $accountInfoArr[$sceneId]['rule_bns']              = $accountRel[$sceneId]->rule_bns;
                $accountInfoArr[$sceneId]['earliest_overdue_time'] = $accountInfo->earliest_overdue_time;
            }
        }

        return $this->Response(true, '查询成功', [
            'total_page' => ceil($count / $pageSize),
            'total_num'  => $count,
            'list'       => $accountInfoArr
        ]);
    }


    public function QueryByCompany($queryFilter, $page = 1, $pageSize = 10)
    {
        $memberId = $queryFilter['member_id'];
        $companyId = $queryFilter['company_id'];

        $count = SceneMemberRelModel::QueryCountByCompanyIdMemberId($companyId,$memberId);
        if ($count <= 0) {
            return $this->Response(true, '查询成功', [
                'total_page' => 0,
                'total_num'  => 0,
                'list'       => []
            ]);
        }

        $accountRel       = SceneMemberRelModel::QueryCompanyIdMemberId($companyId,$memberId, $page, $pageSize);
        $sceneAccountList = [];
        foreach ($accountRel as $relInfo) {
            //获取所有account
            if ($relInfo->account) {
                $sceneAccountList[$relInfo->company_id . '_' . $relInfo->scene_id] = $relInfo->account;
            }
        }

        $accountInfoArr = [];
        $accountServer  = new AccountServer();
        $accountRes     = $accountServer->QueryBatch($sceneAccountList);
        if ($accountRes['data']) {
            foreach ($accountRes['data'] as $accountInfo) {
                $sceneId = array_search($accountInfo->account, $sceneAccountList);

                $accountInfoArr[$sceneId]['company_id']            = $accountRel[$sceneId]->company_id;
                $accountInfoArr[$sceneId]['member_id']             = $accountRel[$sceneId]->member_id;
                $accountInfoArr[$sceneId]['scene_id']              = $accountRel[$sceneId]->scene_id;
                $accountInfoArr[$sceneId]['account']               = $accountRel[$sceneId]->account;
                $accountInfoArr[$sceneId]['account_name']          = $accountRel[$sceneId]->account_name;
                $accountInfoArr[$sceneId]['money']                 = $this->point2money($accountInfo->point);
                $accountInfoArr[$sceneId]['used_money']            = $this->point2money($accountInfo->used_point);
                $accountInfoArr[$sceneId]['frozen_money']          = $this->point2money($accountInfo->frozen_point);
                $accountInfoArr[$sceneId]['overdue_money']         = $this->point2money($accountInfo->overdue_point);
                $accountInfoArr[$sceneId]['point']                 = $accountInfo->point;
                $accountInfoArr[$sceneId]['used_point']            = $accountInfo->used_point;
                $accountInfoArr[$sceneId]['frozen_point']          = $accountInfo->frozen_point;
                $accountInfoArr[$sceneId]['overdue_point']         = $accountInfo->overdue_point;
                $accountInfoArr[$sceneId]['rule_bns']              = $accountRel[$sceneId]->rule_bns;
                $accountInfoArr[$sceneId]['earliest_overdue_time'] = $accountInfo->earliest_overdue_time;
            }
        }

        return $this->Response(true, '查询成功', [
            'total_page' => ceil($count / $pageSize),
            'total_num'  => $count,
            'list'       => $accountInfoArr
        ]);
    }

    /**
     * 查询场景积分消费列表
     *
     * @param array   $queryFilter
     * @param integer $page
     * @param integer $pageSize
     * @return array
     */
    public function GetScenePointConsumeList($queryFilter)
    {
        $page = !empty($queryFilter['page']) ? $queryFilter['page'] : 1;
        $pageSize = !empty($queryFilter['pageSize']) ? $queryFilter['pageSize'] : 10;

        $pointSceneConsumeModel = new PointSceneConsumeModel($queryFilter);
        $totalCount = $pointSceneConsumeModel->queryCount();

        if (!$totalCount) {
            return $this->Response(true, '查询成功', [
                'page'       => $page,
                'totalCount' => 0,
                'totalPage'  => 0,
                'list'       => [],
            ]);
        }

        $point_scene_consumes = $pointSceneConsumeModel->getRows($page, $pageSize);

        return $this->Response(true, '查询成功', [
            'page'       => $page,
            'totalCount' => $totalCount,
            'totalPage'  => ceil($totalCount / $pageSize),
            'list'       => !empty($point_scene_consumes) ? $point_scene_consumes : [],
        ]);
    }

    // 积分转账 - 冻结
    public function TransferFrozen($frozenData){
        $businessType = $frozenData['business_type'];

        $businessBn = $frozenData['business_bn'];

        $systemCode = $frozenData['system_code'];

        $payCompanyId = $frozenData['pay_company_id'];

        $payMemberId = $frozenData['pay_member_id'];

        $sceneId = $frozenData['scene_id'];

        $transferPoint = $frozenData['transfer_point'];

        $overdueTime = $frozenData['overdue_time'];

        $memo = $frozenData['memo'] ?? '';

        $frozenInfoArr[$sceneId]['point'] = $transferPoint;
        $frozenInfoArr[$sceneId]['scene_id'] = $sceneId;

        // 付款账户 - 用户 & 场景关联的账户code
        $payMemberAccountRel = SceneMemberRelModel::FindByMemberAndScene($payCompanyId, $payMemberId, $sceneId);

        if (!$payMemberAccountRel || !$payMemberAccountRel->account){
            return $this->Response(false, '用户主账户未关联场景');
        }

        $frozenInfoArr[$sceneId]['pay_member_scene_account_rel_id'] = $payMemberAccountRel->id;
        $frozenInfoArr[$sceneId]['account'] = $payMemberAccountRel->account;

        // 冻结付款账户列表
        $frozenAccountList[$payMemberAccountRel->account] = $frozenInfoArr[$sceneId];

        app('db')->beginTransaction();

        $accountServer = new AccountServer();

        // 创建 用户业务流水记录
        foreach ($frozenAccountList as $accountItem){
            $payAccountInfo = $accountServer->GetAccountInfo($accountItem['account']);

            $status = MemberBusinessRecord::Create([
                'system_code'       => $systemCode,
                'business_type'     => $businessType,
                'business_bn'       => $businessBn,
                'member_account_id' => $accountItem['pay_member_scene_account_rel_id'],
                'record_type'       => 'reduce',
                'before_point'      => $payAccountInfo->point,
                'point'             => $accountItem['point'],
                'after_point'       => $payAccountInfo->point - $accountItem['point'],
                'memo'              => $memo,
                'created_at'        => time(),
            ]);

            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "创建业务流水失败");
            }
        }

        // 执行积分冻结
        $createFrozenRes = $accountServer->Operation('create_frozen', [
            'total_point'         => $transferPoint,
            'overdue_time'        => $overdueTime,
            'frozen_account_list' => $frozenAccountList,
            'memo'                => $memo,
        ]);

        if (!$createFrozenRes['status']){
            app('db')->rollBack();
            return $createFrozenRes;
        }

        $frozenPoolCode = $createFrozenRes['data']['frozen_pool_code'];

        $businessFrozenFlowServer = new BusinessFrozenFlowServer();

        // 创建 积分业务冻结流水
        $bfCreateRes = $businessFrozenFlowServer->Create([
            'business_type' => $businessType,
            'business_bn'   => $businessBn,
            'system_code'   => $systemCode,
        ]);

        if (!$bfCreateRes['status']){
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $businessFrozenCode = $bfCreateRes['data']['business_frozen_code'];

        // 创建 积分业务冻结code & 积分冻结池code 关联关系
        $bindPoolRelRes = $businessFrozenFlowServer->BindFrozenPoolCode($businessFrozenCode, $frozenPoolCode);

        if (!$bindPoolRelRes['status']){
            app('db')->rollBack();
            return $bindPoolRelRes;
        }

        app('db')->commit();

        return $this->Response(true, '冻结成功', ['flow_code' => $businessFrozenCode]);
    }

    // 积分转账 - 释放冻结
    public function ReleaseTransferFrozen($frozenData){
        $businessType = $frozenData['business_type'];

        $businessBn = $frozenData['business_bn'];

        $systemCode = $frozenData['system_code'];

        $memo = $frozenData['memo'] ?? '';

        $businessFrozenServer = new BusinessFrozenFlowServer();

        // 根据积分冻结业务类型 & 外部业务bn 获取积分冻结池code
        $businessFrozenPoolRel = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn($businessType, $businessBn, $systemCode);

        if (!$businessFrozenPoolRel['status']){
            return $this->Response(false, '锁定流水错误');
        }

        $frozenPoolCodeList = [];

        foreach ($businessFrozenPoolRel['data'] as $item){
            $frozenPoolCodeList[] = $item->frozen_pool_code;
        }

        $accountServer = new AccountServer();

        // 开始事务
        app('db')->beginTransaction();

        $releaseAccountPoint = [];
        $allAccountList = [];

        foreach ($frozenPoolCodeList as $fronzenPoolCode){
            $releaseRes = $accountServer->Operation('release_frozen', ['frozen_pool_code' => $fronzenPoolCode, 'memo' => $memo]);

            if (!$releaseRes['status']){
                app('db')->rollBack();
                return $releaseRes;
            }

            foreach ($releaseRes['data'] as $account => $info){
                $allAccountList[] = $account;

                if (!isset($releaseAccountPoint[$account])){
                    $releaseAccountPoint[$account] = [
                        'point' => $info['point'],
                        'account' => $info['account'],
                    ];

                    continue;
                }

                $releaseAccountPoint[$account]['point'] += $info['point'];
            }
        }

        $returnReleaseData = [];

        $sceneMemberRelList = SceneMemberRelModel::QueryByAccount(array_unique($allAccountList));

        $AdaperPointObj = AdaperPoint::getInstance();

        foreach ($releaseAccountPoint as $account => $info){
            $sceneMemberRelInfo = $sceneMemberRelList[$account];

            if (!$sceneMemberRelInfo){
                app('db')->rollBack();
                return $this->Response(false, '用户关联的场景账户不存在');
            }

            $accountInfo = $accountServer->GetAccountInfo($account);

            // 创建 用户积分业务流水记录
            $createRes = MemberBusinessRecord::Create([
                'system_code'       => $systemCode,
                'business_type'     => 'cancelPointTransfer',
                'business_bn'       => $businessBn,
                'member_account_id' => $sceneMemberRelInfo->id,
                'record_type'       => 'add',
                'before_point'      => $accountInfo->point - $info['point'],
                'point'             => $info['point'],
                'after_point'       => $accountInfo->point,
                'memo'              => $memo,
                'created_at'        => time(),
            ]);

            if (!$createRes){
                app('db')->rollBack();
                return $this->Response(false, '创建用户积分业务流水失败');
            }

            $returnReleaseData[$account] = [
                'account' => $account,
                'scene_id' => $sceneMemberRelInfo->scene_id,
                'point' => $AdaperPointObj->GetPoint($info['point'], $frozenData['channel'], AdaperPoint::RATE_TYPE_OUT),
                'money' => $this->point2money($info['point']),
            ];
        }

        app('db')->commit();

        return $this->Response(true, '转账积分冻结释放成功', $returnReleaseData);
    }

    // 积分转账 - 转账
    public function ScenePointTransfer($transferData){
        $businessType = $transferData['business_type'];

        $businessBn = $transferData['business_bn'];

        $systemCode = $transferData['system_code'];

        $payCompanyId = $transferData['pay_company_id'];

        $payMemberId = $transferData['pay_member_id'];

        $receiveCompanyId = $transferData['receive_company_id'];

        $receiveMemberId = $transferData['receive_member_id'];

        $sceneId = $transferData['scene_id'];

        $transferPoint = $transferData['transfer_point'];

        $businessFrozenCode = $transferData['business_frozen_code'];

        $overdueTime = $transferData['overdue_time'];

        $overdueFunc = $transferData['overdue_func'];

        $memo = $transferData['memo'] ?? '';

        // 付款账户 - 用户 & 场景关联的账户code
        $payMemberAccountRel = SceneMemberRelModel::FindByMemberAndScene($payCompanyId, $payMemberId, $sceneId);

        if (!$payMemberAccountRel || !$payMemberAccountRel->account){
            return $this->Response(false, '用户主账户未关联场景');
        }

        $payAccountCode = $payMemberAccountRel->account;

        // 根据积分业务冻结code 获取 积分冻结池code
        $businessFrozenServer = new BusinessFrozenFlowServer();

        $businessFrozenPoolRel = $businessFrozenServer->GetBusinessFrozenPoolCode($businessFrozenCode);

        if (!$businessFrozenPoolRel['status']){
            return $this->Response(false, '锁定流水错误');
        }

        $frozenPoolCodeList = [];

        foreach ($businessFrozenPoolRel['data'] as $item){
            $frozenPoolCodeList[] = $item->frozen_pool_code;
        }

        if (empty($frozenPoolCodeList)){
            return $this->Response(false, '锁定流水错误');
        }

        // 创建积分业务流水
        $businessFlowServer = new BusinessFlowServer();

        $bfCreateRes = $businessFlowServer->Create(array(
            "business_type" => $businessType,
            "business_bn"   => $businessBn,
            "system_code"   => $systemCode
        ));

        if (!$bfCreateRes['status']){
            return $bfCreateRes;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        // 开始事务
        app('db')->beginTransaction();

        // 中断标识
        $interrupt = 0;
        $interrupt_msg = '';

        // 收款账户 - 用户 & 场景关联的账户code（不存在，则主动创建用户主账户、用户&场景&账户rel关联关系）
        $receiveMemberAccountRel = $this->GetMemberSceneAccount($receiveCompanyId, $receiveMemberId, $sceneId);

        if (!$receiveMemberAccountRel['status']){
            $interrupt = 1;
            $interrupt_msg = $receiveMemberAccountRel['msg'] ?? '';

            goto END_SIGN;
        }

        $receiveAccountCode = $receiveMemberAccountRel['data']['account'];
        $receiveAccountRelId = $receiveMemberAccountRel['data']['member_account_id'];

        $accountServer = new AccountServer();

        // 收款账户 - 可用积分、已用积分、冻结积分、过期积分
        $receiveAccountInfo = $accountServer->GetAccountInfo($receiveAccountCode);

        // 执行转账核心逻辑
        $transferRes = $accountServer->Transaction(
            'transfer',
            [
                'frozen_pool_code_list' => $frozenPoolCodeList,
                'account' => $payAccountCode,
                'to_account' => $receiveAccountCode,
                'point' => $transferPoint,
                'overdue_time' => $overdueTime,
                'overdue_func' => $overdueFunc,
                'memo' => $memo,
                'ignore_pool_overdue_time' => 1,
            ]
        );

        if (!$transferRes['status']){
            $interrupt = 1;
            $interrupt_msg = $transferRes['msg'] ?? '';

            goto END_SIGN;
        }

        // 创建 收款用户 的积分业务变更（收入）流水记录
        $addRecordRes = MemberBusinessRecord::Create(array(
            'business_type'     => $businessType,
            'business_bn'       => $businessBn,
            'system_code'       => $systemCode,
            'member_account_id' => $receiveAccountRelId,
            'record_type'       => 'add',
            'before_point'      => $receiveAccountInfo->point,
            'point'             => $transferPoint,
            'after_point'       => $receiveAccountInfo->point + $transferPoint,
            'memo'              => $memo,
            'created_at'        => time(),
        ));

        if (!$addRecordRes){
            $interrupt = 1;
            $interrupt_msg = '收款账户业务流水添加失败';

            goto END_SIGN;
        }

        // 创建 积分业务流水号 & 积分账单号 关联关系
        $billCode = $transferRes['data']['bill_code'];

        $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);

        if (!$bindBillRes['status']){
            $interrupt = 1;
            $interrupt_msg = $bindBillRes['msg'];

            goto END_SIGN;
        }

        END_SIGN:
        if ($interrupt == 1){
            $businessFlowServer->Delete($businessFlowCode);
            app('db')->rollBack();

            return $this->Response(false, '转账失败：' . $interrupt_msg);
        }else{
            app('db')->commit();
            return $this->Response(true, '转账成功', ['flow_code' => $businessFlowCode]);
        }
    }

    // 查询积分转赠扣减子账户详情
    public function getTransferPaySonAccount($queryData){
        $businessType = $queryData['business_type'];

        $businessBn = $queryData['business_bn'];

        $systemCode = $queryData['system_code'];

        $businessFlowServer = new BusinessFlowServer();

        $billCodeList = $businessFlowServer->GetBillCodeByBusiness($businessType, $businessBn, $systemCode);

        if (!$billCodeList['status']){
            return $billCodeList;
        }

        $billCodeArr = [];

        foreach ($billCodeList['data'] as $billInfo){
            $billCodeArr[] = $billInfo->bill_code;
        }

        $accountServer = new AccountServer();

        $sonAccountBillList = $accountServer->TransactionQuery('transfer', $billCodeArr);

        if (!$sonAccountBillList['status']){
            return $sonAccountBillList;
        }

        $return = [];

        foreach ($sonAccountBillList['data'] as $bill){
            $return[] = array(
                'son_account_overdue_time' => $bill->accountson_overdue_time,
                'point' => $bill->point,
            );
        }

        return $this->Response(true, 'success', $return);
    }
}
