<?php

namespace App\Api\V3\Service\ScenePoint;

use App\Api\Model\PointServer\AccountTransfer;
use App\Api\Model\PointServer\Account;
use App\Api\Model\PointServer\SonAccount;
use App\Api\Model\PointScene\BusinessFlow as BusinessFlowModel;
use App\Api\Model\PointScene\SceneMemberRel;
use App\Api\V3\Service\ServiceTrait;


class BusinessFlow
{
    use ServiceTrait;

    /**
     * 根据类型和主账户获取该账户下的BusinessFlow详情
     *
     * @param $type
     * @param $accounts
     */
    public function getBusinessFlow($type = "transfer", $account)
    {
        if ($type == "transfer")
        {

            $accountList    = SceneMemberRel::QueryByAccount([$account]);
            $accountInfo    = $accountList[$account];
            $sonAccountList = SonAccount::QueryByAccountBns([$account]);

            if (!$sonAccountList)
            {
                return $this->Response(true, '查询成功', []);
            }
            $sonAccountList = $sonAccountList->toArray();
            $sonAccountIds  = array_column($sonAccountList, "son_account_id");

            $AccountTransferList = AccountTransfer::GetAccountTransfer(['to_son_account_id' => $sonAccountIds]);

            [$sonAccountIndexList, $sonAccountIndexCodeList] = $this->formatSonAccountList($sonAccountList,
                                                                                           $AccountTransferList,
                                                                                           $accountInfo);
            $billCodes            = array_column($AccountTransferList, "bill_code");
            $businessFlowBillList = BusinessFlowModel::GetBussineByBillCodes($billCodes);

            if (!$businessFlowBillList)
            {
                return $this->Response(true, '查询成功', []);
            }

            $accountBusinessFlowIndexList = $this->formatBusinessFlowBillList($businessFlowBillList,
                                                                              $sonAccountIndexCodeList,
                                                                              $accountInfo);

            return $this->Response(true, '查询成功', $accountBusinessFlowIndexList);
        }
    }

    /**
     * @param $sonAccountList
     * @param $AccountTransferList
     * @param $account
     *
     * @return array[]
     */
    protected function formatSonAccountList($sonAccountList, $AccountTransferList, $account)
    {
        $sonAccountIndexIdList = [];
        foreach ($sonAccountList as $sonAccount)
        {

            $sonAccountItem                   = [];
            $sonAccountItem['account']        = $sonAccount->account;
            $sonAccountItem['account_id']     = $sonAccount->account_id;
            $sonAccountItem['son_account_id'] = $sonAccount->son_account_id;
            $sonAccountItem['overdue_time']   = $sonAccount->overdue_time;
            $sonAccountItem['status']         = $sonAccount->status;
            $sonAccountItem['created_at']     = $sonAccount->created_at;
            $sonAccountItem['point']          = $sonAccount->point;
            $sonAccountItem['used_point']     = $sonAccount->used_point;
            $sonAccountItem['frozen_point']   = $sonAccount->frozen_point;

            $sonAccountIndexIdList[$sonAccount->son_account_id] = $sonAccountItem;
        }

        $AccountTransferIndexList = [];
        foreach ($AccountTransferList as $AccountTransfer)
        {
            $AccountTransferItem                      = [];
            $AccountTransferItem['id']                = $AccountTransfer->id;
            $AccountTransferItem['bill_code']         = $AccountTransfer->bill_code;
            $AccountTransferItem['son_account_id']    = $AccountTransfer->son_account_id;
            $AccountTransferItem['to_son_account_id'] = $AccountTransfer->to_son_account_id;
            $AccountTransferItem['point']             = $AccountTransfer->point;
            $AccountTransferItem['memo']              = $AccountTransfer->memo;
            $AccountTransferItem['created_at']        = $AccountTransfer->created_at;

            $AccountTransferIndexList[$AccountTransfer->to_son_account_id] = $AccountTransferItem;
        }

        $sonAccountIndexCodeList = [];
        foreach ($sonAccountIndexIdList as $key => &$sonAccountItem)
        {
            $sonAccountItem['bill_code']                                           = $AccountTransferIndexList[$key]['bill_code'];
            $sonAccountIndexCodeList[$AccountTransferIndexList[$key]['bill_code']] = $sonAccountItem;
        }

        return [$sonAccountIndexIdList, $sonAccountIndexCodeList];
    }

    /**
     * 处理
     *
     * @param $businessFlowList
     * @param $sonAccountIndexList
     */
    protected function formatBusinessFlowBillList($businessFlowBillList, $sonAccountIndexCodeList, $account)
    {
        $businessFlowIndexList = [];
        foreach ($businessFlowBillList as $businessFlow)
        {
            $businessFlowItem                       = [];
            $businessFlowItem['business_type']      = $businessFlow->business_type;
            $businessFlowItem['business_bn']        = $businessFlow->business_bn;
            $businessFlowItem['business_flow_code'] = $businessFlow->business_flow_code;
            $businessFlowItem['bill_code']          = $businessFlow->bill_code;
            $businessFlowItem                       = array_merge($businessFlowItem,
                                                                  $sonAccountIndexCodeList[$businessFlow->bill_code]);

            $businessFlowIndexList[$businessFlow->business_flow_code][] = $businessFlowItem;
        }

        $pointEr                      = new PointExchangeRate();
        $pointDt                      = new PointDecorator();
        $accountAusinessFlowIndexList = [];
        foreach ($businessFlowIndexList as $businessFlowCode => $billCodeBusinessList)
        {
            $item['account']    = $account->account;
            $item['company_id'] = $account->company_id;
            $item['member_id']  = $account->member_id;
            $item['scene_id']   = $account->scene_id;

            foreach ($billCodeBusinessList as &$billCodeBusiness)
            {
                $point       = $billCodeBusiness['point'];
                $usedPoint   = $billCodeBusiness['used_point'];
                $frozenPoint = $billCodeBusiness['frozen_point'];

                $billCodeBusiness['point'] = $pointDt->GetPointByRate($point,
                                                                      $account->exchange_rate,
                                                                      PointDecorator::RATE_TYPE_OUT);

                $billCodeBusiness['used_point'] = $pointDt->GetPointByRate($usedPoint,
                                                                           $account->exchange_rate,
                                                                           PointDecorator::RATE_TYPE_OUT);

                $billCodeBusiness['frozen_point'] = $pointDt->GetPointByRate($frozenPoint,
                                                                             $account->exchange_rate,
                                                                             PointDecorator::RATE_TYPE_OUT);

                $billCodeBusiness['money']        = $pointEr->point2money($point);
                $billCodeBusiness['used_money']   = $pointEr->point2money($usedPoint);
                $billCodeBusiness['frozen_money'] = $pointEr->point2money($frozenPoint);

            }

            $item['bill_code_record_list']                   = $billCodeBusinessList;
            $accountAusinessFlowIndexList[$businessFlowCode] = $item;
        }

        return $accountAusinessFlowIndexList;
    }


}
