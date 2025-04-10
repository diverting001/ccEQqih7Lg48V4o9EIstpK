<?php
/**
 * 店铺ID的匹配规则
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2018/06/26
 * Time: 10:39
 */

namespace App\Api\Model\Voucher\Rule;


class CostShopRuleProcessor extends BasicRuleProcessor
{
    private $_db;

    public function __construct() {
        $this->_db = app('db')->connection('neigou_store');
    }

    /**
     * 按照规则条件计算匹配的商品和价格
     *
     * @param   $ruleCondition array   规则条件
     * @param   $filterData    array   订单数据
     * @return  mixed
     */
    public function matchV6($ruleCondition, $filterData) {
        if (empty($filterData['products'])) {
            return false;
        }
        $price = 0;
        $matchProductBnList = array();
        //组合商品ID
        $gid = array();
        foreach($filterData['products'] as $product){
            $gid[] = $product['goods_id'];
        }
        //获取商品 shop_id
        $tmp_shop = $this->_getShop_id($gid);

        foreach ($filterData['products'] as $product) {
            // 检验该商品能不能用内购券
            if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn'])) {
                continue;
            }
            //检查商品店铺ID是否都满足设置 否则不能使用优惠券
            if(in_array($tmp_shop[$product['goods_id']], $ruleCondition['shop'])) {
                if(isset($product['amount']) && $product['amount']>0){
                    $price += $product['amount'];
                } else {
                    $price += $product['price'] * $product['quantity'];
                }

                $matchProductBnList[] = $product['bn'];
            }
        }

        $ruleCondition['limit_cost'] = isset($ruleCondition['limit_cost']) ? $ruleCondition['limit_cost'] : 0;
        if ( ! empty($matchProductBnList) && ($price > $ruleCondition['limit_cost'] OR abs($price - $ruleCondition['limit_cost']) < 0.0001)) {
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

    /**
     * 获取商品对应的店铺ID
     * @param $gids
     * @return array
     */
    private function _getShop_id($gids){
        $list = $this->_db->table('sdb_b2c_products')->select('goods_id','pop_shop_id')->whereIn('goods_id',$gids)->groupby('goods_id')->get()->all();
        $ret = array();
        if(!empty($list)){
            foreach($list as $val){
                $ret[$val->goods_id] = $val->pop_shop_id;
            }
        }
        return $ret;
    }
}
