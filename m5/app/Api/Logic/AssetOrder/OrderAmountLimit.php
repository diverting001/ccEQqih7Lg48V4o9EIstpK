<?php
namespace App\Api\Logic\AssetOrder;
use App\Api\Logic\Service as Service;
use App\Api\Model\Order\ClubCompany;
use App\Api\Model\Order\OrderAmountLimitRecords;
use App\Api\Model\Order\OrderAmountLimitRules;
use App\Api\Model\Order\Order;

#商品限制 运营活动等
class OrderAmountLimit
{

    /**
     * 检查订单金额限制（限制及时生效）
     *
     * @param   $companyId      int         公司 ID
     * @param   $orderId        int         订单 ID
     * @param   $amount         float       金额
     * @param   $limitMessage  string      文案
     * @param  $memberId  int 员工ID
     * @return  boolean
     */
    public function checkAmountLimit($companyId, $orderId, $amount, $goodsList, &$limitMessage = '', $memberId = 0 ,&$e_msg = '')
    {
//        $amount =10;
        if ($companyId <= 0 OR $amount <= 0 OR $orderId <= 0 OR empty($goodsList))
        {
            return true;
        }
        $companyObj = new ClubCompany ;

        $channel = $companyObj->getCompanyRealChannel($companyId);

        $orderAmountLimitModel = new OrderAmountLimitRecords;

        // 检查渠道限制
        $channelLimit = $orderAmountLimitModel->getAmountLimit('channel', $channel);

        // 检查企业限制
        $companyLimit = $orderAmountLimitModel->getAllAmountLimit( 'company', $companyId);

        // 检查员工限制
        $memberLimit = $orderAmountLimitModel->getMemberAmountLimit('company', $companyId);
        if (empty($channelLimit) && empty($companyLimit) && empty($memberLimit))
        {
            return true;
        }
        $daily_start_time = 10;
        if (!empty($channelLimit['daily_start_time']) || !empty($companyLimit['daily_start_time']) || !empty($memberLimit['daily_start_time'])) {
            $daily_start_time = $channelLimit['daily_start_time'];
        }
        $startTime = strtotime(date('Y-m-d', time() - $daily_start_time * 3600));
        if ( ! empty($channelLimit))
        {
            if(!empty($channelLimit['message'])){
                $e_msg = $limitMessage = ' 渠道验证提示信息：' . $channelLimit['message'];
            }
            // 获取当天记录金额
            $sumAmount = $orderAmountLimitModel->getOrderAmountLimitSumAmount(0, $channel, $startTime);


            if (($sumAmount + $amount) > $channelLimit['per_day_limit'])
            {
                $e_msg .= ' 检查下单金额限制，渠道当天已用sumAmount ' . $sumAmount . ' + 本次amount ' . $amount . ' > 渠道限额per_day_limit ' . $channelLimit['per_day_limit'];
                \Neigou\Logger::General('order.create.checkout.amount.limit', array('type' => 'channel', 'limit_info' => $channelLimit, 'amount' => $amount, 'sum_amount' => $sumAmount,'error_msg' => $limitMessage,));
                return false;
            }
        }

        $limitRuleId = 0;

        $companyStartStaticTime = strtotime(date('Y-m-d', time() - 10 * 3600));
        if ( ! empty($companyLimit))
        {
            $matchRuleInfo =  $this->_getCompanyGoodsLimitRule($companyLimit, $goodsList);

            // 优先级: 如果企业限制和员工限制都存在，并且企业的限制金额比员工小则优先
            if (!empty($memberLimit)) {
                if (isset($matchRuleInfo['per_day_limit']) && $matchRuleInfo['per_day_limit'] < $memberLimit['per_day_limit']) {
                    goto COMPANY_LIMIT;
                } else {
                    goto MEMBER_LIMIT;
                }
            }

            COMPANY_LIMIT:
            if ($matchRuleInfo)
            {
                if(!empty($matchRuleInfo['message'])){
                    $e_msg = $limitMessage = ' 企业验证提示信息：' .$matchRuleInfo['message'];
                }

                $limitRuleId = $matchRuleInfo['limit_rule_id'];

                $dailyStartTime = !empty($matchRuleInfo['daily_start_time']) ? $matchRuleInfo['daily_start_time'] : 10;

                $companyStartStaticTime = strtotime(date('Y-m-d', time()-$dailyStartTime*3600));

                // 获取当天记录金额
                $sumAmount = $orderAmountLimitModel->getOrderAmountLimitSumAmount($companyId, '',$companyStartStaticTime,null,1, $limitRuleId);

                if (($sumAmount + $amount) > $matchRuleInfo['per_day_limit'])
                {
                    $e_msg .= ' 检查下单金额限制，公司当天已用sumAmount ' . $sumAmount . ' + 本次amount ' . $amount . ' > 公司限额per_day_limit ' . $matchRuleInfo['per_day_limit'];
                    \Neigou\Logger::General('order.create.checkout.amount.limit', array('type' => 'company', 'limit_info' => $companyLimit, 'amount' => $amount, 'sum_amount' => $sumAmount,'error_msg' => $limitMessage,));
                    return false;
                }
            }
        }

        // 员工限制
        MEMBER_LIMIT:
        if (!empty($memberLimit))
        {
            if (!empty($memberLimit['message'])) {
                $e_msg = $limitMessage = ' 用户验证提示信息：' . $memberLimit['message'];
            }
            $limitOrderLists = $orderAmountLimitModel->getMemberOrderList($companyId, $memberId,$startTime);
            $allowOrderIds = array();
            if ($limitOrderLists) {
                $limitOrderIds = array_column($limitOrderLists, 'order_id');
                $result = Order::GetOrderList('order_id',array(
                    'order_id' => array(
                        'type' => 'in',
                        'value' => $limitOrderIds
                    ),
                    'status' => array(
                        'type' => 'in',
                        'value' => array(1,3)
                    )
                ));
                $allowOrderIds = array_column($result, 'order_id');
            }
            if (count($allowOrderIds) > 0) {
                // 获取当天记录金额
                $sumAmount = $orderAmountLimitModel->getMemberOrderAmountLimitSumAmount($companyId, $memberId, $startTime, $allowOrderIds);
                if (bcadd($sumAmount, $amount, 2) > $memberLimit['per_day_limit'])
                {
                    $e_msg .= ' 检查下单金额限制，用户当天已用sumAmount ' . $sumAmount . ' + 本次amount ' . $amount . ' > 用户限额per_day_limit ' . $memberLimit['per_day_limit'];
                    \Neigou\Logger::General('order.create.checkout.amount.limit', array('type' => 'member', 'limit_info' => $memberLimit, 'amount' => $amount, 'sum_amount' => $sumAmount,'error_msg' => $limitMessage,));
                    return false;
                }
            }
        }

        // 保存记录
        $result = $orderAmountLimitModel->addOrderAmountLimitRecord($orderId, $companyId, $channel, $amount, $memberId, $limitRuleId);
        if ( !$result )
        {
            $limitMessage = '';
            $e_msg .= ' 检查下单金额限制，向 sdb_b2c_order_amount_limit_records 表保存当前订单消费数据失败';
            return false;
        }

        if ( ! empty($channelLimit))
        {
            // 获取当天记录金额
            $sumAmount = $orderAmountLimitModel->getOrderAmountLimitSumAmount(0, $channel, $startTime);

            if ($sumAmount > $channelLimit['per_day_limit'])
            {
                $orderAmountLimitModel->updateAmountLimitStatus($orderId);
                $e_msg .= ' 检查下单金额限制，二次验证，渠道当天已用sumAmount ' . $sumAmount . ' > 渠道限额per_day_limit ' . $channelLimit['per_day_limit'];
                return false;
            }
        }

        if ( ! empty($companyLimit))
        {
            // 获取当天记录金额
            $sumAmount = $orderAmountLimitModel->getOrderAmountLimitSumAmount($companyId, '', $companyStartStaticTime,null,1, $limitRuleId);

            if (isset($matchRuleInfo['per_day_limit']) && $sumAmount > $matchRuleInfo['per_day_limit'])
            {
                $orderAmountLimitModel->updateAmountLimitStatus($orderId);
                $e_msg .= ' 检查下单金额限制，二次验证，公司当天已用sumAmount ' . $sumAmount . ' > 公司限额per_day_limit ' . $matchRuleInfo['per_day_limit'];
                return false;
            }
        }

        return true;
    }

    private function _getCompanyGoodsLimitRule($companyLimit, $goodsList)
    {
        // 1. 根据限制规则获取商品规则并组建关联关系
        $limitRuleIds = array();
        foreach ($companyLimit as $limitInfo) {
            $limitRuleIds[] = $limitInfo['limit_rule_id'];
        }

        $orderAmountLimitRulesModel = new OrderAmountLimitRules();
        $limitRuleRelatedList = $orderAmountLimitRulesModel->getLimitRuleRelatedInfo($limitRuleIds);

        $ruleRelArr = array();
        foreach ($limitRuleRelatedList as $ruleRelInfo) {
            if (empty($ruleRelArr[$ruleRelInfo['goods_rule_id']])) {
                $ruleRelArr[$ruleRelInfo['goods_rule_id']] = array();
            }
            $ruleRelArr[$ruleRelInfo['goods_rule_id']][] = $ruleRelInfo['limit_rule_id'];
        }

        // 2. 根据订单商品bn, 货品bn获取商品可用规则
        $goodsBnList = array();
        foreach ($goodsList as $goodInfo) {
            $goodsBnList[] = array(
                'goods_bn' => $goodInfo['goods_bn'],
                'product_bn' => $goodInfo['bn']
            );
        }

        $usableGoodsRules = $this->_getGoodsUsableRule(array_keys($ruleRelArr), $goodsBnList);
        if (empty($usableGoodsRules)) {
            return false;
        }

        $allowGoodsRuleIds = array_keys($usableGoodsRules);

        // 3. 根据商品可用规则获取允许的限购规则
        $allAllowLimitRule = array();
        foreach ($allowGoodsRuleIds as $goodsRuleId) {
            foreach ($ruleRelArr[$goodsRuleId] as $limitRuleId) {
                $allAllowLimitRule[$limitRuleId] = $limitRuleId;
            }
        }

        // 4. 返回权重最大的规则
        $weightRule = array();
        foreach ($companyLimit as $limitInfo) {
            if (in_array($limitInfo['limit_rule_id'], $allAllowLimitRule)) {
                $weightRule =  array(
                    'limit_rule_id' => $limitInfo['limit_rule_id'],
                    'daily_start_time' => $limitInfo['daily_start_time'],
                    'per_day_limit' => $limitInfo['per_day_limit'],
                    'message' => $limitInfo['message']
                );
                break;
            }
        }

        return $weightRule;
    }

    private function _getGoodsUsableRule($ruleList, $goodsBns)
    {
        $postData = array(
            'rule_list'   => $ruleList,
            'filter_data' => array(
                'product' => $goodsBns
            )
        );

        $service_logic = new Service();
        $ret = $service_logic->ServiceCall('product_with_rule', $postData);

        if ('SUCCESS' == $ret['error_code']) {
            return $ret['data']['product'];
        }

        return array();
    }
}
