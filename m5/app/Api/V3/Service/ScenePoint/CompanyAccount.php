<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-12-02
 * Time: 10:45
 */

namespace App\Api\V3\Service\ScenePoint;

use App\Api\V3\Service\ServiceTrait;
use App\Api\V3\Service\PointServer\Account as AccountServer;
use App\Api\Model\PointScene\SceneCompanyRel as SceneCompanyRelModel;
use App\Api\Model\PointScene\CompanyBusinessRecord as CompanyBusinessRecordModel;

class CompanyAccount
{
    const COMMON_SCENE = 1;
    const COMMON_RULE  = 1;

    use ServiceTrait;

    /**
     * 查询用户账户
     * @param $queryFilter
     * @return array
     */
    public function GetCompanyAccount($queryFilter)
    {
        $companyId = $queryFilter['company_id'];

        $companyAccountRel = SceneCompanyRelModel::QueryByCompany($companyId);
        if ($companyAccountRel->count() <= 0) {
            return $this->Response(true, '查询成功', []);
        }

        $companyAccount = $companyAccountRel->toArray();
        $accountIdList  = array_column($companyAccount, 'account');

        $accountServer = new AccountServer();
        $accountRes    = $accountServer->QueryBatch($accountIdList);
        if (!$accountRes['status']) {
            return $accountRes;
        }

        $accountArr = array_column($accountRes['data']->toArray(), null, 'account');

        $pointEr     = new PointExchangeRate();
        $pointDt     = new PointDecorator();
        $accountList = [];
        foreach ($companyAccountRel as &$account) {
            $accountInfo = $accountArr[$account->account];

            foreach ($accountInfo->sons as $son_key=>$sons){
                $accountInfo->sons[$son_key]->point = $pointDt->GetPointByRate(
                    $sons->point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                );
                $accountInfo->sons[$son_key]->used_point = $pointDt->GetPointByRate(
                    $sons->used_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                );
                $accountInfo->sons[$son_key]->frozen_point = $pointDt->GetPointByRate(
                    $sons->frozen_point,
                    $account->exchange_rate,
                    $pointDt::RATE_TYPE_OUT
                );
                $accountInfo->sons[$son_key]->money = $pointEr->point2money($sons->point);
                $accountInfo->sons[$son_key]->used_money = $pointEr->point2money($sons->used_point);
                $accountInfo->sons[$son_key]->frozen_money = $pointEr->point2money($sons->frozen_point);
                unset($accountInfo->sons[$son_key]->account_id);
                unset($accountInfo->sons[$son_key]->created_at);
                unset($accountInfo->sons[$son_key]->updated_at);
            }

            $accountList[$account->account] = [
                'account'       => $account->account,
                'account_name'  => $account->account_name,
                'scene_id'      => $account->scene_id,
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
                'sons' => $accountInfo->sons,
            ];
        }

        return $this->Response(true, '查询成功', $accountList);
    }

    public function GetCompanyRecord($queryData)
    {
        $companyIds = $queryData['company_id_list'];
        $direction  = $queryData['direction'];

        $recordCount = CompanyBusinessRecordModel::QueryCountV2(
            $companyIds,
            [
                'begin_time' => isset($queryData['last_created_at']) ? $queryData['last_created_at'] : 0,
            ]
        );

        if (!$recordCount) {
            return $this->Response(
                true,
                '无数据',
                [
                    'base' => [
                        "totalPage" => 0,
                        "totalNum"  => 0
                    ],
                    'data' => []
                ]
            );
        }

        $recordList = CompanyBusinessRecordModel::QueryV2(
            $companyIds,
            [
                'begin_time' => isset($queryData['last_created_at']) ? $queryData['last_created_at'] : 0,
            ],
            $direction,
            $queryData['page'],
            $queryData['page_size']
        );

        $pointEr = new PointExchangeRate();
        $pointDt = new PointDecorator();

        foreach ($recordList as &$recordInfo) {
            $recordInfo['account_name'] = $recordInfo['scene_name'];

            $recordInfo['before_money'] = $pointEr->point2money($recordInfo['before_point']);
            $recordInfo['before_point'] = $pointDt->GetPointByRate(
                $recordInfo['before_point'],
                $recordInfo['exchange_rate'],
                $pointDt::RATE_TYPE_OUT
            );

            $recordInfo['money'] = $pointEr->point2money($recordInfo['point']);
            $recordInfo['point'] = $pointDt->GetPointByRate(
                $recordInfo['point'],
                $recordInfo['exchange_rate'],
                $pointDt::RATE_TYPE_OUT
            );

            $recordInfo['after_money'] = $pointEr->point2money($recordInfo['after_point']);
            $recordInfo['after_point'] = $pointDt->GetPointByRate(
                $recordInfo['after_point'],
                $recordInfo['exchange_rate'],
                $pointDt::RATE_TYPE_OUT
            );
        }

        return $this->Response(
            true,
            '查询成功',
            [
                'base' => [
                    "totalPage" => ceil($recordCount / $queryData['page_size']),
                    "totalNum"  => $recordCount
                ],
                'data' => $recordList
            ]
        );

    }

}
