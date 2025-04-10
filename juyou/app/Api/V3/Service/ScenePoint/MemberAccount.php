<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-02
 * Time: 10:45
 */

namespace App\Api\V3\Service\ScenePoint;


use App\Api\Logic\Service;
use App\Api\V3\Service\ServiceTrait;
use App\Api\V3\Service\PointServer\Account as AccountServer;
use App\Api\Model\PointScene\SceneMemberRel as SceneMemberRelModel;
use App\Api\Model\PointScene\MemberBusinessRecord as MemberBusinessRecordModel;
use App\Api\V1\Service\PointScene\BusinessFrozenFlow as BusinessFrozenFlowServer;
use App\Api\V1\Service\PointScene\BusinessFlow as BusinessFlowServer;
use App\Api\Model\Point\Point as PointModel;

class MemberAccount
{
    const COMMON_SCENE = 1;
    const COMMON_RULE  = 1;

    use ServiceTrait;

    /**
     * 查询用户账户
     * @param $queryFilter
     * @return array
     */
    public function GetMemberAccount($queryFilter)
    {
        $companyId = $queryFilter['company_id'];
        $memberId  = $queryFilter['member_id'];

        $memberAccountRel = SceneMemberRelModel::QueryByMember($companyId, $memberId);
        $accountIdList    = array_column($memberAccountRel, 'account');

        $accountServer = new AccountServer();
        $accountRes    = $accountServer->QueryBatch($accountIdList);
        if (!$accountRes['status']) {
            return $accountRes;
        }

        $accountArr = array_column($accountRes['data']->toArray(), null, 'account');

        $pointEr     = new PointExchangeRate();
        $pointDt     = new PointDecorator();
        $accountList = [];
        foreach ($memberAccountRel as &$account) {
            $accountInfo = $accountArr[$account->account];

            $accountList[$account->account] = [
                'account'       => $account->account,
                'account_name'  => $account->account_name,
                'rule_bns'      => $account->rule_bns,
                'exchange_rate' => $account->exchange_rate,

                'point'         => $pointDt->GetPointByRate(
                    $accountInfo->point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'used_point'    => $pointDt->GetPointByRate(
                    $accountInfo->used_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'frozen_point'  => $pointDt->GetPointByRate(
                    $accountInfo->frozen_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'overdue_point' => $pointDt->GetPointByRate(
                    $accountInfo->overdue_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),

                'money'         => $pointEr->point2money($accountInfo->point),
                'used_money'    => $pointEr->point2money($accountInfo->used_point),
                'frozen_money'  => $pointEr->point2money($accountInfo->frozen_point),
                'overdue_money' => $pointEr->point2money($accountInfo->overdue_point),

                'earliest_overdue_time' => $accountInfo->earliest_overdue_time,
            ];
        }

        return $this->Response(true, '查询成功', $accountList);
    }

    /**
     * 锁定用户积分
     * @param $lockParam
     * @return array
     */
    public function LockMemberPoint($lockParam)
    {
        $accounts = array_column($lockParam['account_list'], 'account');
        //相同的账户只可以传入一次
        if (count(array_unique($accounts)) != count($lockParam['account_list'])) {
            return $this->Response(false, '参数错误【重复账户】', []);
        }

        $totalPoint = array_sum(array_column($lockParam['account_list'], 'point'));
        if (abs($totalPoint - $lockParam['point']) > 0.001) {
            return $this->Response(false, '参数错误【账户详情与总积分数不等】', []);
        }

        $accountList = SceneMemberRelModel::QueryByAccount($accounts);

        $pointEr = new PointExchangeRate();
        $pointDt = new PointDecorator();

        //获取账户对传入参数进行比例转换
        foreach ($lockParam['account_list'] as $key => $lockPointInfo) {
            $account       = $lockPointInfo['account'];
            $memberAccount = $accountList[$account];
            if (!$memberAccount) {
                return $this->Response(false, $account . '账户不存在', []);
            }

            $ngLockPoint = $pointDt->GetPointByRate(
                $lockPointInfo['point'],
                $memberAccount->exchange_rate,
                $pointDt::RATE_TYPE_INT
            );

            $lockParam['account_list'][$key]['point'] = $ngLockPoint;

            $money = $pointEr->point2money($ngLockPoint);

            if (abs($money - $lockPointInfo['money']) > 0.001) {
                return $this->Response(false, '参数错误【' . $account . '积分与现金价值不等】', []);
            }
        }

        $lockParam['point'] = array_sum(array_column($lockParam['account_list'], 'point'));

        $totalMoney = array_sum(array_column($lockParam['account_list'], 'money'));
        if (abs($totalMoney - $lockParam['money']) > 0.001) {
            return $this->Response(false, '参数错误【锁定详情与总金额不等】', []);
        }

        app('db')->beginTransaction();

        $frozenReturnData = array();

        foreach ($lockParam['account_list'] as $lockPointInfo) {
            $frozenReturnData[$lockPointInfo['account']] = [
                'account' => $lockPointInfo['account'],
                'money'   => $pointEr->point2money($lockPointInfo['point']),
                'point'   => $pointDt->GetPointByRate(
                    $lockPointInfo['point'],
                    $memberAccount->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
            ];

            $status = MemberBusinessRecordModel::Create([
                'system_code'       => $lockParam['system_code'],
                'business_type'     => $lockParam['business_type'],
                'business_bn'       => $lockParam['business_bn'],
                'member_account_id' => $accountList[$lockPointInfo['account']]->id,
                'record_type'       => 'reduce',
                'point'             => $lockPointInfo['point'],
                'memo'              => $lockParam['memo'],
                'created_at'        => time()
            ]);
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "业务流水添加失败");
            }
        }

        $accountServer = new AccountServer();
        $frozenRes     = $accountServer->Operation('create_frozen', [
            "total_point"         => $lockParam['point'],
            "overdue_time"        => $lockParam['overdue_time'],
            "frozen_account_list" => $lockParam['account_list'],
            "memo"                => $lockParam['memo'],
        ]);
        if (!$frozenRes['status']) {
            app('db')->rollBack();
            return $frozenRes;
        }

        $frozenPoolCode = $frozenRes['data']['frozen_pool_code'];

        $businessFrozenFlowServer = new BusinessFrozenFlowServer();
        $bfCreateRes              = $businessFrozenFlowServer->Create([
            "business_type" => $lockParam['business_type'],
            "business_bn"   => $lockParam['business_bn'],
            "system_code"   => $lockParam['system_code']
        ]);
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

        return $this->Response(true, '锁定成功', [
            'trade_no'    => $businessFrozenCode,
            'frozen_data' => $frozenReturnData
        ]);
    }

    public function ConfirmMemberPoint($confirmParam)
    {
        $accounts = array_column($confirmParam['account_list'], 'account');
        //相同的账户只可以传入一次
        if (count(array_unique($accounts)) != count($confirmParam['account_list'])) {
            return $this->Response(false, '参数错误【重复账户】', []);
        }

        $totalPoint = array_sum(array_column($confirmParam['account_list'], 'point'));
        if (abs($totalPoint - $confirmParam['point']) > 0.001) {
            return $this->Response(false, '参数错误【账户详情与总积分数不等】', []);
        }

        $accountList = SceneMemberRelModel::QueryByAccount($accounts);

        $pointEr = new PointExchangeRate();
        $pointDt = new PointDecorator();

        //获取账户对传入参数进行比例转换
        foreach ($confirmParam['account_list'] as $key => $confirmPointInfo) {
            $account       = $confirmPointInfo['account'];
            $memberAccount = $accountList[$account];
            if (!$memberAccount) {
                return $this->Response(false, $account . '账户不存在', []);
            }

            $ngConfirmPoint = $pointDt->GetPointByRate(
                $confirmPointInfo['point'],
                $memberAccount->exchange_rate,
                $pointDt::RATE_TYPE_INT
            );

            $confirmParam['account_list'][$key]['point'] = $ngConfirmPoint;

            $money = $pointEr->point2money($ngConfirmPoint);

            if (abs($money - $confirmPointInfo['money']) > 0.001) {
                return $this->Response(false, '参数错误【' . $account . '积分与现金价值不等】', []);
            }
        }

        $confirmParam['point'] = array_sum(array_column($confirmParam['account_list'], 'point'));

        $totalMoney = array_sum(array_column($confirmParam['account_list'], 'money'));
        if (abs($totalMoney - $confirmParam['money']) > 0.001) {
            return $this->Response(false, '参数错误【锁定详情与总金额不等】', []);
        }

        $businessFrozenServer = new BusinessFrozenFlowServer();

        $frozenCodeRes = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn(
            $confirmParam['source_business_type'],
            $confirmParam['source_business_bn'],
            $confirmParam['system_code']
        );

        if (!$frozenCodeRes['status']) {
            return $this->Response(false, '锁定流水获取失败', []);
        }

        $frozenPoolCodeList = array_column($frozenCodeRes['data']->toArray(), 'frozen_pool_code');

        if (!$frozenPoolCodeList) {
            return $this->Response(false, '锁定池获取失败');
        }


        app('db')->beginTransaction();

        $businessFlowServer = new BusinessFlowServer();

        $bfCreateRes = $businessFlowServer->Create(array(
            "business_type" => $confirmParam['business_type'],
            "business_bn"   => $confirmParam['business_bn'],
            "system_code"   => $confirmParam['system_code']
        ));

        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        $accountServer = new AccountServer();

        foreach ($confirmParam['account_list'] as $account) {
            $confirmRes = $accountServer->Transaction('consume', [
                "frozen_pool_code_list" => $frozenPoolCodeList,
                "account"               => $account['account'],
                "point"                 => $account['point'],
                "memo"                  => $confirmParam['memo'],
            ]);

            if (!$confirmRes['status']) {
                app('db')->rollBack();
                return $confirmRes;
            }

            $billCode    = $confirmRes['data']['bill_code'];
            $bindBillRes = $businessFlowServer->BindBillCode($businessFlowCode, $billCode);
            if (!$bindBillRes['status']) {
                app('db')->rollBack();
                return $bindBillRes;
            }
        }

        app('db')->commit();

        return $this->Response(true, "消费成功", [
            'flow_code' => $businessFlowCode
        ]);
    }

    public function CancelMemberPoint($cancelParam)
    {
        $businessFrozenServer = new BusinessFrozenFlowServer();
        $cancelCodeRes        = $businessFrozenServer->GetBusinessFrozenPoolCodeByBusinessBn(
            $cancelParam['business_type'],
            $cancelParam['business_bn'],
            $cancelParam['system_code']
        );
        if (!$cancelCodeRes['status']) {
            return $this->Response(false, '锁定流水获取失败', []);
        }

        $frozenPoolCodeList = array_column($cancelCodeRes['data']->toArray(), 'frozen_pool_code');

        if (!$frozenPoolCodeList) {
            return $this->Response(false, '锁定池获取失败');
        }


        $returnRelease = [];
        $allAccount    = [];

        $accountServer = new AccountServer();

        app('db')->beginTransaction();
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

        $pointEr = new PointExchangeRate();
        $pointDt = new PointDecorator();

        $returnSceneRelease = [];
        $accountList        = SceneMemberRelModel::QueryByAccount(array_unique($allAccount));
        foreach ($returnRelease as $account => $releaseData) {
            $accountInfo = $accountList[$account];
            if (!$accountInfo) {
                app('db')->rollBack();
                return $this->Response(false, '账户信息错误');
            }

            $status = MemberBusinessRecordModel::Create([
                'system_code'       => $cancelParam['system_code'],
                'business_type'     => 'cancelOrder',
                'business_bn'       => $cancelParam['business_bn'],
                'member_account_id' => $accountInfo->id,
                'record_type'       => 'add',
                'point'             => $releaseData['point'],
                'memo'              => $cancelParam['memo'],
                'created_at'        => time()
            ]);
            if (!$status) {
                app('db')->rollBack();
                return $this->Response(false, "业务流水添加失败");
            }

            $returnSceneRelease[$accountInfo->account] = [
                'account' => $accountInfo->account,
                'point'   => $pointDt->GetPointByRate(
                    $releaseData['point'],
                    $accountInfo->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'money'   => $pointEr->point2money($releaseData['point']),
            ];
        }

        app('db')->commit();

        return $this->Response(true, '锁定释放成功', $returnSceneRelease);
    }

    public function RefundMemberPoint($refundParam)
    {
        $accounts = array_column($refundParam['account_list'], 'account');
        //相同的账户只可以传入一次
        if (count(array_unique($accounts)) != count($refundParam['account_list'])) {
            return $this->Response(false, '参数错误【重复账户】', []);
        }

        $totalPoint = array_sum(array_column($refundParam['account_list'], 'point'));
        if (abs($totalPoint - $refundParam['point']) > 0.001) {
            return $this->Response(false, '参数错误【账户详情与总积分数不等】', []);
        }

        $accountList = SceneMemberRelModel::QueryByAccount($accounts);

        $pointEr = new PointExchangeRate();
        $pointDt = new PointDecorator();

        //获取账户对传入参数进行比例转换
        foreach ($refundParam['account_list'] as $key => $confirmPointInfo) {
            $account       = $confirmPointInfo['account'];
            $memberAccount = $accountList[$account];
            if (!$memberAccount) {
                return $this->Response(false, $account . '账户不存在', []);
            }

            $ngConfirmPoint = $pointDt->GetPointByRate(
                $confirmPointInfo['point'],
                $memberAccount->exchange_rate,
                $pointDt::RATE_TYPE_INT
            );

            $refundParam['account_list'][$key]['point'] = $ngConfirmPoint;

            $money = $pointEr->point2money($ngConfirmPoint);

            if (abs($money - $confirmPointInfo['money']) > 0.001) {
                return $this->Response(false, '参数错误【' . $account . '积分与现金价值不等】', []);
            }
        }

        $refundParam['point'] = array_sum(array_column($refundParam['account_list'], 'point'));

        $totalMoney = array_sum(array_column($refundParam['account_list'], 'money'));
        if (abs($totalMoney - $refundParam['money']) > 0.001) {
            return $this->Response(false, '参数错误【锁定详情与总金额不等】', []);
        }

        $businessFlowServer = new BusinessFlowServer();

        $relListRes = $businessFlowServer->GetBillCodeByBusiness(
            $refundParam['source_business_type'],
            $refundParam['source_business_bn'],
            $refundParam['system_code']
        );
        if (!$relListRes['status']) {
            return $relListRes;
        }


        app('db')->beginTransaction();

        $businessFlowServer = new BusinessFlowServer();
        $bfCreateRes        = $businessFlowServer->Create(array(
            "business_type" => $refundParam['business_type'],
            "business_bn"   => $refundParam['business_bn'],
            "system_code"   => $refundParam['system_code']
        ));
        if (!$bfCreateRes['status']) {
            app('db')->rollBack();
            return $bfCreateRes;
        }

        $businessFlowCode = $bfCreateRes['data']['business_flow_code'];

        $accountServer = new AccountServer();
        foreach ($refundParam['account_list'] as $account) {
            foreach ($relListRes['data'] as $billRel) {
                $returnRes = $accountServer->Transaction('refund', array(
                    "consume_bill_code" => $billRel->bill_code,
                    "account"           => $account['account'],
                    "refund_data"       => $account,
                    "memo"              => $refundParam['memo']
                ));

                if (!$returnRes['status'] && $returnRes['code'] == 10000) {
                    continue;
                }

                if (!$returnRes['status']) {
                    app('db')->rollBack();
                    return $returnRes;
                }

                $status = MemberBusinessRecordModel::Create([
                    'system_code'       => $refundParam['system_code'],
                    'business_type'     => $refundParam['business_type'],
                    'business_bn'       => $refundParam['business_bn'],
                    'member_account_id' => $accountList[$account['account']]->id,
                    'record_type'       => 'add',
                    'point'             => $account['point'],
                    'memo'              => "订单退款,订单号：" . $refundParam['source_business_bn'],
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
            }
        }

        app('db')->commit();

        return $this->Response(true, "退款成功", array(
            "trade_no" => $businessFlowCode
        ));
    }

    public function GetMemberRecord($queryParam)
    {
        $where = array(
            'begin_time'  => $queryParam['begin_time'] ?? 0,
            'end_time'    => $queryParam['end_time'] ?? 0,
            'search_key'  => $queryParam['search_key'] ?? '',
            'record_type' => $queryParam['record_type'] ?? 'all'
        );
        if ($queryParam['system_code']) {
            $where['system_code'] = $queryParam['system_code'];
        }
        if ($queryParam['scene_ids']) {
            $where['scene_ids'] = $queryParam['scene_ids'];
        }
        if ($queryParam['accounts']) {
            $where['accounts'] = $queryParam['accounts'];
        }


        $recordCount = MemberBusinessRecordModel::QueryCount(
            $queryParam['company_ids'] ?? '',
            $queryParam['member_ids'] ?? '',
            $where
        );

        if (!$recordCount) {
            return $this->Response(true, '无数据', array(
                'base' => array(
                    "totalPage" => 0,
                    "totalNum"  => 0
                ),
                'data' => []
            ));
        }

        $recordList = MemberBusinessRecordModel::Query(
            $queryParam['company_ids'] ?? '',
            $queryParam['member_ids'] ?? '',
            $where,
            $queryParam['page'],
            $queryParam['page_size']
        );

        $pointEr = new PointExchangeRate();
        $pointDt = new PointDecorator();

        foreach ($recordList as &$recordInfo) {
            $recordInfo['account_name'] = $recordInfo['scene_name'];
            $recordInfo['money']        = $pointEr->point2money($recordInfo['point']);
            $recordInfo['point']        = $pointDt->GetPointByRate(
                $recordInfo['point'],
                $recordInfo['exchange_rate'],
                $pointDt::RATE_TYPE_OUT
            );
        }


        return $this->Response(
            true,
            '查询成功',
            [
                'base' => [
                    "totalPage" => ceil($recordCount / $queryParam['page_size']),
                    "totalNum"  => $recordCount
                ],
                'data' => $recordList
            ]
        );
    }

    public function MemberPointWithRule($queryData)
    {
        $goodsList = array();
        foreach ($queryData['filter_data']['product'] as $val) {
            if (!isset($val['goods_bn'])) {
                return $this->Response(false, '参数错误', []);
            }
            $goodsList[$val['goods_bn']] = $val;
        }
        $pointChannelInfo = PointModel::GetChannelInfo($queryData['channel']);
        if ($pointChannelInfo->point_version == 1) {
            $accountId = $queryData['company_id'] . '_' . $queryData['member_id'] . '_' . self::COMMON_SCENE;
            return $this->Response(true, '', [
                'product' => [
                    $accountId => [
                        'rule_id'    => self::COMMON_RULE,
                        'scene_id'   => $accountId,
                        'scene_name' => '通用积分',
                        'goods_list' => $goodsList
                    ]
                ]
            ]);
        }

        $serviceLogic = new Service();

        $res = $serviceLogic->ServiceCall(
            'get_member_point',
            [
                'member_id'  => $queryData['member_id'],
                'company_id' => $queryData['company_id'],
                'channel'    => $queryData['channel']
            ],
            'v3'
        );
        if ('SUCCESS' == $res['error_code']) {
            $memberAccountRel = $res['data'];
        }

        $ruleBnArr = [];
        foreach ($memberAccountRel as $account) {
            $ruleBnArr = array_merge($ruleBnArr, $account['rule_bns']);

            $sceneIdArr[$account['account']] = $account['rule_bns'];
        }

        $sendData = [
            'channel'  => "NEIGOU_SHOPING",
            'rule_bns' => $ruleBnArr
        ];

        $neigouRuleRes = $serviceLogic->ServiceCall('rule_bn_to_neigou_rule_id', $sendData);

        if ('SUCCESS' != $neigouRuleRes['error_code'] || !$neigouRuleRes['data']) {
            return $this->Response();
        }

        $ruleIdArr = [];
        foreach ($neigouRuleRes['data'] as $neigouRule) {
            $ruleIdArr[$neigouRule['rule_bn']] = $neigouRule['channel_rule_bn'];
        }

        $returnData = array();
        foreach ($queryData['filter_data'] as $filterType => $filterInfo) {
            if ($filterInfo) {
                $className = 'App\\Api\\Logic\\PointScene\\RuleAnalysis\\' . ucfirst(camel_case($filterType)) . "RuleAnalysis";
                if (!class_exists($className)) {
                    $returnData[$filterType] = array();
                    continue;
                }
                $transactionObj = new $className();
                $withRuleRes    = $transactionObj->WithRule($ruleIdArr, $sceneIdArr, $filterInfo);
                if ($withRuleRes['status']) {
                    $returnData[$filterType] = $withRuleRes['data'];
                }
            } else {
                $returnData[$filterType] = array();
            }
        }
        return $this->Response(true, '', $returnData);
    }

    public function GetMemberList($queryFilter, $page = 1, $pageSize = 10)
    {
        $totalCount = SceneMemberRelModel::QueryCount($queryFilter);
        if (!$totalCount) {
            return $this->Response(true, '查询成功', [
                'page'       => $page,
                'totalCount' => 0,
                'totalPage'  => 0
            ]);
        }

        $memberAccountRel = SceneMemberRelModel::Query($queryFilter, $page, $pageSize);
        if ($memberAccountRel->count() <= 0) {
            return $this->Response(true, '查询成功', [
                'page'       => $page,
                'totalCount' => $totalCount,
                'totalPage'  => ceil($totalCount / $pageSize),
                'list'       => []
            ]);
        }

        $accountIdList = array_column($memberAccountRel->toArray(), 'account');

        $accountServer = new AccountServer();
        $accountRes    = $accountServer->QueryBatch($accountIdList, $queryFilter['overdue_time'] ?? time());
        if (!$accountRes['status']) {
            return $accountRes;
        }

        $accountArr = array_column($accountRes['data']->toArray(), null, 'account');

        $pointEr     = new PointExchangeRate();
        $pointDt     = new PointDecorator();
        $accountList = [];
        foreach ($memberAccountRel as &$account) {
            $accountInfo = $accountArr[$account->account];

            $accountList[$account->account] = [
                'company_id'    => $account->company_id,
                'member_id'     => $account->member_id,
                'account'       => $account->account,
                'account_name'  => $account->account_name,
                'exchange_rate' => $account->exchange_rate,

                'point'         => $pointDt->GetPointByRate(
                    $accountInfo->point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'used_point'    => $pointDt->GetPointByRate(
                    $accountInfo->used_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'frozen_point'  => $pointDt->GetPointByRate(
                    $accountInfo->frozen_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),
                'overdue_point' => $pointDt->GetPointByRate(
                    $accountInfo->overdue_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                ),

                'point_money'         => $pointEr->point2money($accountInfo->point),
                'used_point_money'    => $pointEr->point2money($accountInfo->used_point),
                'frozen_point_money'  => $pointEr->point2money($accountInfo->frozen_point),
                'overdue_point_money' => $pointEr->point2money($accountInfo->overdue_point),

                'earliest_overdue_time' => $accountInfo->earliest_overdue_time,
            ];
        }

        return $this->Response(true, '查询成功', [
            'page'       => $page,
            'totalCount' => $totalCount,
            'totalPage'  => ceil($totalCount / $pageSize),
            'list'       => $accountList
        ]);
    }
}
