<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-24
 * Time: 11:27
 */

namespace App\Api\Logic\PointServer\Transaction;

use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\V1\Service\PointServer\Account as AccountServer;

use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\AccountIncome as AccountIncomeModel;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;

class Income extends ATransaction
{
    const TRANSACTION_TYPE = 'income';

    /**
     * 账户入账(账户从外部收入积分)
     */
    public function Execute($transactionData)
    {
        $account = $transactionData['account'];
        $point = $transactionData['point'];
        $overdueTime = $transactionData['overdue_time'];
        $memo = $transactionData['memo'] ? $transactionData['memo'] : "";

        $accountServer = new AccountServer();
        $accountRes = $accountServer->GetValidAccount($account);
        if (!$accountRes['status']) {
            return $accountRes;
        }
        $accountInfo = $accountRes['data'];


        $billServer = new BillServer();
        app('db')->beginTransaction();
        //生成收入账单
        $billCreateRes = $billServer->Create(array("bill_type" => self::TRANSACTION_TYPE));
        if (!$billCreateRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, '收入账单创建失败');
        }

        $accountId = $accountInfo->account_id;
        $billCode = $billCreateRes['data']['bill_code'];
        //每一笔收入都等于创建一个子账户，并将流水挂在子账户下
        $sonAccountId = SonAccountModel::Create(array(
            "account_id" => $accountId,
            "point" => $point,
            "overdue_time" => $overdueTime
        ));

        if (!$sonAccountId) {
            app('db')->rollBack();
            return $this->Response(false, '子账户创建失败');
        }

        //生成交易数据
        $incomeCreateStatus = AccountIncomeModel::Create(array(
            "bill_code" => $billCode,
            "son_account_id" => $sonAccountId,
            "point" => $point,
            "memo" => $memo,
        ));

        if (!$incomeCreateStatus) {
            app('db')->rollBack();
            return $this->Response(false, '入账信息创建失败');
        }

        //生成流水
        $recordCreateStatus = AccountRecordModel::Create(array(
            'son_account_id' => $sonAccountId,
            'bill_code' => $billCode,
            'frozen_record_id' => -1,
            'record_type' => 'add',
            'before_point' => 0,
            'change_point' => $point,
            'after_point' => $point,
            'memo' => $memo,
        ));

        if (!$recordCreateStatus) {
            app('db')->rollBack();
            return $this->Response(false, '交易流水创建失败');
        }

        //变更主账户金额
        $updateAccountStatus = AccountModel::IncomePoint($accountId, array(
            "point" => $point
        ));

        if (!$updateAccountStatus) {
            app('db')->rollBack();
            return $this->Response(false, '账户金额变更失败');
        }

        app('db')->commit();
        return $this->Response(true, '交易成功', array("bill_code" => $billCode));
    }

    public function Query($billList)
    {
        return $this->Response(false, "查询失败");
    }

}
