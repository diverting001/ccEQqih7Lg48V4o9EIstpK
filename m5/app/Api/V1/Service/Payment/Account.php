<?php
/**
 * neigou_service-stock
 * @package     api
 * @author      xupeng
 * @since       Version
 * @filesource
 */

namespace App\Api\V1\Service\Payment;

use App\Api\Model\Payment\Account as PaymentAccountModel;
use App\Api\Logic\Service as Service;

/**
 * 账户 Service
 *
 * @package     api
 * @category    Service
 * @author      xupeng
 */
class Account
{
    /**
     * 获取账户详情
     *
     * @param   $paId       int      账户ID
     * @return  array
     */
    public function getAccountInfo($paId)
    {
        $accountModel = new PaymentAccountModel();

        // 获取账户信息
        $return = $accountModel->getAccountInfo($paId);

        $return['rule_ids'] = $accountModel->getPaymentAccountRule($paId);

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 创建账户
     *
     * @param   $name           string      名称
     * @param   $ruleIds        array       规则ID
     * @param   $accountCode    string      账户编码
     * @param   $memo           string      备注
     * @return  array
     */
    public function createAccount($name, $ruleIds, $accountCode = '', $memo = '')
    {
        $accountModel = new PaymentAccountModel();

        // 创建账户信息
        $data = array(
            'name'          => $name,
            'status'        => 1,
            'memo'          => $memo ? $memo : '', // 备注
            'update_time'   => time(),
        );

        //开启事务
        app('db')->beginTransaction();

        $id = $accountModel->createAccount($data);

        if ($id <= 0)
        {
            app('db')->rollback();
            return array();
        }

        if ($accountCode)
        {
            if ( ! $accountModel->bindAccount($id, $accountCode))
            {
                app('db')->rollback();
                return array();
            }
        }

        if ($ruleIds)
        {
            foreach ($ruleIds as $ruleId)
            {
                if ( ! $accountModel->bindRule($id, $ruleId))
                {
                    app('db')->rollback();
                    return array();
                }
            }
        }

        // 提交
        app('db')->commit();

        return $this->getAccountInfo($id);
    }

    // --------------------------------------------------------------------

    /**
     * 获取支付账号列表
     *
     * @param   $page       int     页码
     * @param   $pageSize   int     每页数
     * @param   $filter     array   过滤条件
     * @return  array
     */
    public function getPaymentAccountList($page =  1, $pageSize = 20, $filter = array())
    {
        $accountModel = new PaymentAccountModel();

        // 获取总数量
        $count = $accountModel->getPaymentAccountCount($filter);

        $totalPage = ceil($count / $pageSize);

        $return = array(
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_count'   => $count,
            'total_page'    => $totalPage,
            'data'          => array(),
        );

        if ($count == 0)
        {
            return $return;
        }

        $offset = ($page - 1) * $pageSize;

        $data = $accountModel->getPaymentAccountList($pageSize, $offset, $filter);

        if ( ! empty($data))
        {
            $paId = array_keys($data);

            // 获取绑定的账户
            $accountCodes = $accountModel->getPaymentAccountBind($paId);

            // 获取绑定的规则
            $ruleList = $accountModel->getPaymentAccountRule($paId);

            foreach ($data as & $v)
            {
                $v['account_code'] = isset($accountCodes[$v['pa_id']]) ? $accountCodes[$v['pa_id']]['account_code'] : '';
                $v['rule_ids'] = isset($ruleList[$v['pa_id']]) ? $ruleList[$v['pa_id']] : array();
            }

            $return['data'] = $data;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 绑定支付
     *
     * @param   $paId           int     账号ID
     * @param   $accountCode    string  账户编码
     * @return  boolean
     */
    public function bindAccount($paId, $accountCode)
    {
        $accountModel = new PaymentAccountModel();

        return $accountModel->bindAccount($paId, $accountCode);
    }

    // --------------------------------------------------------------------

    /**
     * 订单付款
     *
     * @param   $orderId    string  订单ID
     * @param   $paList     array   付款列表(多个）
     * @param   $source     string  来源
     *              pa_ids          array   账户ID
     *              cost_freight    double  邮费
     *              pmt_amount      double  优惠
     *              item_amount     double  商品总金额
     *              final_amount    double  总金额
     *              goods_list      array   商品明细
     *                  product_bn  	string  货品编码
     *                  goods_bn    	string  商品编码
     *                  name        	string  名称
     *                  price       	float   销售价
     *                  num         	int     购买数量
     *                  cost_tax    	float   税费
     * 				    cost_freight	double	邮费
     *				    item_amount		double	商品含税总金额
     *				    total_amount	double	总金额
     *				    avg_amount 		double	平均金额
     * @return  mixed
     */
    public function orderPay($orderId, $paList, $source, & $errMsg)
    {???????
        $return = array();

        if (empty($paList))
        {
            $errMsg = '结算异常，参数缺失';
            return false;
        }

        $recordList = $this->_getOrderPaymentAccountRecord($orderId, $source);

        if ( ! empty($recordList))
        {
            return $recordList;
        }

        $paIds = array();
        foreach ($paList as $key => $paInfo)
        {
            $paIds = array_merge($paIds, $paInfo['pa_ids']);
        }

        // 获取支付账户信息
        $result = $this->getPaymentAccountList(1, count($paIds), array('pa_id' => $paIds));
        $paymentList = $result['data'];
        if (empty($paymentList))
        {
            $errMsg = '结算异常，获取支付账户信息失败';
            return false;
        }

        // 获取账户列表
        $accountList = $this->_getAccountListByPayment($paymentList);

        // 计算订单账户支付列表
        $paRecordList = array();
        $accountPayList = $this->_calcOrderAccountPayList($paList, $accountList, $paymentList, $return, $paRecordList, $errMsg);

        if (empty($accountPayList))
        {
            $errMsg = $errMsg ?? '结算异常，计算失败';
            return false;
        }
        $serviceLogic = new Service();

        // 账户进行支付
        $data = array();
        foreach ($accountPayList as $v)
        {
            $data[$v['account_code']] = array(
                'account_code' => $v['account_code'],
                'amount' => $v['amount'],
                'trade_type' => 'ORDER',
                'trade_bn' => $orderId,
                'trade_category' => '',
                'memo' => isset($v['memo']) ? $v['memo'] : '',
            );
        }

        $result = $serviceLogic->ServiceCall('account_deduct_batch', $data);

        // 扣款失败
        if ($result['error_code'] != 'SUCCESS')
        {
            $errMsg = '结算异常，批次扣款失败，暂时无法下单，请稍候重试。';
            \Neigou\Logger::General('payment.account.order.pay.failed', array($result['error_msg'] ?? $errMsg, 'order_id' => $orderId, 'pa_list' => $paList, 'action' => 'account_deduct_batch', 'request' => $data, 'result' => $result));
            if ($result['error_msg']) \Neigou\Logger::General('service_order_create', array('error_msg' => '结算异常，批次扣款失败, '.$result['error_msg'], 'order_id' => $orderId));
            return false;
        }
        // 账户记录
        $accountRecordList = $result['data'];

        foreach ($paRecordList as $key => $paList)
        {
            foreach ($paList as $paId => $accountList)
            {
                foreach ($accountList as $accountCode => $info)
                {
                    $data = array(
                        'pa_id'             => $paId,
                        'type'              => 'pay',
                        'trade_type'        => 'ORDER',
                        'trade_bn'          => $orderId,
                        'account_code'      => $accountCode,
                        'amount'            => $info['amount'],
                        'account_record_sn' => $accountRecordList[$accountCode] ?  $accountRecordList[$accountCode]['sn'] : '',
                        'source'            => $source,
                        'source_bn'         => $key,
                        'item_list'         => is_array($info['item_list']) ? json_encode($info['item_list']) : '',
                        'memo'              => isset($v['memo']) ? $v['memo'] : '',
                    );

                    $this->_addPaymentAccountRecord($data);
                }
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 订单退款
     *
     * @param   $orderId        string  订单ID
     * @param   $refundList     array   退款列表(多个）
     * @param   $source         string  来源
     *                  product_bn  	string  货品编码
     *                  pa_id    	    string  账户ID
     *                  num         	int     购买数量
     *				    amount	        double	金额
     *				    sc_id 		    int	    结算通道ID
     * @return  mixed
     */
    public function orderRefund($orderId, $refundList, $source, & $errMsg)
    {
        $return = array();

        if (empty($refundList))
        {
            return false;
        }

        // 获取订单支付列表
        $scPayItemList = $this->_getOrderPaymentList($orderId, $source);

        // 获取订单已退明细列表
        $hasRefundItem = $this->_getOrderHasRefundItemList($orderId, $source);

        $paRefundList = array();
        $accountRefundList = $this->_calcOrderAccountRefundList($refundList, $scPayItemList, $hasRefundItem, $orderId, $paRefundList);

        if (empty($accountRefundList))
        {
            return $return;
        }

        $serviceLogic = new Service();
        $result = $serviceLogic->ServiceCall('account_refund_batch', $accountRefundList);

        // 退款失败
        if ($result['error_code'] != 'SUCCESS')
        {
            $errMsg = $result['error_msg'] ? $result['error_msg'][0] : '账户退款失败';
            \Neigou\Logger::General('payment.account.order.refund.failed', array('order_id' => $orderId, 'refund_list' => $refundList, 'action' => 'account_refund_batch', 'request' => $accountRefundList, 'result' => $result));
            return false;
        }

        // 账户记录
        $accountRecordList = $result['data'];

        foreach ($paRefundList as $paInfo)
        {
            $accountCode = $paInfo['account_code'];
            $data = array(
                'pa_id'             => $paInfo['pa_id'],
                'type'              => 'refund',
                'trade_type'        => 'ORDER',
                'trade_bn'          => $orderId,
                'account_code'      => $accountCode,
                'amount'            => $paInfo['amount'],
                'account_record_sn' => $accountRecordList[$accountCode] ?  $accountRecordList[$accountCode]['sn'] : '',
                'source'            => $source,
                'source_bn'         => $paInfo['source_bn'],
                'item_list'         => json_encode($paInfo['item_list']),
                'memo'              => '',
            );

            $return[] = $data;

            $this->_addPaymentAccountRecord($data);
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 添加支付账户记录
     *
     * @param   $data   array   数据
     * @return  mixed
     */
    private function _addPaymentAccountRecord($data)
    {
        $accountModel = new PaymentAccountModel();

        return $accountModel->addAccountRecord($data);
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单支付账户记录
     *
     * @param   $orderId    string  订单ID
     * @param   $source     string  资源
     * @return  mixed
     */
    private function _getOrderPaymentAccountRecord($orderId, $source)
    {
        $return = array();

        $accountModel = new PaymentAccountModel();

        // 检查支付账户记录
        $filter = array(
            'trade_type'    => 'ORDER',
            'type'          => 'pay',
            'trade_bn'      => $orderId,
            'source'        => $source,
        );
        $recordList = $accountModel->getPaymentAccountRecordList($filter);

        if ( ! empty($recordList))
        {
            foreach ($recordList as $v)
            {
                $itemList = $v['item_list'] ? json_decode($v['item_list'], true) : $v['item_list'];
                if (empty($itemList))
                {
                    continue;
                }
                foreach ($itemList as $item)
                {
                    $return[$v['source_bn']][$item['product_bn']] = array(
                        'product_bn' => $item['product_bn'],
                        'pay_amount' => $item['pay_amount'],
                        'pa_id' => $v['pa_id'],
                        'account_code' => $v['account_code'],
                    );
                }
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取账户列表
     *
     * @param   $paymentList    array       支付账户列表
     * @return  mixed
     */
    private function _getAccountListByPayment($paymentList)
    {
        $return = array();

        $serviceLogic = new Service();

        // 获取账户信息
        $accountCodes = array();
        foreach ($paymentList as $paId => $v)
        {
            if ($v['account_code'])
            {
                $accountCodes[] = $v['account_code'];
            }
        }

        // 获取账户列表
        $data = array(
            'page_size' => count($accountCodes),
            'filter' => ['account_code' => $accountCodes],
        );
        $result = $serviceLogic->ServiceCall('account_get_list', $data);

        if (empty($result['data']))
        {
            return $return;
        }

        foreach ($result['data']['data'] as $v)
        {
            $return[$v['account_code']] = $v;
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 计算订单账户支付列表
     *
     * @param   $paList         array       支付账户列表
     * @param   $accountList    array       账户列表
     * @param   $paymentList    array       账户列表
     * @param   $orderPayReturn array       订单支付返回
     * @param   $paRecordList   array       账户记录列表
     * @param   $errMsg         string      错误信息
     * @return  mixed
     */
    private function _calcOrderAccountPayList($paList, $accountList, $paymentList, & $orderPayReturn, & $paRecordList = array(), & $errMsg = '',$order_id = '')
    {
        $pregProductList = array();
        $accountPayList = array();
        // 获取规则进行匹配
        foreach ($paList as $key => $paInfo)
        {
            $productList = array();
            $productPayList = array();
            foreach ($paInfo['goods_list'] as $goodsInfo)
            {
                $productList[] = array(
                    'product_bn' => $goodsInfo['product_bn'],
                    'goods_bn' => $goodsInfo['goods_bn'],
                );

                $productPayList[$goodsInfo['product_bn']] = $goodsInfo['total_amount'];
                $pregProductList[$goodsInfo['product_bn']] = $goodsInfo['product_bn'];
            }
            $ruleIds = array();
            foreach ($paInfo['pa_ids'] as $paId)
            {
                if (isset($paymentList[$paId]) && $paymentList[$paId]['rule_ids'])
                {
                    $ruleIds = array_merge($ruleIds, $paymentList[$paId]['rule_ids']);
                }
            }

            // 获取规则货品列表
            $ruleGoodsList = $this->_getRuleProductList($ruleIds, $productList);

            $paymentAccountList = array();
            $accountLogs = array();
            foreach ($paInfo['pa_ids'] as $paId)
            {
                if ( ! isset($paymentList[$paId]) OR empty($paymentList[$paId]['rule_ids']) OR empty($paymentList[$paId]['account_code']) OR empty($accountList[$paymentList[$paId]['account_code']]))
                {
                    continue;
                }

                $accountCode = $paymentList[$paId]['account_code'];
                $accountInfo = $accountList[$accountCode];
                $ruleId = current($paymentList[$paId]['rule_ids']);

                $productBnList = $ruleGoodsList[$ruleId] ? $ruleGoodsList[$ruleId] : array();
                foreach ($paymentList[$paId]['rule_ids'] as $ruleId)
                {
                    $productBnList = array_intersect($productBnList, $ruleGoodsList[$ruleId]);
                }

                foreach ($productBnList as $productBn)
                {
                    if ( ! isset($productPayList[$productBn]))
                    {
                        continue;
                    }

                    if (isset($pregProductList[$productBn]))
                    {
                        unset($pregProductList[$productBn]);
                    }

                    $amount = $productPayList[$productBn];
                    if ($accountInfo['enable_balance'] >= $amount)
                    {
                        if ( ! isset($accountPayList[$accountCode]))
                        {
                            $accountPayList[$accountCode] = array(
                                'pa_id' => array(),
                                'account_code' => $accountCode,
                                'amount' => 0,
                                'goods_list' => array(),
                            );
                        }

                        $accountList[$accountCode]['enable_balance'] = bcsub($accountInfo['enable_balance'], $amount, 2);
                        $accountPayList[$accountCode]['pa_id'][$paId] = $paId;
                        $accountPayList[$accountCode]['amount'] = bcadd($accountPayList[$accountCode]['amount'], $amount, 2);
                        $accountPayList[$accountCode]['goods_list'][$productBn] = $productBn;
                        if (empty($paRecordList[$key]) OR empty( $paRecordList[$key][$paId]) OR empty($paRecordList[$key][$paId][$accountCode]))
                        {
                            $paRecordList[$key][$paId][$accountCode] = array('amount' => 0, 'item_list' => array());
                        }
                        $paRecordList[$key][$paId][$accountCode]['amount'] = bcadd($paRecordList[$key][$paId][$accountCode]['amount'], $amount, 2);
                        $paRecordList[$key][$paId][$accountCode]['item_list'][] = array(
                            'product_bn' => $productBn,
                            'pay_amount' => $amount,
                        );

                        $orderPayReturn[$key][$productBn] = array(
                            'product_bn' => $productBn,
                            'pay_amount' => $amount,
                            'pa_id' => $paId,
                            'account_code' => $accountCode,
                        );
                        unset($productPayList[$productBn]);
                    }
                    else
                    {
                        $accountLogs[] = array(
                            'account_code' => $accountCode,
                            'time' => date('Y-m-d H:i:s'),
                            'amount' => $amount,
                            'msg' => '当前bn:' . $productBn . ' 的总价：' . $amount . ' 大于当前账户'.$accountCode.' 的总可用额度：' . $accountInfo['enable_balance'],
                        );
                    }
                }
                $paymentAccountList[$paId] = array(
                    'pa_id' => $paId,
                    'rule_ids' => $paymentList[$paId]['rule_ids'],
                    'account_code' => $paymentList[$paId]['account_code'],
                    'account_info' => $accountInfo,
                    'product_list' => $productBnList,
                );
            }

            // 余额不足
            if ( ! empty($productPayList))
            {
                if (empty($pregProductList))
                {
                    $errMsg = '结算异常，计算失败，暂时无法下单，请稍候重试。';
                }
                if ( ! empty($accountLogs))
                {
                    $log_array = [];
                    $log_msg = '';
                    foreach ($accountLogs as $log) {
                        $log_msg .= '; ' . $log['msg'];
                        $log_array[] = array('order_id' => $order_id, 'account_code' => $log['account_code'], 'time' => $log['time'], 'amount' => $log['amount'], 'msg' => $log['msg'],);
                    }
                    if ($log_array) {
                        \Neigou\Logger::General('account.deduct.failed.balance', $log_array);
                        \Neigou\Logger::General('service_order_create', '结算账户余额不足：' . $log_msg);
                    }
                }

                return false;
            }
        }

        return $accountPayList;
    }


    // --------------------------------------------------------------------

    /**
     * 获取规则货品列表
     *
     * @param   $ruleIds        array       规则ID
     * @param   $productList    array       商品列表
     * @return  array
     */
    private function _getRuleProductList($ruleIds, $productList)
    {
        $return = array();

        $serviceLogic = new Service();

        // 获取商品匹配规则
        $data = array(
            'rule_list' => $ruleIds,
            'filter_data' => ['product' => $productList],
        );
        $result = $serviceLogic->ServiceCall('product_with_rule', $data);

        if ($result['error_code'] != 'SUCCESS')
        {
            return $return;
        }

        foreach ($result['data']['product'] as $ruleId => $data)
        {
            if ( ! empty($data['product_list']) && is_array($data['product_list']))
            {
                foreach ($data['product_list'] as $goods)
                {
                    $return[$ruleId][] = $goods['product_bn'];
                }
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单支付账户列表
     *
     * @param   $orderId    string  订单ID
     * @param   $source     string  资源
     * @return  mixed
     */
    private function _getOrderPaymentList($orderId, $source)
    {
        $return = array();

        $accountModel = new PaymentAccountModel();

        // 获取支付账户记录
        $filter = array(
            'type'          => 'pay',
            'trade_type'    => 'ORDER',
            'trade_bn'      => $orderId,
            'source'        => $source,
        );
        $payRecordList = $accountModel->getPaymentAccountRecordList($filter);

        if (empty($payRecordList))
        {
            return true;
        }

        foreach ($payRecordList as $v)
        {
            $itemList = $v['item_list'] ? json_decode($v['item_list'], true) : $v['item_list'];
            if (empty($itemList))
            {
                continue;
            }
            foreach ($itemList as $item)
            {
                $return[$v['source_bn']][$v['pa_id']][$item['product_bn']] = array(
                    'product_bn' => $item['product_bn'],
                    'pay_amount' => $item['pay_amount'],
                    'pa_id' => $v['pa_id'],
                    'account_code' => $v['account_code'],
                );
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 获取订单已退明细列表
     *
     * @param   $orderId    string  订单ID
     * @param   $source     string  资源
     * @return  mixed
     */
    private function _getOrderHasRefundItemList($orderId, $source)
    {
        $hasRefundItem = array();

        $accountModel = new PaymentAccountModel();

        // 获取账户已退记录
        $filter = array(
            'type'          => 'refund',
            'trade_type'    => 'ORDER',
            'trade_bn'      => $orderId,
            'source'        => $source,
        );
        $refundRecordList = $accountModel->getPaymentAccountRecordList($filter);

        /**
         *   $data[$v['account_code']] = array(
        'account_code' => $v['account_code'],
        'amount' => $v['amount'],
        'trade_type' => 'ORDER',
        'trade_bn' => $orderId,
        'trade_category' => '',
        'memo' => isset($v['memo']) ? $v['memo'] : '',);
         *
         */

        if ( ! empty($refundRecordList))
        {
            foreach ($refundRecordList as $v)
            {
                $itemList = json_decode($v['item_list'], true);
                if (empty($itemList))
                {
                    continue;
                }
                foreach ($itemList as $item)
                {
                    if (empty($hasRefundItem[$v['source_bn']]) OR empty($hasRefundItem[$v['source_bn']][$v['pa_id']]) OR empty($hasRefundItem[$v['source_bn']][$v['pa_id']][$item['product_bn']]))
                    {
                        $hasRefundItem[$v['source_bn']][$v['pa_id']][$item['product_bn']] = 0;
                    }
                    $amount = $hasRefundItem[$v['source_bn']][$v['pa_id']][$item['product_bn']];

                    $hasRefundItem[$v['source_bn']][$v['pa_id']][$item['product_bn']] = bcadd($amount, $item['amount'], 2);
                }
            }
        }

        return $hasRefundItem;
    }

    // --------------------------------------------------------------------

    /**
     * 计算订单账号退款列表
     *
     * @param   $refundList     array       退款列表
     * @param   $scPayItemList  array       订单支付列表
     * @param   $hasRefundItem  array       已退列表
     * @param   $orderId        string      订单编码
     * @param   $paRefundList   array       账户退款列表
     * @return  mixed
     */
    private function _calcOrderAccountRefundList($refundList, $scPayItemList, $hasRefundItem, $orderId, & $paRefundList = array())
    {
        $accountRefundList = array();

        foreach ($refundList as $scId => $itemList)
        {
            foreach ($itemList as $item)
            {
                $paId = $item['pa_id'];
                $productBn = $item['product_bn'];
                $payInfo = $scPayItemList[$scId][$paId][$productBn];
                if (empty($payInfo))
                {
                    continue;
                }
                // 检查剩余金额
                $refundItemAmount = ! empty($hasRefundItem[$scId][$paId][$productBn]) ? $hasRefundItem[$scId][$paId][$productBn] : 0;

                $accountCode = $payInfo['account_code'];

                $return[$scId][$productBn] = array(
                    'product_bn'    => $productBn,
                    'pay_amount'    => $item['amount'],
                    'pa_id'         => $paId,
                    'account_code'  => $accountCode,
                );

                if ($item['amount'] > $payInfo['pay_amount'] - $refundItemAmount)
                {
                    continue;
                }

                if ( ! isset($paRefundList[$paId]))
                {
                    $paRefundList[$paId] = array(
                        'pa_id'         => $paId,
                        'account_code'  => $payInfo['account_code'],
                        'source_bn'     => $scId,
                        'amount'        => 0,
                        'item_list'     => array(),
                    );
                }

                $paRefundList[$paId]['amount'] = bcadd($paRefundList[$paId]['amount'], $item['amount'], 2);
                $paRefundList[$paId]['item_list'][] = array('product_bn' => $productBn, 'amount' => $item['amount']);

                if ( ! isset($accountRefundList[$accountCode]))
                {
                    $accountRefundList[$accountCode] = array(
                        'account_code' => $accountCode,
                        'amount' => 0,
                        'trade_type' => 'ORDER',
                        'trade_bn' => $orderId,
                    );
                }

                $accountRefundList[$accountCode]['amount'] = bcadd($accountRefundList[$accountCode]['amount'], $item['amount'], 2);
            }
        }

        return $accountRefundList;
    }

}
