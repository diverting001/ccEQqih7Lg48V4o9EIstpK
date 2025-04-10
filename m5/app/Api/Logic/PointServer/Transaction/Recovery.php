<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-28
 * Time: 16:34
 */

namespace App\Api\Logic\PointServer\Transaction;

use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\Model\PointServer\AccountTransfer as AccountTransferModel;
use App\Api\Model\PointServer\FrozenPoolRecord as FrozenPoolRecordModel;
use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\FrozenPool as FrozenPoolModel;
use App\Api\Model\PointServer\FrozenAssets as FrozenAssetsModel;
use App\Api\Model\PointServer\FrozenAssetsInfo as FrozenAssetsInfoModel;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;

class Recovery extends ATransaction
{

    const TRANSACTION_TYPE = 'transfer';

    /**
     * 账户回收
     */
    public function Execute($transactionData)
    {
        $account         = $transactionData['account'];
        $assignBillCodes = $transactionData['assign_flow_code'];
        $point           = $curPoint = $transactionData['point'];
        $memo            = $transactionData['memo'] ? $transactionData['memo'] : "";

        $billServer = new BillServer();
        app('db')->beginTransaction();
        //生成收入账单
        $billCreateRes = $billServer->Create(array("bill_type" => self::TRANSACTION_TYPE));
        if (!$billCreateRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, '收入账单创建失败');
        }
        $billCode = $billCreateRes['data']['bill_code'];

        $sonAccountList = SonAccountModel::QueryByAccountBns(array($account));

        $sonAccountIds = [];
        foreach ($sonAccountList as $sonAccount) {
            $sonAccountIds[] = $sonAccount->son_account_id;
        }

        $assignTransferList = AccountTransferModel::QueryByBillAndToAccount($assignBillCodes, $sonAccountIds);


        if ($assignTransferList->count() <= 0) {
            app('db')->rollBack();
            return $this->Response(false, '转账信息查询失败');
        }

        foreach ($assignTransferList as $assignTransfer) {
            if ($curPoint <= 0) {
                break;
            }

            $sonAccountId   = $assignTransfer->son_account_id;
            $toSonAccountId = $assignTransfer->to_son_account_id;

            //$toSAccInfo 是用户子账户
            $toSAccInfo = SonAccountModel::Find($toSonAccountId);
            $sAccInfo   = SonAccountModel::Find($sonAccountId);

            $paymenPoint = min($curPoint, $toSAccInfo->point,$assignTransfer->point);
            if ($paymenPoint <= 0) {
                continue;
            }

            $transferRes = $this->SonAccountRevocery($billCode, $toSAccInfo, $sAccInfo, $paymenPoint, $memo);
            if (!$transferRes['status']) {
                app('db')->rollBack();
                return $transferRes;
            }

            $curPoint -= $paymenPoint;
        }

        if ($curPoint > 0) {
            app('db')->rollBack();
            return $this->Response(false, '账户金额不足');
        }

        app('db')->commit();

        $cAccuntInfo = SonAccountModel::GetSonAccountInfoBySonAccountId($sonAccountId);

        return $this->Response(true, '回收成功', array(
            "bill_code" => $billCode,
            "to_account" => $cAccuntInfo->account
        ));
    }

    /**
     * @param $billCode
     * @param $sonAccount
     * @param $toSonAccount
     * @param $point
     * @param string $memo
     * @return array
     */
    private function SonAccountRevocery($billCode, $sonAccount, $toSonAccount, $point, $memo = '')
    {
        $memberSAId  = $sonAccount->son_account_id;
        $companySAid = $toSonAccount->son_account_id;

        app('db')->beginTransaction();
        $transferCreateRes = AccountTransferModel::Create(array(
            'bill_code'         => $billCode,
            'son_account_id'    => $memberSAId,
            'to_son_account_id' => $companySAid,
            'point'             => $point,
            'memo'              => $memo,
        ));
        if (!$transferCreateRes) {
            app('db')->rollBack();
            return $this->Response(false, '转帐交易数据创建失败');
        }

        $consumeStatus = SonAccountModel::ConsumePoint($memberSAId, array(
            "point" => $point
        ));
        if (!$consumeStatus) {
            app('db')->rollBack();
            return $this->Response(false, '用户子账户出账失败');
        }
dump($companySAid, array(
    "refund_point" => $point
));
        $refundStatus = SonAccountModel::RefundPoint($companySAid, array(
            "refund_point" => $point
        ));
        if (!$refundStatus) {
            app('db')->rollBack();
            return $this->Response(false, '用户子账户入账失败');
        }

        $consumeRecordId = AccountRecordModel::Create(array(
            'son_account_id'   => $memberSAId,
            'bill_code'        => $billCode,
            'frozen_record_id' => -1,
            'record_type'      => 'reduce',
            'before_point'     => $sonAccount->point + $sonAccount->frozen_point,
            'change_point'     => $point,
            'after_point'      => $sonAccount->point + $sonAccount->frozen_point - $point,
            'memo'             => $memo,
        ));
        if (!$consumeRecordId) {
            app('db')->rollBack();
            return $this->Response(false, '子账户出账流水创建失败');
        }

        $incomeRecordId = AccountRecordModel::Create(array(
            'son_account_id'   => $companySAid,
            'bill_code'        => $billCode,
            'frozen_record_id' => -1,
            'record_type'      => 'add',
            'before_point'     => $toSonAccount->point + $toSonAccount->frozen_point,
            'change_point'     => $point,
            'after_point'      => $toSonAccount->point + $toSonAccount->frozen_point + $point,
            'memo'             => $memo,
        ));
        if (!$incomeRecordId) {
            return $this->Response(false, "子账户入账流水创建失败");
        }

        app('db')->commit();
        return $this->Response(true, "回收成功");
    }

    public function Query($billList)
    {
        return $this->Response(false, "查询失败");
    }

}
