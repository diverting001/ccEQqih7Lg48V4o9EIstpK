<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Distribution;

use App\Api\Model\Distribution\Distributor as DistributorModel;

/**
 * 分销商 Controller
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class Distributor
{
    /**
     * 保存分销商信息
     *
     * @param   $dtBn       string      分销商标识
     * @param   $dtName     string      分销商名称
     * @param   $status     mixed       状态
     * @param   $memo       mixed       备注
     * @param   $errMsg     string      错误信息
     * @return  mixed
     */
    public function saveDistributorInfo($dtBn, $dtName, $status = null, $memo = null, & $errMsg = '')
    {
        if (empty($dtBn) OR empty($dtName)) {
            $errMsg = '参数错误';
            return false;
        }

        $dtModel = new DistributorModel();

        // 获取分销商信息
        $dtInfo = $dtModel->getDistributorInfo($dtBn);

        // 添加分销商
        if (empty($dtInfo)) {
            if (!$dtModel->addDistributor($dtBn, $dtName, $status, $memo)) {
                $errMsg = '添加分销商失败';
                return false;
            }
        } else {
            if ($dtInfo['dt_name'] != $dtName OR $dtInfo['status'] != $status OR $dtInfo['memo'] != $memo) {
                if (!$dtModel->updateDistributor($dtBn, $dtName, $status, $memo)) {
                    $errMsg = '更新分销商失败';
                    return false;
                }
            }
        }

        // 获取分销商信息
        $dtInfo = $dtModel->getDistributorInfo($dtBn);

        return $dtInfo;
    }

    // --------------------------------------------------------------------

    /**
     * 资金池扣除
     *
     * @param   $dtBn           string      分销商标识
     * @param   $money          float       金额
     * @param   $outTradeNo     string      外部交易号
     * @param   $errMsg         string      错误信息
     * @return  mixed
     */
    public function deductMoneyPool($dtBn, $money, $outTradeNo, & $errMsg = '')
    {
        if (empty($dtBn) OR $money <= 0 OR empty($outTradeNo)) {
            $errMsg = '参数错误';
            return false;
        }

        $dtModel = new DistributorModel();

        // 获取外部交易号记录
        $tradeLog = $dtModel->getTradeLog($outTradeNo);

        if (!empty($tradeLog)) {
            $errMsg = '重复的交易记录';
            return false;
        }

        $errMsg = '';
        $tradeLog = $dtModel->deductMoneyPool($dtBn, $money, $outTradeNo, $errMsg);

        if (empty($tradeLog)) {
            $errMsg OR $errMsg = '资金池扣除失败';
            return false;
        }

        return $tradeLog;
    }

    // --------------------------------------------------------------------

    /**
     * 资金池扣除退还
     *
     * @param   $money              float       金额
     * @param   $originOutTradeNo   string      原始外部交易号
     * @param   $outTradeNo         string      外部交易号
     * @param   $errMsg             string      错误信息
     * @return  mixed
     */
    public function refundMoneyPool($money, $outTradeNo, $originOutTradeNo, & $errMsg = '')
    {
        if ($money <= 0 OR empty($outTradeNo) OR empty($originOutTradeNo)) {
            $errMsg = '参数错误';
            return false;
        }

        $dtModel = new DistributorModel();

        // 获取外部交易号记录
        $tradeLog = $dtModel->getTradeLog($outTradeNo);

        if (!empty($tradeLog)) {
            $errMsg = '重复的交易记录';
            return false;
        }

        $errMsg = '';
        $tradeLog = $dtModel->refundMoneyPool($money, $outTradeNo, $originOutTradeNo, $errMsg);

        if (empty($tradeLog)) {
            $errMsg OR $errMsg = '资金池退还失败';
            return false;
        }

        return $tradeLog;
    }

}
