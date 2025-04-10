<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 17:48
 */

namespace App\Api\Model\Voucher\Rule;


class CostBrandRuleProcessor extends BasicRuleProcessor
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
        $price = 0;
        $matchProductBnList = array();
        foreach ($goods_list as $k => $goods) {
            $goods_id = $goods['params']['goods_id'];

            //检验该商品能不能用内购券
            $productBn = $goods['obj_items']['products'][0]['bn'];
            if($productBn){
                $retBlack = $this->AuthProductBnBlackGoodsList($rule_condition['ret_black_list'],$productBn);
                if(!$retBlack){
                    continue;
                }
            }

            $brand_id = $this -> getBrandIdByGoodsId($goods_id);
            $goods_price = $goods['obj_items']['products'][0]['price']['price'];
            $number = $goods['quantity'];
            if(in_array($brand_id,$rule_condition['brand'])){
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
        $goodsList = array();
        foreach ($filterData['products'] as $product)
        {
            $goodsList[] = $product['goods_id'];
        }

        // 获取品牌数据
        $goodsBrand = $this->_getBrandIdByGoodsIds($goodsList);

        foreach ($filterData['products'] as $product)
        {
            // 检验该商品能不能用内购券
            if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn']))
            {
                continue;
            }

            // 检查品牌条件
            if (isset($goodsBrand[$product['goods_id']]) && in_array($goodsBrand[$product['goods_id']], $ruleCondition['brand']))
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

    private function getBrandIdByGoodsId($goods_id){
//        $sql = "select * from sdb_b2c_goods where goods_id = $goods_id";
//        $result = $this -> _db -> findOne($sql);
        $result = $this->_db->table('sdb_b2c_goods')->where('goods_id',$goods_id)->first();
        if($result){
            return $result->brand_id;
        }
        return false;
    }


    private function getOrderCost($filter_data) {
        if (isset($filter_data) && isset($filter_data['total_amount'])) {
            return $filter_data['total_amount'];
        }
        return false;
    }

    /**
     * 通过 goods ID 获取品牌 ID
     *
     * @param   $goodsIds   array   规则条件
     * @return  array
     */
    private function _getBrandIdByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds))
        {
            return $return;
        }

        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id, brand_id from sdb_b2c_goods where goods_id IN ({$goodsIds})";
        $result = $this->_db->select($sql);
        if ($result)
        {
            foreach ($result as $v)
            {
                $tmp_gid = $v->goods_id;
                $return[$tmp_gid] = $v->brand_id;
            }
        }

        return $return;
    }

}