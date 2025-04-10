<?php

namespace App\Api\V1\Service\Voucher;

use App\Api\Model\Voucher\Voucher as VoucherModel;
use App\Api\Model\Voucher\VoucherMember as VoucherMemberModel;

/**
 * 券退还
 * @author zhaolong
 */
class Refund
{

    private $voucherModel;
    private $_db;

    public function __construct()
    {
        $this->_db = app('api_db')->connection('neigou_store');
        $this->voucherModel = new VoucherModel($this->_db);
    }

    /**
     * 退还券
     * @param $memberId
     * @param $refundInfo
     * @return array
     */
    public function Refund($memberId, $refundInfo)
    {
        $msg = '';
        $extendData = array();
        $checkStatus = $this->checkRefundCanQuit($memberId, $refundInfo, $extendData, $msg);
        if (!$checkStatus) {
            return $this->Response(400, $msg);
        }

        $oldVoucherId = $extendData['old_voucher_info']->voucher_id;
        $voucherMemberModel = new VoucherMemberModel($this->_db);

        $createVoucherData = array(
            'member_id' => $memberId,
            'money' => $refundInfo['money'],
            'count' => $refundInfo['count'],
            'type' => isset($refundInfo['type']) ? $refundInfo['type'] : null,
            'company_id' => $refundInfo['company_id'],
            'valid_time' => $refundInfo['valid_time'],
            'time_type' => !empty($refundInfo['time_type']) ? $refundInfo['time_type'] : '',
            'op_id' => $refundInfo['op_id'],
            'op_name' => $refundInfo['op_name'],
            'comment' => $refundInfo['comment'],
            'num_limit' => $refundInfo['num_limit'],
            'exclusive' => $refundInfo['exclusive'],
            'rule_id' => $refundInfo['rule_id'],
            'source_type' => $refundInfo['source_type'],
            'voucher_name' => $refundInfo['voucher_name'],
            'voucher_nature' => intval($refundInfo['voucher_nature'])
        );

        // 如果原券来源于券包
        if (isset($refundInfo['pkg_rule_id']) && $refundInfo['pkg_rule_id'] > 0){
            $createVoucherData['external_id'] = $refundInfo['pkg_rule_id'];
        }

        $this->_db->beginTransaction();
        $message = "";
        $createId = $voucherMemberModel->createVoucherForMember($memberId, $createVoucherData, $message);
        if (!$createId) {
            $this->_db->rollback();
            \Neigou\Logger::General('action.refund', array(
                'action' => 'createVoucherForMember',
                'member_id' => $memberId,
                'success' => 0,
                'create_data' => $createVoucherData,
                'reason' => 'insert_failed'
            ));
            return $this->Response(401, "创建券失败，请稍后重试!");
        }

        $relaStatus = $this->voucherModel->createRefundVoucherRela($oldVoucherId, $createId);
        if (!$relaStatus) {
            $this->_db->rollback();
            \Neigou\Logger::General('action.refund',
                array('action' => 'createRefundVoucherRela', 'sparam1' => $oldVoucherId, 'sparam2' => $createId));
            return $this->Response(402, "创建退还券关联失败，请稍后重试!");
        }

        $this->_db->commit();

        return $this->Response(200, "退还成功", ['create_id' => $createId]);
    }

    /**
     * 查看券是否可退
     * @param $memberId
     * @param $refundInfo
     * @param $extendData
     * @param $msg
     * @return bool
     */
    private function checkRefundCanQuit($memberId, $refundInfo, &$extendData, &$msg)
    {
        $voucherOrderId = $refundInfo['order_id'];
        $oldVoucherNumber = $refundInfo['old_voucher_number'];
        $voucherInfo = $this->voucherModel->queryVoucher($oldVoucherNumber);
        if (!$voucherInfo) {
            $msg = "券信息获取失败";
            return false;
        }

        if ($voucherInfo->status !== 'finish') {
            $msg = "未使用的券";
            return false;
        }

        if ($voucherInfo->type == 'discount') {
            $msg = "打折券退款失败";
            return false;
        }

        if ($voucherInfo->money - $refundInfo['money'] < -0.01) {
            $msg = "退还金额错误[1]";
            return false;
        }

        $oldVoucherId = $voucherInfo->voucher_id;
        $refundMoney = $this->voucherModel->queryRefundVoucherMoney($oldVoucherId);
        if ($voucherInfo->money - ($refundInfo['money'] + $refundMoney) < -0.01) {
            $msg = "退还金额错误[2]";
            return false;
        }

        $useVoucherListRes = $this->voucherModel->queryOrderVoucher($voucherOrderId);
        $useVoucherList = array();
        foreach ($useVoucherListRes as $val) {
            $useVoucherList[$val->number] = $val;
        }

        if (!array_key_exists($voucherInfo->number, $useVoucherList)) {
            $msg = "订单未使用该券";
            return false;
        }

        if ($useVoucherList[$voucherInfo->number]->member_id != $memberId) {
            $msg = "该券不属于该用户";
            return false;
        }

        $extendData['old_voucher_info'] = $voucherInfo;
        return true;
    }

    private function Response($error_code, $error_msg, $data = [])
    {
        return [
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'data' => $data,
        ];
    }

}
