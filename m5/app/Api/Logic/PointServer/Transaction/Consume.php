<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-02-12
 * Time: 19:02
 */

namespace App\Api\Logic\PointServer\Transaction;

use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\V1\Service\PointServer\Account as AccountServer;
use App\Api\Model\PointServer\FrozenPoolRecord as FrozenPoolRecordModel;
use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\FrozenPool as FrozenPoolModel;
use App\Api\Model\PointServer\FrozenAssets as FrozenAssetsModel;
use App\Api\Model\PointServer\FrozenAssetsInfo as FrozenAssetsInfoModel;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;
use App\Api\Model\PointServer\AccountConsume as AccountConsumeModel;

class Consume extends ATransaction
{
    const TRANSACTION_TYPE = 'consume';

    /**
     * 账户入账(账户从外部收入积分)
     */
    public function Execute($transactionData)
    {
        $frozenPoolCodeList = $transactionData['frozen_pool_code_list'];
        $account = $transactionData['account'];
        $point = $transactionData['point'];
        $memo = $transactionData['memo'] ? $transactionData['memo'] : "";

        $accountServer = new AccountServer();
        $paymenAccountRes = $accountServer->GetValidAccount($account);
        if (!$paymenAccountRes['status']) {
            return $this->Response(false, '出账账户无效');
        }

        $paymenAccountId = $paymenAccountRes['data']->account_id;

        $billServer = new BillServer();
        app('db')->beginTransaction();
        //生成收入账单
        $billCreateRes = $billServer->Create(array("bill_type" => self::TRANSACTION_TYPE));
        if (!$billCreateRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, '出账账单创建失败');
        }
        $billCode = $billCreateRes['data']['bill_code'];

        //转帐用户出账
        if ($frozenPoolCodeList) {
            $consumeRes = $this->ConsumeByFrozenPoolList(
                $frozenPoolCodeList,
                $billCode,
                array(
                    'account_id' => $paymenAccountId
                ),
                $point,
                array("memo" => $memo)
            );
            if (!$consumeRes['status']) {
                app('db')->rollBack();
                return $consumeRes;
            }
        } else {
            $consumeRes = $this->ConsumeByAccount($billCode, array('account_id' => $paymenAccountId), $point);
            if (!$consumeRes['status']) {
                app('db')->rollBack();
                return $consumeRes;
            }
        }
        app('db')->commit();
        return $this->Response(true, '转账成功', array("bill_code" => $billCode));
    }

    private function ConsumeByFrozenPoolList($frozenPoolCodeList, $billCode, $accountData, $point, $extendData = array())
    {
        $poolList = FrozenPoolModel::QueryAvailableList($frozenPoolCodeList);
        if ($poolList->count() <= 0) {
            return $this->Response(false, '无可用锁定池');
        }

        $curPoint = $point;
        foreach ($poolList as $frozenPoolInfo) {
            if ($curPoint <= 0) {
                break;
            }
            $paymenPoint = min($curPoint, $frozenPoolInfo->frozen_point);
            $consumeRes = $this->ConsumeByFrozenPool($frozenPoolInfo->frozen_pool_code, $billCode, $accountData, $paymenPoint, $extendData);
            if (!$consumeRes['status']) {
                return $consumeRes;
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
    private function ConsumeByFrozenPool($frozenPoolCode, $billCode, $accountData, $point, $extendData = array())
    {
        $poolInfo = FrozenPoolModel::QueryAvailable($frozenPoolCode);
        if (!$poolInfo) {
            return $this->Response(false, '锁定池无效');
        }

        $assetsList = FrozenAssetsModel::QueryAvailableListByPoolAndAccount($poolInfo->frozen_pool_id, $accountData['account_id']);
        $curPoint = $point;
        foreach ($assetsList as $assetsData) {
            if ($curPoint <= 0) {
                break;
            }
            $paymenPoint = min($curPoint, $assetsData->frozen_point);
            $consumeRes = $this->ConsumeByAssets($billCode, $assetsData, $paymenPoint);
            if (!$consumeRes['status']) {
                return $consumeRes;
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

    private function ConsumeByAssets($billCode, $assetsData, $point, $extendData = array())
    {
        $frozenAssetsId = $assetsData->frozen_assets_id;
        $assetsInfoList = FrozenAssetsInfoModel::QueryAvailable($frozenAssetsId);

        $curPoint = $point;
        foreach ($assetsInfoList as $assetsInfoData) {
            if ($curPoint <= 0) {
                break;
            }
            $paymenPoint = min($curPoint, $assetsInfoData->frozen_point);
            $consumeInfoRes = $this->ConsumeByAssetsInfo($billCode, $assetsInfoData, $paymenPoint, $extendData);
            if (!$consumeInfoRes['status']) {
                return $consumeInfoRes;
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

        return $this->Response(true, "出账成功");
    }

    private function ConsumeByAssetsInfo($billCode, $assetsInfoData, $point, $extendData = array())
    {
        $memo = $extendData['memo'] ? $extendData['memo'] : '';
        //生成交易数据
        $consumeCreateRes = AccountConsumeModel::Create(array(
            'bill_code' => $billCode,
            'son_account_id' => $assetsInfoData->son_account_id,
            'point' => $point,
            'memo' => $memo,
        ));
        if (!$consumeCreateRes) {
            return $this->Response(false, "出账交易数据创建失败");
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
        return $this->Response(true, "出账成功");
    }

    public function Query($billList)
    {
        return $this->Response(false, "查询失败");
    }

    /**
     * 从指定账户扣除指定金额
     * 该方法必须存在于事务中
     */
    private function ConsumeByAccount($billCode, $account, $point, $extendData = array())
    {
        return $this->Response(false, "待实现");
    }
}
