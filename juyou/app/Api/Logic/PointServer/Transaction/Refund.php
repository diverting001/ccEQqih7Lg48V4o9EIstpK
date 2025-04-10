<?php
/**
 * Created by PhpStorm.
 * User: zhaolong
 * Date: 2019-01-24
 * Time: 11:27
 */

namespace App\Api\Logic\PointServer\Transaction;

use App\Api\V1\Service\PointServer\Bill as BillServer;
use App\Api\Model\PointServer\Account as AccountModel;
use App\Api\Model\PointServer\SonAccount as SonAccountModel;
use App\Api\Model\PointServer\AccountConsume as AccountConsumeModel;
use App\Api\Model\PointServer\AccountRecord as AccountRecordModel;
use App\Api\Model\PointServer\AccountRefund as AccountRefundModel;

class Refund extends ATransaction
{
    const TRANSACTION_TYPE = 'refund';

    /**
     * 账户入账(账户从外部收入积分)
     */
    public function Execute($transactionData)
    {
        $consumeBillCode = $transactionData['consume_bill_code'];
        $account         = $transactionData['account'];
        $refundData      = $transactionData['refund_data'];
        $memo            = $transactionData['memo'] ? $transactionData['memo'] : "";

        $consumeList = AccountConsumeModel::GetConsumeByBillCode($consumeBillCode);
        if (!$consumeList || $consumeList->count() <= 0) {
            return $this->Response(false, "账单消费信息不存在");
        }
        //一个账单只会对应一个主账户
        $souAccountId = $consumeList[0]->son_account_id;

        $accountInfo = SonAccountModel::GetAccountInfoBySonAccountId($souAccountId);
        $billAccount = $accountInfo->account;
        if ($account != $billAccount) {
            return $this->Response(false, "账单对应主账户不匹配", array(), '10000');
        }

        $accountId   = $accountInfo->account_id;
        $refundPoint = $curPoint = $refundData['point'];

        $consumePoint = 0;
        foreach ($consumeList as $consumeInfo) {
            $consumePoint += ($consumeInfo->point - $consumeInfo->refund_point);
        }
        if ($refundPoint - $consumePoint  > 0.001) {
            return $this->Response(false, "退款超出上限");
        }

        $billServer = new BillServer();
        app('db')->beginTransaction();
        //生成收入账单
        $billCreateRes = $billServer->Create(array("bill_type" => self::TRANSACTION_TYPE));
        if (!$billCreateRes['status']) {
            app('db')->rollBack();
            return $this->Response(false, '收入账单创建失败');
        }
        $billCode = $billCreateRes['data']['bill_code'];

        foreach ($consumeList as $consumeInfo) {
            if (($consumeInfo->point - $consumeInfo->refund_point) <= 0 || $curPoint <= 0) {
                continue;
            }

            $sonRefundPoint  = min(($consumeInfo->point - $consumeInfo->refund_point), $curPoint);
            $sonRefundStatus = $this->RefundSonAccount(
                $billCode,
                $consumeBillCode,
                $consumeInfo,
                $sonRefundPoint,
                array(
                    "memo" => $memo
                )
            );


            if (!$sonRefundStatus['status']) {
                app('db')->rollBack();
                return $sonRefundStatus;
            }
            $curPoint -= $sonRefundPoint;
        }

        if ($curPoint > 0) {
            app('db')->rollBack();
            return $this->Response(false, '退款异常【1】');
        }

        app('db')->commit();
        return $this->Response(true, "退款成功", array(
            "bill_code" => $billCode
        ));
    }

    private function RefundSonAccount(
        $billCode,
        $consumeBillCode,
        $sonAccountInfo,
        $sonRefundPoint,
        $extendData = array()
    ) {
        $consumeId       = $sonAccountInfo->consume_id;
        $sonAccountId    = $sonAccountInfo->son_account_id;
        $memo            = $extendData['memo'] ? $extendData['memo'] : '';
        $refundCreateRes = AccountRefundModel::Create(array(
            'bill_code'         => $billCode,
            'son_account_id'    => $sonAccountId,
            'consume_bill_code' => $consumeBillCode,
            'point'             => $sonRefundPoint,
            'memo'              => $memo
        ));
        if (!$refundCreateRes) {
            return $this->Response(false, "退款数据创建失败");
        }

        $consumeRefundStatus = AccountConsumeModel::RefundPoint($consumeId, array(
            "refund_point" => $sonRefundPoint
        ));
        if (!$consumeRefundStatus) {
            return $this->Response(false, '消费单变更失败');
        }

        $refundStatus = SonAccountModel::RefundPoint($sonAccountId, array(
            "refund_point" => $sonRefundPoint
        ));
        if (!$refundStatus) {
            return $this->Response(false, '退款子账单变更失败');
        }

        $refundRecordId = AccountRecordModel::Create(array(
            'son_account_id'   => $sonAccountId,
            'bill_code'        => $billCode,
            'frozen_record_id' => -1,
            'record_type'      => 'add',
            'before_point'     => $sonAccountInfo->accont_point + $sonAccountInfo->accont_frozen_point,
            'change_point'     => $sonRefundPoint,
            'after_point'      => $sonAccountInfo->accont_point + $sonAccountInfo->accont_frozen_point + $sonRefundPoint,
            'memo'             => $memo,
        ));
        if (!$refundRecordId) {
            return $this->Response(false, "子账户入账流水创建失败");
        }

        return $this->Response(true, "退款成功");
    }

    public function Query($billList)
    {
        return $this->Response(false, "查询失败");
    }
}
