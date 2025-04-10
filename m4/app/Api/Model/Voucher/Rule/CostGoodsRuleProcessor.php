<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 17:49
 */

namespace App\Api\Model\Voucher\Rule;


class CostGoodsRuleProcessor extends BasicRuleProcessor
{
    private $_db;

    public function __construct() {
        $this->_db = app('db')->connection('neigou_store');
    }

    public function match ($rule_condition, $filter_data) {
        $total_amount = $this->getOrderCost($filter_data);
        if($total_amount <= 0)
            return false;

        $goods_list = $filter_data['cart_objects']['object']['goods'];
        // if(isset($rule_condition['limit_cost'])){
        //     if($rule_condition['limit_cost'] > $total_amount){
        //         return false;
        //     }
        // }
        $price = 0;
        $matchProductBnList = array();
        foreach ($goods_list as $k => $goods) {

            //检验该商品能不能用内购券
            $productBn = $goods['obj_items']['products'][0]['bn'];
            if($productBn){
                $retBlack = $this->AuthProductBnBlackGoodsList($rule_condition['ret_black_list'],$productBn);
                if(!$retBlack){
                    continue;
                }
            }

            $goods_id = $goods['params']['goods_id'];
            $goods_price = $goods['obj_items']['products'][0]['price']['price'];
            $number = $goods['quantity'];
            if(in_array($goods_id,$rule_condition['goods'])){
                $price += $goods_price * $number;
                $matchProductBnList[] = $productBn;
            }
        }

        $rule_condition['limit_cost'] = isset($rule_condition['limit_cost'])?$rule_condition['limit_cost']:0;
        if(!empty($matchProductBnList) && ($price > $rule_condition['limit_cost'] ||  abs($price - $rule_condition['limit_cost']) < 0.0001)){
            return array(
                "product_bn_list"=>  $matchProductBnList,
                "match_use_money"=>$price
            );
        }

        return false;
    }

    // --------------------------------------------------------------------

    /**
     * 按照规则条件计算匹配的商品和价格
     *
     * @param   $ruleCondition array   规则条件
     * @param   $filterData    array   订单数据
     * @return  mixed
     */
    public function matchV6($ruleCondition, $filterData)
    {
        if (empty($filterData['products']))
        {
            return false;
        }

        $price = 0;
        $matchProductBnList = array();
        foreach ($filterData['products'] as $product)
        {
            // 检验该商品能不能用内购券
            if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn']))
            {
                continue;
            }

            if(in_array($product['goods_id'], $ruleCondition['goods']))
            {
                $price += $product['price'] * $product['quantity'];
                $matchProductBnList[] = $product['bn'];
            }
        }

        $ruleCondition['limit_cost'] = isset($ruleCondition['limit_cost']) ? $ruleCondition['limit_cost'] : 0;
        if ( ! empty($matchProductBnList) && ($price > $ruleCondition['limit_cost'] OR abs($price - $ruleCondition['limit_cost']) < 0.0001))
        {
            return array(
                "product_bn_list" => $matchProductBnList,
                "match_use_money" => $price,
                "limit_cost" => $ruleCondition['limit_cost']
            );
        } else {
            //新版购物车 如果匹配的商品存在 显示商品匹配列表和匹配金额
            if($filterData['newcart']==1 && !empty($matchProductBnList)){
                return array(
                    "product_bn_list" => $matchProductBnList,
                    "match_use_money" => $price,
                    "need_money" => $ruleCondition['limit_cost']-$price,
                    "limit_cost" => $ruleCondition['limit_cost']
                );
            } else {
                return false;
            }
        }
    }

    private function getOrderCost($filter_data) {
        if (isset($filter_data) && isset($filter_data['total_amount'])) {
            return $filter_data['total_amount'];
        }
        return false;
    }


}