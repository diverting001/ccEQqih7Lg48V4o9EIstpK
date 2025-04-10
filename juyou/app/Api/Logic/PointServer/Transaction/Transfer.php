<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-28
 * Time: 16:34
 */

namespace App\Api\Logic\PointServer\Transaction;

use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\V1\Service\PointServer\Account as AccountServer;
use App\Api\Model\PointServer\AccountTransfer as AccountTransferModel;
use App\Api\Model\PointServer\FrozenPoolRecord as FrozenPoolRecordModel;
use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\FrozenPool as FrozenPoolModel;
use App\Api\Model\PointServer\FrozenAssets as FrozenAssetsModel;
use App\Api\Model\PointServer\FrozenAssetsInfo as FrozenAssetsInfoModel;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;

class Transfer extends ATransaction
{

    const TRANSACTION_TYPE = 'transfer';

    /**
     * 账户转帐(两个账户之间金额互转)
     */
    public function Execute($transactionData)
    {
        $frozenPoolCodeList = $transactionData['frozen_pool_code_list'];
        $account = $transactionData['account'];
        $toAccount = $transactionData['to_account'];
        $point = $transactionData['point'];
        $overdueTime = $transactionData['overdue_time'];
        $overdueFunc = $transactionData['overdue_func'];
        $memo = $transactionData['memo'] ? $transactionData['memo'] : "";

        $accountServer = new AccountServer();
        $paymenAccountRes = $accountServer->GetValidAccount($account);
        if (!$paymenAccountRes['status']) {
            return $this->Response(false, '出账账户无效');
        }

        $paymenAccountId = $paymenAccountRes['data']->account_id;

        $receiveAccountRes = $accountServer->GetValidAccount($toAccount);
        if (!$receiveAccountRes['status']) {
            return $this->Response(false, '入账账户无效');
        }
        $receiveAccountId = $receiveAccountRes['data']->account_id;

        $billServer = new BillServer();
        app('db')->beginTransaction();
        //生成收入账单
        $billCreateRes = $billServer->Create(array("bill_type" => self::TRANSACTION_TYPE));
        if (!$billCreateRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, '收入账单创建失败');
        }
        $billCode = $billCreateRes['data']['bill_code'];

        //创建收款子账户
        $receiveSonAccountId = SonAccountModel::Create(array(
            'account_id' => $receiveAccountId,
            'point' => 0,
            'overdue_func' => $overdueFunc,
            'overdue_time' => $overdueTime
        ));

        //转帐用户出账
        if ($frozenPoolCodeList) {
            $transferRes = $this->TransferByFrozenPoolList(
                $frozenPoolCodeList,
                $billCode,
                array(
                    'account_id' => $paymenAccountId
                ),
                array(
                    'account_id' => $receiveAccountId,
                    'son_account_id' => $receiveSonAccountId
                ),
                $point,
                $overdueTime,
                array("memo" => $memo)
            );
            if (!$transferRes['status']) {
                app('db')->rollBack();
                return $transferRes;
            }
        } else {
            $transferRes = $this->TransferredByAccount($billCode, $account, $point, $overdueTime);
            if (!$transferRes['status']) {
                app('db')->rollBack();
                return $transferRes;
            }
        }

        app('db')->commit();
        return $this->Response(true, '转账成功', array("bill_code" => $billCode));
    }

    /**
     * 从多个冻结池子扣除指定金额
     * 该方法必须存在于事务中
     */
    private function TransferByFrozenPoolList(
        $frozenPoolCodeList,
        $billCode,
        $accountData,
        $toAccountData,
        $point,
        $overdueTime,
        $extendData = array()
    )
    {
        $poolList = FrozenPoolModel::QueryAvailableList($frozenPoolCodeList, $overdueTime);
        if ($poolList->count() <= 0) {
            return $this->Response(false, '无可用锁定池');
        }

        $curPoint = $point;
        foreach ($poolList as $frozenPoolInfo) {
            if ($curPoint <= 0) {
                break;
            }
            $paymenPoint = min($curPoint, $frozenPoolInfo->frozen_point);
            $transferRes = $this->TransferByFrozenPool($frozenPoolInfo->frozen_pool_code, $billCode, $accountData, $toAccountData, $paymenPoint, $overdueTime, $extendData);
            if (!$transferRes['status']) {
                return $transferRes;
            }
            $curPoint -= $paymenPoint;
        }
        if ($curPoint > 0) {
            return $this->Response(false, "锁定池资产余额不足");
        }
        return $this->Response(true, "消费成功");
    }

    /**
     * 从单个冻结池子扣除指定金额
     * 该方法必须存在于事务中
     */
    private function TransferByFrozenPool(
        $frozenPoolCode,
        $billCode,
        $accountData,
        $toAccountData,
        $point,
        $overdueTime,
        $extendData = array()
    )
    {
        $poolInfo = FrozenPoolModel::QueryAvailable($frozenPoolCode, $overdueTime);
        if (!$poolInfo) {
            return $this->Response(false, '锁定池无效');
        }

        $assetsList = FrozenAssetsModel::QueryAvailableListByPoolAndAccount($poolInfo->frozen_pool_id, $accountData['account_id'], $extendData);
        $curPoint = $point;
        foreach ($assetsList as $assetsData) {
            if ($curPoint <= 0) {
                break;
            }
            $paymenPoint = min($curPoint, $assetsData->frozen_point);
            $transferRes = $this->TransferByAssets($billCode, $assetsData, $toAccountData, $paymenPoint,$extendData);
            if (!$transferRes['status']) {
                return $transferRes;
            }
            $curPoint -= $paymenPoint;
        }

        \Neigou\Logger::Debug('Transaction.ConsumeByFrozenPool', array(
            'sender' => $curPoint,
            'reason' => json_encode($assetsList),
        ));
        if ($curPoint > 0.001) {
            return $this->Response(false, "锁定池余额不足");
        }

        $status = $poolInfo->frozen_point >= $point ? 1 : 0;
        $poolConsumeRes = FrozenPoolModel::Consume($poolInfo->frozen_pool_id, $status, array(
            "frozen_point" => $point
        ));

        if (!$poolConsumeRes) {
            return $this->Response(false, "锁定池消费失败");
        }
        return $this->Response(true, "消费成功");
    }

    private function TransferByAssets($billCode, $assetsData, $toAccountData, $point, $extendData = array())
    {
        $frozenAssetsId = $assetsData->frozen_assets_id;
        $assetsInfoList = FrozenAssetsInfoModel::QueryAvailable($frozenAssetsId);

        $curPoint = $point;
        foreach ($assetsInfoList as $assetsInfoData) {
            if ($curPoint <= 0) {
                break;
            }
            $paymenPoint = min($curPoint, $assetsInfoData->frozen_point);
            $transferInfoRes = $this->TransferByAssetsInfo($billCode, $assetsInfoData, $toAccountData, $paymenPoint, $extendData);
            if (!$transferInfoRes['status']) {
                return $transferInfoRes;
            }
            $curPoint -= $paymenPoint;
        }

        if ($curPoint > 0) {
            return $this->Response(false, "锁定账户余额不足");
        }

        $frozenConsumeRes = FrozenAssetsModel::Consume($frozenAssetsId, array(
            'frozen_point' => $point
        ));
        if (!$frozenConsumeRes) {
            return $this->Response(false, "锁定账户出账失败");
        }

        $incomeRes = AccountModel::IncomePoint($toAccountData['account_id'], array(
            "point" => $point
        ));

        if (!$incomeRes) {
            return $this->Response(false, "账户入账失败");
        }

        return $this->Response(true, "转帐成功");
    }

    private function TransferByAssetsInfo($billCode, $assetsInfoData, $toAccountData, $point, $extendData = array())
    {
        $memo = $extendData['memo'] ? $extendData['memo'] : '';
        //生成转帐交易数据
        $transferCreateRes = AccountTransferModel::Create(array(
            'bill_code' => $billCode,
            'son_account_id' => $assetsInfoData->son_account_id,
            'to_son_account_id' => $toAccountData['son_account_id'],
            'point' => $point,
            'memo' => $memo,
        ));
        if (!$transferCreateRes) {
            return $this->Response(false, "转帐交易数据创建失败");
        }

        $sonAccountInfo = SonAccountModel::Find($assetsInfoData->son_account_id);
        if (!$sonAccountInfo) {
            return $this->Response(false, "出账子账户已失效");
        }

        $frozenInfoId = $assetsInfoData->frozen_info_id;
        $consumeStatus = FrozenAssetsInfoModel::Consume($assetsInfoData->frozen_info_id, array(
            "frozen_point" => $point
        ));
        if (!$consumeStatus) {
            return $this->Response(false, "冻结资产子账户出账失败");
        }

        $frozenRecordId = FrozenPoolRecordModel::Create(array(
            'frozen_info_id' => $frozenInfoId,
            'record_type' => 'reduce',
            'before_point' => $assetsInfoData->frozen_point,
            'change_point' => $point,
            'after_point' => $assetsInfoData->frozen_point - $point,
            'memo' => $memo,
        ));

        if (!$frozenRecordId) {
            return $this->Response(false, "冻结资产子账户出账流水创建失败");
        }

        //冻结账户与账户主体本为一体，所以当冻结资产产生出账的同时账户本身也应该产生一次出账
        $sonAccountUpdateStatus = SonAccountModel::ConsumePointByFrozen($assetsInfoData->son_account_id, array(
            "frozen_point" => $point
        ));

        if (!$sonAccountUpdateStatus) {
            return $this->Response(false, "子账户冻结积分出账失败");
        }

        $consumeRecordId = AccountRecordModel::Create(array(
            'son_account_id' => $assetsInfoData->son_account_id,
            'bill_code' => $billCode,
            'frozen_record_id' => $frozenRecordId,
            'record_type' => 'reduce',
            'before_point' => $sonAccountInfo->point + $sonAccountInfo->frozen_point,
            'change_point' => $point,
            'after_point' => $sonAccountInfo->point + $sonAccountInfo->frozen_point - $point,
            'memo' => $memo,
        ));
        if (!$consumeRecordId) {
            return $this->Response(false, "子账户出账流水创建失败");
        }

        $incomeSonAccountInfo = SonAccountModel::Find($toAccountData['son_account_id']);
        if (!$incomeSonAccountInfo) {
            return $this->Response(false, "入账子账户已失效");
        }

        //收款账户入账
        $incomeStatus = SonAccountModel::IncomePoint($toAccountData['son_account_id'], array(
            "point" => $point
        ));
        if (!$incomeStatus) {
            return $this->Response(false, "入账子账户入账积分失败");
        }

        $incomeRecordId = AccountRecordModel::Create(array(
            'son_account_id' => $toAccountData['son_account_id'],
            'bill_code' => $billCode,
            'frozen_record_id' => -1,
            'record_type' => 'add',
            'before_point' => $incomeSonAccountInfo->point + $incomeSonAccountInfo->frozen_point,
            'change_point' => $point,
            'after_point' => $incomeSonAccountInfo->point + $incomeSonAccountInfo->frozen_point + $point,
            'memo' => $memo,
        ));
        if (!$incomeRecordId) {
            return $this->Response(false, "子账户入账流水创建失败");
        }

        return $this->Response(true, "转帐成功");
    }

    public function Query($billList)
    {
        $billServer = new BillServer();
        $billListRes = $billServer->QueryByTransfer($billList);
        return $billListRes;
    }


    /**
     * 从指定账户扣除指定金额
     * 该方法必须存在于事务中
     */
    private function TransferredByAccount($billCode, $account, $point, $overdueTime, $extendData = array())
    {
        return $this->Response(false, "待实现");
    }

}
