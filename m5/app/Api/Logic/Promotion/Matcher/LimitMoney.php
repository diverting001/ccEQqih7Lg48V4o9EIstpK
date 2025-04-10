<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 18:16
 */

namespace App\Api\Logic\Promotion\Matcher;


use App\Api\Model\Goods\Promotion;

class LimitMoney extends BaseMatcher implements RuleMatcherInterface
{
    private $_processor_name = 'limit_money';

    private $_model;

    public function __construct()
    {
        $this->_model = new Promotion();

    }

    public function exec($times, $config = array(), $filter_data = array())
    {
        $detailLimit = $this->_verifyLimit($filter_data, $config);

        if ($detailLimit['status'] !== true) {
            return $detailLimit;
        }

        $returnData = array(
            'limit_money' => $config,
            'class' => $this->_processor_name
        );

        return $this->output(true, '满足限购条件', $returnData);
    }


    private function _verifyLimit($filter_data, $config)
    {
        $orderGoodsAmount = 0;

        //  1. 统计当前下单商品金额
        foreach ($filter_data['goods_list'] as $value) {
            $amount = isset($value['amount']) ? $value['amount'] : $value['price'] * $value['nums'];
            $orderGoodsAmount += bcmul($amount, 100);
        }

        //  2. 获取当前已使用总售价， 区分是否每日刷新：基于公司，用户，规则;
        $excludeOrderId = !empty($filter_data['order_id']) ? $filter_data['order_id'] : 0;
        if (isset($config['refresh_time']) && $config['refresh_time'] == 'day') {
            $usedGoodsAmount = $this->_model->getDayUseGoodsAmount($filter_data['member_id'], $filter_data['company_id'], $filter_data['scene_id'], $excludeOrderId, $config['daily_refresh_start_time']);
        } else {
            $usedGoodsAmount = $this->_model->getUseGoodsAmount($filter_data['member_id'], $filter_data['company_id'], $filter_data['scene_id'], $excludeOrderId);
        }

        //  3. 对比是否允许下单
        $totalGoodsAmount = bcadd($usedGoodsAmount,$orderGoodsAmount);
        $limitBuyMoney = bcmul($config['max_amount'],100);  // 转换单位（分）

        if (bccomp($totalGoodsAmount, $limitBuyMoney) == 1) {
            return $this->output(false, $config['tips'], array(
                'max_amount' => bcdiv($limitBuyMoney,100, 2),
                'used_amount' => bcdiv($usedGoodsAmount,100, 2),
                'buy_amount' => bcdiv($orderGoodsAmount, 100, 2),
            ));
        }

        return $this->output(true, '', array());
    }
}
