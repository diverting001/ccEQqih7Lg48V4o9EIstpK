<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-16
 * Time: 15:59
 */

namespace App\Api\V1\Service\PointServer;

use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\V1\Service\PointScene\Account as BaseAccount;

class Account extends BaseAccount
{
    public function GetSonAccount($accounts)
    {
        $sonAccountList = SonAccountModel::QueryByAccountBns($accounts);
        if (!$sonAccountList) {
            return $this->Response(false, "账户不存在");
        }
        foreach ($sonAccountList as &$item) {
            $item->money         = $this->point2money($item->point);
            $item->used_money    = $this->point2money($item->used_point);
            $item->frozen_money  = $this->point2money($item->frozen_point);
            $item->overdue_money = $this->point2money($item->overdue_point);
        }
        return $this->Response(true, "", $sonAccountList);
    }

    /**
     * 根据account获取有效账户
     */
    public function GetValidAccount($account)
    {
        $accountInfo = AccountModel::Find($account);
        if (!$accountInfo) {
            return $this->Response(false, "账户不存在");
        }

        if ($accountInfo->disabled != 0) {
            return $this->Response(false, "账户不可用");
        }

        return $this->Response(true, "", $accountInfo);;
    }


    /**
     * 根据account获取一批账户的info
     */
    public function QueryBatch($accountList)
    {
        $accountList = AccountModel::QueryBatch(array_values($accountList));
        if ($accountList->count() > 0) {
            $accountIds = array();
            foreach ($accountList as $key => $accountInfo) {
                $accountIds[] = $accountInfo->account_id;
            }

            $earliestList        = SonAccountModel::GetEarliestOverdueTime($accountIds);
            $sonAccountGroupList = SonAccountModel::GetAccountTotalValidPoint($accountIds, time());
            foreach ($accountList as $key => $account) {
                $accountId = $account->account_id;

                $accountList[$key]->earliest_overdue_time = $earliestList[$accountId]->overdue_time ?? 0;

                $sonAccountTotal = $sonAccountGroupList[$accountId];
                AccountModel::UpdateAccountPoint($accountId, $sonAccountTotal);
                $accountList[$key]->point         = isset($sonAccountTotal->point) ? $sonAccountTotal->point : 0;
                $accountList[$key]->used_point    = isset($sonAccountTotal->used_point) ? $sonAccountTotal->used_point : 0;
                $accountList[$key]->frozen_point  = isset($sonAccountTotal->frozen_point) ? $sonAccountTotal->frozen_point : 0;
                $accountList[$key]->overdue_point = isset($sonAccountTotal->overdue_point) ? $sonAccountTotal->overdue_point : 0;
            }
        }
        return $this->Response(
            true,
            "获取成功",
            $accountList ? $accountList : array()
        );
    }

    public function QueryByOverdueTime($accountList, $queryFilter)
    {
        $accountList = AccountModel::QueryByOverdueTime(array_values($accountList), $queryFilter);
        return $this->Response(
            true,
            "获取成功",
            $accountList ? $accountList : array()
        );
    }

    /**
     * 创建积分底层账户
     */
    public function Create()
    {
        do {
            $account      = date('YmdHis') . rand(1000, 9999);
            $accountIsset = AccountModel::Find($account);
        } while ($accountIsset);

        $id = AccountModel::Create(array("account" => $account));
        if (!$id) {
            return $this->Response(false, "创建账户失败");
        }
        return $this->Response(true, "创建账户成功", array(
            "account"            => $account,
            'company_account_id' => $id
        ));
    }

    /**
     * 账户交易
     */
    public function TransactionQuery($type, $billList)
    {
        $className = 'App\\Api\\Logic\\PointServer\\Transaction\\' . ucfirst(camel_case($type));
        if (!class_exists($className)) {
            return $this->Response(false, '暂不支持此类型交易');
        }
        $transactionObj = new $className();
        $ret            = $transactionObj->Query($billList);
        return $ret;
    }


    /**
     * 账户交易
     */
    public function Transaction($type, $transactionData)
    {
        $className = 'App\\Api\\Logic\\PointServer\\Transaction\\' . ucfirst(camel_case($type));
        if (!class_exists($className)) {
            return $this->Response(false, '暂不支持此类型交易');
        }
        $transactionObj = new $className();
        $ret            = $transactionObj->Execute($transactionData);
        return $ret;
    }

    /**
     * 账户操作
     */
    public function Operation($type, $operationData)
    {
        $className = 'App\\Api\\Logic\\PointServer\\Operation\\' . ucfirst(camel_case($type));
        if (!class_exists($className)) {
            return $this->Response(false, '暂不支持此操作');
        }
        $operationObj = new $className();
        $ret          = $operationObj->Execute($operationData);
        return $ret;
    }
}
