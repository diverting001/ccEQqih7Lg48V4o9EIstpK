<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\Model\Distribution;

use Mockery\Exception;

/**
 * 分销商 model
 *
 * @package     api
 * @category    Controller
 * @author        xupeng
 */
class Distributor
{
    /**
     * 获取分销商信息
     *
     * @param   $dtBn       string      分销商标识
     * @return  array
     */
    public function getDistributorInfo($dtBn)
    {
        $return = array();

        if (empty($dtBn)) {
            return $return;
        }

        $where = [
            'dt_bn' => $dtBn,
        ];
        $return = app('api_db')->table('server_distributors')->where($where)->first();

        return $return ? get_object_vars($return) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 添加分销商信息
     *
     * @param   $dtBn       string      分销商标识
     * @param   $dtName     string      分销商名称
     * @param   $status     mixed       状态
     * @param   $memo       mixed       备注
     * @return  boolean
     */
    public function addDistributor($dtBn, $dtName, $status = null, $memo = null)
    {
        if (empty($dtBn) OR empty($dtName)) {
            return false;
        }

        $addData = array(
            'dt_bn' => $dtBn,
            'dt_name' => $dtName,
            'status' => $status === null ? 1 : intval($status),
            'memo' => $memo ? $memo : '',
            'create_time' => time(),
            'update_time' => time(),
        );

        return app('api_db')->table('server_distributors')->insert($addData);
    }

    // --------------------------------------------------------------------

    /**
     * 更新分销商信息
     *
     * @param   $dtBn       string      分销商标识
     * @param   $dtName     string      分销商名称
     * @param   $status     mixed       状态
     * @param   $memo       mixed       备注
     * @return  boolean
     */
    public function updateDistributor($dtBn, $dtName, $status = null, $memo = null)
    {
        if (empty($dtBn) OR empty($dtName)) {
            return false;
        }

        $updateData = array(
            'dt_bn' => $dtBn,
            'dt_name' => $dtName,
            'update_time' => time(),
        );

        if ($status !== null) {
            $updateData['status'] = intval($status);
        }

        if ($memo !== null) {
            $updateData['memo'] = $memo;
        }

        $where = [
            'dt_bn' => $dtBn,
        ];

        return app('api_db')->table('server_distributors')->where($where)->update($updateData);
    }

    // --------------------------------------------------------------------

    /**
     * 获取分销商商品范围
     *
     * @param   $dtBn       string      分销商标识
     * @return  array
     */
    public function getDistributorGoodsScope($dtBn)
    {
        $return = array();

        if (empty($dtBn)) {
            return $return;
        }

        $where = [
            'dt_bn' => $dtBn,
            'status' => 1,
        ];

        $result = app('api_db')->table('server_distributor_goods_scope')->where($where)->get();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取所有分销商商品范围
     *
     * @return  array
     */
    public function getAllDistributorGoodsScope()
    {
        $return = array();

        $where = [
            'status' => 1,
        ];

        $result = app('api_db')->table('server_distributor_goods_scope')->where($where)->get();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[$v->dt_bn][] = get_object_vars($v);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 检查扣除资金池
     *
     * @param   $dtBn       string      分销商标识
     * @param   $money      float       金额
     * @param   $errMsg     string      错误信息
     * @return  boolean
     */
    public function checkDeductMoneyPool($dtBn, $money, & $errMsg = '')
    {
        if (empty($dtBn) OR $money <= 0) {
            return false;
        }

        $where = [
            'dt_bn' => $dtBn,
            'status' => '1',
            ['credit_limit', '>=', $money],
        ];

        $result = app('api_db')->table('server_distributor_money_pool')->where($where)->first();

        return !empty($result) ? get_object_vars($result) : false;
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
            return false;
        }

        // 检查资金池余额
        if (!$this->checkDeductMoneyPool($dtBn, $money)) {
            return false;
        }

        //开启事务
        app('db')->beginTransaction();

        // 添加交易记录
        try {
            $tradeNo = $this->addTradeLog($dtBn, $money, $outTradeNo);
        } catch (Exception $e) {
            app('db')->rollBack();
            $errMsg = '添加交易记录失败';
            return false;
        }

        if (empty($tradeNo)) {
            app('db')->rollBack();
            $errMsg = '添加交易记录失败';
            return false;
        }

        $where = [
            'dt_bn' => $dtBn,
            'status' => 1,
            ['credit_limit', '>=', $money],
        ];

        $result = app('api_db')->table('server_distributor_money_pool')->where($where)->increment('used_money', $money);
        if (!$result) {
            app('db')->rollBack();
            $errMsg = '修改资金池信息失败';
            return false;
        }

        $updateData = array(
            'update_time' => time(),
        );
        $result = app('api_db')->table('server_distributor_money_pool')->where($where)->decrement('credit_limit',
            $money, $updateData);
        if (!$result) {
            app('db')->rollBack();
            $errMsg = '余额不足';
            return false;
        }
        app('db')->commit();

        return array(
            'dt_bn' => $dtBn,
            'money' => $money,
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
        );
    }

    // --------------------------------------------------------------------

    /**
     * 资金池退还
     *
     * @param   $money              float       金额
     * @param   $outTradeNo         string      外部交易号
     * @param   $originOutTradeNo   string      原始外部交易号
     * @param   $errMsg             string      错误信息
     * @return  mixed
     */
    public function refundMoneyPool($money, $outTradeNo, $originOutTradeNo, & $errMsg = '')
    {
        if ($money <= 0 OR empty($outTradeNo) OR empty($originOutTradeNo)) {
            return false;
        }

        $originTradeLog = $this->getTradeLog($originOutTradeNo);

        if (empty($originTradeLog)) {
            $errMsg = '未获取到扣款记录';
            return false;
        }

        $dtBn = $originTradeLog['dt_bn'];

        if ($money > $originTradeLog['money']) {
            $errMsg = '超出金额';
            return false;
        }

        // 获取退还记录
        $refundTradeLog = $this->getTradeLogByOriginOutTradeNo($originOutTradeNo, 'refund');

        // 已退还金额
        $refundedMoney = 0;

        foreach ($refundTradeLog as $log) {
            $refundedMoney += $log['money'];
        }

        if (bcadd($refundedMoney, $money, 3) > $originTradeLog['money']) {
            $errMsg = '超出金额';
            return false;
        }

        //开启事务
        app('db')->beginTransaction();

        // 添加交易记录
        try {
            $tradeNo = $this->addTradeLog($dtBn, $money, $outTradeNo, $originOutTradeNo, 'refund');
        } catch (Exception $e) {
            app('db')->rollBack();
            $errMsg = '添加交易记录失败';
            return false;
        }

        if (empty($tradeNo)) {
            app('db')->rollBack();
            $errMsg = '添加交易记录失败';
            return false;
        }

        $where = [
            'dt_bn' => $dtBn,
            'status' => 1,
            ['credit_limit', '>=', $money],
        ];

        $result = app('api_db')->table('server_distributor_money_pool')->where($where)->decrement('used_money', $money);
        if (!$result) {
            app('db')->rollBack();
            $errMsg = '修改资金池信息失败';
            return false;
        }

        $updateData = array(
            'update_time' => time(),
        );
        $result = app('api_db')->table('server_distributor_money_pool')->where($where)->increment('credit_limit',
            $money, $updateData);
        if (!$result) {
            app('db')->rollBack();
            $errMsg = '修改资金池信息失败';
            return false;
        }
        app('db')->commit();

        return array(
            'dt_bn' => $dtBn,
            'money' => $money,
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
        );
    }

    // --------------------------------------------------------------------

    /**
     * 添加交易记录
     *
     * @param   $dtBn               string      分销商标识
     * @param   $money              float       金额
     * @param   $outTradeNo         string      外部交易号
     * @param   $originOutTradeNo   string      原始外部交易号
     * @param   $type               string      类型
     * @return  mixed
     */
    public function addTradeLog($dtBn, $money, $outTradeNo, $originOutTradeNo = null, $type = 'deduct')
    {
        if (empty($dtBn) OR $money <= 0 OR empty($outTradeNo)) {
            return false;
        }

        $tradeLog = $this->getTradeLog($outTradeNo);

        if (!empty($tradeLog)) {
            return false;
        }

        // 获取当前毫秒数
        $time = explode(' ', microtime());

        // 生成交易流水号
        $tradeNo = date('YmdHis') . (round($time[0] * 1000)) . mt_rand(1000, 9999);

        $addData = array(
            'dt_bn' => $dtBn,
            'money' => $money,
            'out_trade_no' => $outTradeNo,
            'origin_out_trade_no' => $originOutTradeNo,
            'trade_no' => $tradeNo,
            'type' => $type,
            'create_time' => time(),
            'update_time' => time(),
        );

        $result = app('api_db')->table('server_distribution_log')->insert($addData);

        return $result ? $tradeNo : false;
    }

    // --------------------------------------------------------------------

    /**
     * 获取交易记录
     *
     * @param   $outTradeNo     string      交易号
     * @return  mixed
     */
    public function getTradeLog($outTradeNo)
    {
        if (empty($outTradeNo)) {
            return array();
        }

        $where = [
            'out_trade_no' => $outTradeNo,
        ];
        $result = app('api_db')->table('server_distribution_log')->where($where)->first();

        return $result ? get_object_vars($result) : array();
    }

    // --------------------------------------------------------------------

    /**
     * 通过原始交易号获取交易记录
     *
     * @param   $originOutTradeNo     string      原始交易号
     * @param   $type                 string      类型
     * @return  mixed
     */
    public function getTradeLogByOriginOutTradeNo($originOutTradeNo, $type = 'refund')
    {
        $return = array();

        if (empty($originOutTradeNo)) {
            return $return;
        }

        $where = [
            'origin_out_trade_no' => $originOutTradeNo,
            'type' => $type,
        ];
        $result = app('api_db')->table('server_distribution_log')->where($where)->get();

        if (empty($result)) {
            return $return;
        }

        foreach ($result as $v) {
            $return[] = get_object_vars($v);
        }

        return $return;
    }

}
