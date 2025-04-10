<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Settlement;

use App\Api\Model\Settlement\Channel as SettlementChannelModel;
use App\Api\Model\Settlement\Rule as SettlementRuleModel;
use App\Api\Logic\Service as Service;

/**
 * 账户 Service
 *
 * @package     api
 * @category    Service
 * @author      xupeng
 */
class Channel
{
    /**
     * 创建渠道
     *
     * @param   $name           string      名称
     * @param   $memo           string      备注
     * @return  array
     */
    public function createChannel($name, $memo = '')
    {
        $channelModel = new SettlementChannelModel();

        // 创建账户信息
        $data = array(
            'name'  => $name,
            'code'   => self::_createChannelCode(),
            'memo'  => $memo ? $memo : '', // 备注
        );

        $id = $channelModel->createChannel($data);

        if ($id <= 0) {
            return array();
        }

        return $channelModel->getChannelInfo($id);
    }

    // --------------------------------------------------------------------

    /**
     * 更新渠道
     *
     * @param   $scId           int         通道ID
     * @param   $name           string      名称
     * @param   $memo           string      备注
     * @return  boolean
     */
    public function updateChannel($scId, $name, $memo = null)
    {
        $channelModel = new SettlementChannelModel();

        $channelInfo = $channelModel->getChannelInfo($scId);

        if (empty($channelInfo))
        {
            return false;
        }

        if ($channelInfo['name'] == $name && ($memo === null OR $channelInfo['memo'] == $memo))
        {
            return true;
        }
        // 创建账户信息
        $data = array(
            'name'  => $name,
            'memo'  => $memo !== null ? $memo : $channelInfo['memo'], // 备注
        );

        if ( ! $channelModel->updateChannel($scId, $data))
        {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道详情
     *
     * @param   $scId       int      渠道ID
     * @return  array
     */
    public function getChannelInfo($scId)
    {
        $channelModel = new SettlementChannelModel();

        $channelInfo = $channelModel->getChannelInfo($scId);

        if (empty($channelInfo))
        {
            return array();
        }

        // 获取绑定支付列表
        $channelPayment = $channelModel->getChannelPaymentById($scId);

        // 获取绑定账户列表
        $channelAccount = $channelModel->getChannelAccountById($scId);

        // 获取绑定规则
        $channelRules = $channelModel->getChannelRuleById($scId);

        $channelInfo['payment'] = $channelPayment;

        $channelInfo['accounts'] = $channelAccount;

        $channelInfo['sr_id'] = $channelRules;

        return $channelInfo;
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付 by company
     *
     * @param   $companyId      int         公司ID
     * @param   $paymentList    array       支付列表
     *          payment_type
     *          payment_item
     *          sc_id
     * @return  boolean
     */
    public function bindPaymentByCompany($companyId, $paymentList)
    {
        $channelModel = new SettlementChannelModel();

        $data = array();

        if ( ! empty($paymentList))
        {
            foreach ($paymentList as $v)
            {
                $data[$v['payment_type']][$v['payment_item']] = $v['sc_id'];
            }
        }

        $addPaymentList = $deleteIds = $updatePaymentList = array();

        $hasPaymentList = $channelModel->getPaymentByCompany($companyId);

        if ( ! empty($hasPaymentList))
        {
            foreach ($hasPaymentList as $v)
            {
                if ( ! empty($data[$v['payment_type']]) && ! empty($data[$v['payment_type']][$v['payment_item']]))
                {
                    if ($v['sc_id'] != $data[$v['payment_type']][$v['payment_item']])
                    {
                        $updatePaymentList[$v['id']] = $data[$v['payment_type']][$v['payment_item']];
                    }
                    unset($data[$v['payment_type']][$v['payment_item']]);
                    continue;
                }

                $deleteIds[] = $v['id'];
            }
        }

        if ( ! empty($data))
        {
            foreach ($data as $type => $payments)
            {
                foreach ($payments as $paymentItem => $scId)
                {
                    $addPaymentList[] = array(
                        'payment_type'  => $type,
                        'payment_item'  => $paymentItem,
                        'sc_id'         => $scId,
                    );
                }
            }
        }

        if ( ! empty($addPaymentList) OR ! empty($deleteIds) OR ! empty($updatePaymentList))
        {
            //开启事务
            app('db')->beginTransaction();
        }

        // 添加
        if ( ! empty($addPaymentList))
        {
            foreach ($addPaymentList as $v)
            {
                if ( ! $channelModel->addBindPayment($v['sc_id'], $companyId, $v['payment_type'], $v['payment_item']))
                {
                    app('db')->rollback();
                    return false;
                }
            }
        }

        // 删除
        if ( ! empty($deleteIds))
        {
            if ( ! $channelModel->deleteBindPayment($deleteIds))
            {
                app('db')->rollback();
                return false;
            }
        }

        // 更新
        if ( ! empty($updatePaymentList))
        {
            foreach ($updatePaymentList as $id => $scId)
            {
                if ( ! $channelModel->updateBindPaymentById($id, $scId))
                {
                    app('db')->rollback();
                    return false;
                }
            }
        }

        if ( ! empty($addPaymentList) OR ! empty($deleteIds) OR ! empty($updatePaymentList))
        {
            // 提交
            app('db')->commit();
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 绑定规则
     *
     * @param   $scId       int      结算通道ID
     * @param   $srId       int      规则ID
     * @return  mixed
     */
    public function bindRule($scId, $srId)
    {
        $channelModel = new SettlementChannelModel();

        $result = $channelModel->bindRule($scId, $srId);

        if ( ! $result)
        {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付方式
     *
     * @param   $scId           int     结算通道ID
     * @param   $accountType    string  账户类型
     * @param   $accountBn      string  账户编码
     * @return  mixed
     */
    public function bindAccount($scId, $accountType, $accountBn)
    {
        $channelModel = new SettlementChannelModel();

        $result = $channelModel->bindAccount($scId, $accountType, $accountBn);

        if ( ! $result)
        {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 获取渠道列表
     *
     * @param   $page       int     页数
     * @param   $pageSize   int     每页数量
     * @param   $filter     array   过滤
     * @return  array
     */
    public function getChannelList($page = 1, $pageSize = 20, $filter = array())
    {
        $channelModel = new SettlementChannelModel();

        // 获取总数量
        $count = $channelModel->getChannelCount($filter);

        $totalPage = ceil((int)$count / $pageSize);

        $return = array(
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_count'   => $count,
            'total_page'    => $totalPage,
            'data'          => array(),
        );

        $offset = ($page - 1) * $pageSize;

        $data = $channelModel->getChannelList($pageSize, $offset, $filter);

        if ( ! empty($data))
        {
            $channelIds = array();
            foreach ($data as $v)
            {
                $channelIds[] = $v['sc_id'];
            }

            // 获取绑定支付列表
            $channelPayment = $channelModel->getChannelPaymentById($channelIds);

            // 获取绑定账户列表
            $channelAccount = $channelModel->getChannelAccountById($channelIds);

            // 获取绑定规则
            $channelRules = $channelModel->getChannelRuleById($channelIds);

            foreach ($data as & $v)
            {
                $scId = $v['sc_id'];
                $v['payment'] = isset($channelPayment[$scId]) ? $channelPayment[$scId] : array();
                $v['accounts'] = isset($channelAccount[$scId]) ? $channelAccount[$scId] : array();
                $v['sr_id'] = isset($channelRules[$scId]) ? $channelRules[$scId] : 0;
            }

            $return['data'] = $data;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付信息 by company
     *
     * @param   $companyId     int   公司ID
     * @return  array
     */
    public function getPaymentByCompany($companyId)
    {
        $channelModel = new SettlementChannelModel();

        return $channelModel->getPaymentByCompany($companyId);
    }

    // --------------------------------------------------------------------

    /**
     * 订单付款
     *
     * @param   $companyId      int     公司
     * @param   $paymentList    array   支付列表
     * @param   $orderId        int     订单ID
     * @param   $orderInfo      array   订单信息
     * @param   $errMsg         string  错误信息
     * @param   $errCode        int     错误编码
     * @return  mixed
     */
    public function orderPay($companyId, $paymentList, $orderId, $orderInfo, & $errMsg = '', & $errCode = 400)
    {
        $channelModel = new SettlementChannelModel();
??????
        // 获取记录
        $filter = array(
            'type'          => 'pay',
            'trade_type'    => 'ORDER',
            'trade_bn'      => $orderId,
        );
        $recordList = $channelModel->getChannelRecordList($filter);

        if ( ! empty($recordList))
        {
            return true;
        }

        // 获取公司绑定的支付
        $payments = $channelModel->getPaymentByCompany($companyId);
        if (empty($payments))
        {
            $errCode = 1000;
            $errMsg = '未配置支付通道';
            return false;
        }

        /*
         * PAYMENT => array('cash' => $cashMoney),
         * POINT => array($this->request_data['point_channel'] => $pointAmount)
         */
        // 计算通道金额
        $scPayments = array();
        $scPaymentItems = array();
        $usePaymentList = array();
        foreach ($payments as $v)
        {
            if ( ! isset($paymentList[$v['payment_type']]))
            {
                continue;
            }

            $paymentInfo = $paymentList[$v['payment_type']];
            if ( ! isset($paymentInfo[$v['payment_item']]) OR $paymentInfo[$v['payment_item']] <= 0)
            {
                continue;
            }

            if ( ! isset($scPayments[$v['sc_id']]))
            {
                $scPayments[$v['sc_id']] = 0;
            }
            $scPayments[$v['sc_id']] += $paymentInfo[$v['payment_item']];
            $scPaymentItems[$v['sc_id']][] = $paymentInfo;
            $usePaymentList[$v['payment_type']][$v['payment_item']] = $v;
        }

        // 未找到结算通道则返回成功
        if (empty($scPayments))
        {
            $errCode = 1000;
            $errMsg = '未配置支付通道';
            return false;
        }

        // 计算邮费
        if ($orderInfo['cost_freight'] > 0 OR $orderInfo['pmt_amount'] > 0)
        {
            foreach ($paymentList as $paymentType => $paymentInfo)
            {
                foreach ($paymentInfo as $payItem => $amount)
                {
                    if ( ! isset($usePaymentList[$paymentType]) OR ! isset($usePaymentList[$paymentType][$payItem]))
                    {
                        $scale = bcdiv($amount, $orderInfo['final_amount'], 8);
                        $orderInfo['cost_freight'] = bcsub($orderInfo['cost_freight'], bcmul($orderInfo['cost_freight'], $scale, 2), 2);
                        $orderInfo['pmt_amount'] = bcsub($orderInfo['pmt_amount'], bcmul($orderInfo['pmt_amount'], $scale, 2), 2);
                        if ($orderInfo['cost_freight'] < 0)
                        {
                            $orderInfo['cost_freight'] = 0;
                        }

                        if ($orderInfo['pmt_amount'] < 0)
                        {
                            $orderInfo['pmt_amount'] = 0;
                        }
                    }
                }
            }
        }

        // 计算支付账户的付款列表
        $paymentAccountPayList = $this->_calcPaymentAccountPayList($scPayments, $orderInfo, $errMsg);

        if (empty($paymentAccountPayList))
        {
            return false;
        }

        // 支付账户订单支付
        $data = array(
            'order_id'          => $orderId,
            'order_category'    => $orderInfo['order_category'] ? $orderInfo['order_category'] : '',
            'company_id'        => $companyId,
            'source'            => 'SC',
            'pay_list'          => $paymentAccountPayList,
        );

        $service_logic = new Service();
        $result = $service_logic->ServiceCall('payment_account_order_pay', $data);

        if ($result['error_code'] != 'SUCCESS')
        {
            $errMsg = $result['error_msg'] ? $result['error_msg'][0] : '账户余额不足或扣款失败';
            \Neigou\Logger::General('settlement.channel.order.pay.failed', array('order_id' => $orderId, 'payment_list' => $paymentList, 'order_info' => $orderInfo, 'action' => 'payment_account_order_pay', 'request' => $data, 'result' => $result));
            return false;
        }

        // 保存支付结果
        $this->_saveOrderPaymentAccountResult($paymentAccountPayList, $result['data'], $scPaymentItems, $orderId);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 订单退款
     *
     * @param   $orderId        int     订单ID
     * @param   $itemList       array   列表
     * @param   $errMsg         string  错误信息
     * @return  mixed
     */
    public function orderRefund($orderId, $itemList, & $errMsg = '')
    {
        $channelModel = new SettlementChannelModel();

        // 获取记录
        $filter = array(
            'type'          => 'pay',
            'trade_type'    => 'ORDER',
            'trade_bn'      => $orderId,
        );
        $recordList = $channelModel->getChannelRecordList($filter);

        if (empty($recordList))
        {
            return true;
        }

        $scPayItemList = array();
        $isAllItem = empty($itemList) ? true : false;
        foreach ($recordList as $record)
        {
            // item 列表
            $payItemList = json_decode($record['item_list'], true);
            $scId = $record['sc_id'];
            foreach ($payItemList as $v)
            {
                $scPayItemList[$scId][$v['pa_id']][$v['product_bn']] = $v;
                if ($isAllItem)
                {
                    $itemList[$v['product_bn']] = array(
                        'product_bn'    => $v['product_bn'],
                        'num'           => $v['num'],
                    );
                }
            }
        }

        // 获取已经退款的商品
        $hasRefundItemList = $this->_getOrderHasRefundItemList($orderId);

        // 计算订单退款明细列表
        $scTotalAmount = array();
        $refundItemList = $this->_calcOrderRefundItemList($itemList, $scPayItemList, $hasRefundItemList, $scTotalAmount);

        if (empty($refundItemList))
        {
            return true;
        }

        $data = array(
            'order_id'          => $orderId,
            'source'            => 'SC',
            'refund_list'       => $refundItemList,
        );

        $service_logic = new Service();

        $result = $service_logic->ServiceCall('payment_account_order_refund', $data);

        if ($result['error_code'] != 'SUCCESS')
        {
            $errMsg = $result['error_msg'] ? $result['error_msg'][0] : '支付账户退款失败';

            \Neigou\Logger::General('settlement.channel.order.refund.failed', array('order_id' => $orderId, 'refund_list' => $itemList, 'action' => 'payment_account_order_refund', 'request' => $data, 'result' => $result));

            return false;
        }

        foreach ($refundItemList as $scId => $itemList)
        {
            /**
             *  $scId           int     通道ID
             *  $type           string  类型
             *  $tradeType      string  交易类型
             *  $tradeBn        string  交易编码
             *  $paymentList    array   支付信息
             *  $accountList    array   账户信息
             *  $itemList       array   明细
             *  $amount         double  金额
             */
            // 保存结算通道支付记录
            if ( ! $this->_addChannelRecord($scId, 'refund', 'ORDER', $orderId, array(), array(), $itemList, $scTotalAmount[$scId]))
            {
                // 记录错误日志
                \Neigou\Logger::General('server_sc_record_failed', array('action' => 'orderRefund', 'order_id' => $orderId, 'item_list' => $itemList));
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 新增渠道记录
     *
     * @param   $scId           int     通道ID
     * @param   $type           string  类型
     * @param   $tradeType      string  交易类型
     * @param   $tradeBn        string  交易编码
     * @param   $paymentList    array   支付信息
     * @param   $accountList    array   账户信息
     * @param   $itemList       array   明细
     * @param   $amount         double  金额
     * @param   $extendData     array   扩展信息
     * @return  mixed
     */
    private function _addChannelRecord($scId, $type, $tradeType, $tradeBn, $paymentList, $accountList, $itemList, $amount, $extendData = array())
    {
        if (empty($scId))
        {
            return false;
        }

        $channelModel = new SettlementChannelModel();

        $data = array(
            'sc_id'         => $scId,
            'type'          => $type,
            'trade_type'    => $tradeType,
            'trade_bn'      => $tradeBn,
            'payment_list'  => is_array($paymentList) ? json_encode($paymentList) : $paymentList,
            'account_list'  => is_array($accountList) ? json_encode($accountList) : $accountList,
            'item_list'     => is_array($itemList) ? json_encode($itemList) : $itemList,
            'amount'        => $amount,
            'extend_data'   => is_array($extendData) ? json_encode($extendData) : $extendData,
        );

        return $channelModel->addChannelRecord($data);
    }

    // --------------------------------------------------------------------

    /**
     * 计算订单item
     *
     * @param   $ruleInfo   array   规则信息
     * @param   $data       array   数据
     *              scale   float   比例
     *              cost_freight    float   邮费
     *              pmt_amount      float   优惠
     * @param   $itemList   array   商品列表
     *              product_bn  string  货品编码
     *              goods_bn    string  商品编码
     *              name        string  名称
     *              price       float   销售价
     *              cost        float   成本价
     *              num         int     购买数量
     *              cost_tax    float   税费
     * @param   $amount     float   总金额
     * @return  mixed
     */
    private function _calcOrderItemWithRule($ruleInfo, & $data, $itemList, $amount)
    {
        // 价格计算规则，默认成本价
        $rule = $ruleInfo ? $ruleInfo['rule_type']. '_'. $ruleInfo['rule_item'] : 'PRICE_PRICE';

        // 商品总金额
        $itemTotalAmount = 0;
        foreach ($itemList as & $v)
        {
            if ($rule == 'PRICE_COST' && $v['cost'] > 0)
            {
                $v['price'] = $v['cost'];
            }

            // 分摊后价格
            $v['price'] = bcmul($v['price'], $data['scale'], 3);

            // 分摊后税
            $v['cost_tax'] = bcmul($v['cost_tax'], $data['scale'], 2);

            // 商品金额
            $v['item_amount']= bcadd(bcmul($v['price'], $v['num'], 2), $v['cost_tax'], 2);

            unset($v['cost']);

            $itemTotalAmount += $v['item_amount'];
        }

        // 商品总金额
        $data['item_amount'] = $itemTotalAmount;
        $costFreight = 0;
        $pmtAmount = 0;

        foreach ($itemList as $k => & $v)
        {
            $scale = bcdiv($v['item_amount'], $itemTotalAmount, 3);

            if ($k + 1 < count($itemList))
            {
                // 邮费
                $v['cost_freight'] = bcmul($data['cost_freight'], $scale, 2);
                // 优惠
                $v['pmt_amount'] = bcmul($data['pmt_amount'], $scale, 2);

                $costFreight = bcadd($costFreight, $v['cost_freight'], 2);
                $pmtAmount = bcadd($pmtAmount, $v['pmt_amount'], 2);
            }
            else
            {
                // 邮费
                $v['cost_freight'] = bcsub($data['cost_freight'], $costFreight, 2);
                // 优惠
                $v['pmt_amount'] = bcsub($data['pmt_amount'], $pmtAmount, 2);

                $totalAmount = bcsub(bcadd($v['item_amount'], $v['cost_freight'], 2), $v['pmt_amount'], 2);
                if ($rule == 'PRICE_PRICE' && $amount > $totalAmount)
                {
                    $v['cost_freight'] = bcadd($v['cost_freight'], bcsub($amount, $totalAmount, 2), 2);
                }

                if ($rule == 'PRICE_COST' && $totalAmount == 0)
                {
                    $v['cost_freight'] = $amount;
                }
            }

            $v['total_amount'] = bcsub(bcadd($v['item_amount'], $v['cost_freight'], 2), $v['pmt_amount'], 2);

            $v['avg_amount'] = bcdiv($v['total_amount'], $v['num'], 2);

            $amount = bcsub($amount, $v['total_amount'], 2);
        }

        return $itemList;
    }

    // --------------------------------------------------------------------

    /**
     * 获取通道的规则列表
     *
     * @param   $scIds      array   通道ID
     * @return  array
     */
    private function _getChannelRuleList($scIds)
    {
        $return = array();

        $channelModel = new SettlementChannelModel();
        $channelRuleModel = new SettlementRuleModel();

        $channelRuleList = $channelModel->getChannelRuleById($scIds);

        if (empty($channelRuleList))
        {
            return $return;
        }

        $ruleList = $channelRuleModel->getRuleList(count($channelRuleList), 0, array('sr_id' => $channelRuleList));

        foreach ($channelRuleList as $scId => $srId)
        {
            if ( ! empty($ruleList[$srId]))
            {
                $return[$scId] = $ruleList[$srId];
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 计算支付账户支付列表
     *
     * @param   $scPayments     array   通道支付列表
     * @param   $orderInfo      array   订单信息
     * @param   $errMsg         string  错误信息
     * @return  mixed
     */
    private function _calcPaymentAccountPayList($scPayments, $orderInfo, & $errMsg = '')
    {
        $return = array();

        $channelModel = new SettlementChannelModel();

        $scIds = array_keys($scPayments);

        // 获取结算通道的结算规则
        $channelRuleList = $this->_getChannelRuleList($scIds);

        // 获取结算的支付账户
        $channelAccount = $channelModel->getChannelAccountById($scIds);

        $i = 0;
        $totalFreight = 0;
        $totalPmtAmount = 0;
        foreach ($scPayments  as $scId => $amount)
        {
            $i++;
            if (empty($channelAccount[$scId]))
            {
                $errMsg = '未匹配到账户';
                return false;
            }

            $scale = bcdiv($amount, $orderInfo['final_amount'], 8);
            $ruleInfo = isset($channelRuleList[$scId]) ? $channelRuleList[$scId] : array();

            if ($i < count($scPayments))
            {
                $costFreight = bcmul($orderInfo['cost_freight'], $scale, 2);
                $pmtAmount = bcmul($orderInfo['pmt_amount'], $scale, 2);
                $totalFreight = bcadd($totalFreight, $costFreight, 2);
                $totalPmtAmount = bcadd($totalPmtAmount, $pmtAmount, 2);
            }
            else
            {
                $costFreight = bcsub($orderInfo['cost_freight'], $totalFreight, 2);
                $pmtAmount = bcsub($orderInfo['pmt_amount'], $totalPmtAmount, 2);
            }

            $data = array(
                'scale' => $scale,
                'cost_freight' => $costFreight, // 邮费
                'pmt_amount' => $pmtAmount, // 优惠
            );
            // 根据规则计算商品分摊的金额
            $itemList = $this->_calcOrderItemWithRule($ruleInfo, $data, $orderInfo['goods_list'], $amount);

            $paymentAccountBns = $channelAccount[$scId]['PAYMENT'];
            $return[$scId] = array(
                'pa_ids' => $paymentAccountBns,
                'goods_list' => $itemList,
                'cost_freight' => $costFreight,
                'pmt_amount' => $pmtAmount,
                'item_amount' => $data['item_amount'],
                'final_amount' => $data['item_amount'] + $costFreight - $pmtAmount,
            );
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 保存订单支付账户结果
     *
     * @param   $paymentAccountPayList      array   账户列表
     * @param   $result                     array   结果
     * @param   $scPaymentItems             array   明细
     * @param   $orderId                    string  订单ID
     * @return  mixed
     */
    private function _saveOrderPaymentAccountResult($paymentAccountPayList, $result, $scPaymentItems, $orderId)
    {
        // 保存支付结果
        $scItemAccountList = array();
        $itemAccountList = $result;
        foreach ($paymentAccountPayList as $scId => & $v)
        {
            $scItemAccountList[$scId]['goods_list'] = array();
            $itemAccount = $itemAccountList[$scId];
            $totalPayAmount = 0;
            foreach ($v['goods_list'] as & $item)
            {
                $bn = $item['product_bn'];
                $item['pa_id'] = $itemAccount[$bn]['pa_id'];
                $item['pay_amount'] = $itemAccount[$bn]['pay_amount'];
                $totalPayAmount = bcadd($totalPayAmount, $item['pay_amount'], 2);
            }

            $itemPaList = $itemAccountList[$scId];
            /**
             *  $scId           int     通道ID
             *  $type           string  类型
             *  $tradeType      string  交易类型
             *  $tradeBn        string  交易编码
             *  $paymentList    array   支付信息
             *  $accountList    array   账户信息
             *  $itemList       array   明细
             *  $amount         double  金额
             */
            // 保存结算通道支付记录
            if ( ! $this->_addChannelRecord($scId, 'pay', 'ORDER', $orderId, $scPaymentItems[$scId], $itemPaList, $v['goods_list'], $totalPayAmount))
            {
                // 记录错误日志
                \Neigou\Logger::General('server_sc_record_failed', array('action' => 'orderPay', 'order_id' => $orderId,
                        'payment_list' => $scPaymentItems[$scId],
                        'account_list' => $itemPaList,
                        'item_list' => $v['goods_list'])
                );
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * 获取已经退款的商品
     *
     * @param   $orderId                    string  订单ID
     * @return  array
     */
    private function _getOrderHasRefundItemList($orderId)
    {
        $return = array();

        $channelModel = new SettlementChannelModel();

        $filter = array(
            'type'          => 'refund',
            'trade_type'    => 'ORDER',
            'trade_bn'      => $orderId,
        );
        $hasRefundItemList = array();

        $hasRefundRecordList = $channelModel->getChannelRecordList($filter, 999);

        if ( ! empty($hasRefundRecordList))
        {
            foreach ($hasRefundRecordList as $recordInfo)
            {
                $list = json_decode($recordInfo['item_list'], true);
                $scId = $recordInfo['sc_id'];
                foreach ($list as $v)
                {
                    if ( ! isset($hasRefundItemList[$scId]) OR ! isset($hasRefundItemList[$scId][$v['pa_id']]) OR ! isset($hasRefundItemList[$scId][$v['pa_id']][$v['product_bn']]))
                    {
                        $hasRefundItemList[$scId][$v['pa_id']][$v['product_bn']] = 0;
                    }
                    $hasRefundItemList[$scId][$v['pa_id']][$v['product_bn']] += $v['num'];
                }
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 计算订单退款明细列表
     *
     * @param   $itemList           array       退款列表
     * @param   $scPayItemList      array       通道支付列表
     * @param   $hasRefundItemList  array       已退明细
     * @param   $scTotalAmount      array       通道金额
     * @return  array
     */
    private function _calcOrderRefundItemList($itemList, $scPayItemList, $hasRefundItemList, & $scTotalAmount = array())
    {
        $return = array();
        foreach ($scPayItemList as $scId => $payPaItemList)
        {
            $totalAmount = 0;
            foreach ($itemList as $item)
            {
                $productBn = $item['product_bn'];
                foreach ($payPaItemList as $paId => $productList)
                {
                    $hasRefundNum = ! empty($hasRefundItemList[$scId][$paId][$productBn]) ? $hasRefundItemList[$scId][$paId][$productBn] : 0;
                    $payItem = $productList[$productBn];
                    if ($hasRefundNum + $item['num'] > $payItem['num'])
                    {
                        continue;
                    }

                    if ($hasRefundNum + $item['num'] == $payItem['num'])
                    {
                        $amount = bcsub($payItem['total_amount'], bcmul($payItem['avg_amount'], $hasRefundNum, 2), 2);
                    }
                    else
                    {
                        $amount = bcmul($payItem['avg_amount'], $item['num'], 2);
                    }

                    $totalAmount = bcadd($totalAmount, $amount, 2);
                    $return[$scId][] = array(
                        'product_bn' => $productBn,
                        'pa_id' => $paId,
                        'num' => $item['num'],
                        'amount' => $amount,
                        'sc_id' => $scId,
                    );
                }
            }
            $scTotalAmount[$scId] = $totalAmount;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 生成编码
     *
     * @return  string
     */
    private static function _createChannelCode()
    {
        return 'SC_'. date('ymdHi'). mt_rand(100, 999);
    }

}
