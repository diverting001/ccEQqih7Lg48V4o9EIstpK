<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 17:40
 */

namespace App\Api\Model\Voucher\Rule;


class CostMallAndCatRuleProcessor extends BasicRuleProcessor
{

    private $_db;

    public function __construct() {
        $this->_db = app('api_db')->connection('neigou_store');
    }

    private function getOrderCost($filter_data) {
        if (isset($filter_data) && isset($filter_data['total_amount'])) {
            return $filter_data['total_amount'];
        }
        return false;
    }

    private function getMallIdbyGoodsId($goodsId){
        if(!$goodsId) return false;
//        $sql = "select * from mall_module_mall_goods where goods_id = $goodsId";
//        $mallGoodsList = $this -> _db -> findAll($sql);
        $mallGoodsList = $this->_db->table('mall_module_mall_goods')->where('goods_id',$goodsId)->get()->all();
        if($mallGoodsList){
            $mallIdList = array();
            foreach ($mallGoodsList as $mallGoodsItem) {
                $mallIdList[] = $mallGoodsItem->mall_id;
            }
            return $mallIdList;
        }

        return false;
    }




    private function getOneLevelMallCatByGoodsId($goodsId){
        if(!$goodsId) return false;
//        $sql = "select * from sdb_b2c_goods where goods_id = {$goodsId}";
//        $result = $this -> _db -> findOne($sql);
        $result = $this->_db->table('sdb_b2c_goods')->where('goods_id',$goodsId)->first();
        if(empty($result->mall_goods_cat)) return false;
        $mallCatId = $result->mall_goods_cat;

//        $sql = "select * from sdb_b2c_mall_goods_cat where cat_id = $mallCatId";
//        $result = $this -> _db -> findOne($sql);
        $result = $this->_db->table('sdb_b2c_mall_goods_cat')->where('cat_id',$mallCatId)->first();
        if(!$result) return false;

        $parent_id = $result->cat_path;
        $parent_id = explode(',',$parent_id);
        $parent_id = array_filter($parent_id);
        $parent_id = array_values($parent_id);

        if(count($parent_id) == 0){
            return $result->cat_id;
        }else{
            return current($parent_id);
        }
    }

    public function match ($rule_condition, $filter_data) {
        $total_amount = $this->getOrderCost($filter_data);
        if($total_amount <= 0)
            return false;

        $goods_list = $filter_data['cart_objects']['object']['goods'];
        if(!isset($rule_condition['mall_id_list']) || !isset($rule_condition['mall_id_list']))
            return false;

        $price = 0;
        $matchProductBnList = array();
        foreach ($goods_list as $k => $goods) {
            $goodsId = $goods['obj_items']['products'][0]['goods_id'];
            $mallIdList = $this->getMallIdbyGoodsId($goodsId);
            $mallCatPathTree = $this->getMallCatPathTreeByGoodsIds(array($goodsId));

            $goods_price = $goods['obj_items']['products'][0]['price']['price'];
            $number = $goods['quantity'];

            //检验该商品能不能用内购券
            $productBn = $goods['obj_items']['products'][0]['bn'];
            if($productBn){
                $retBlack = $this->AuthProductBnBlackGoodsList($rule_condition['ret_black_list'],$productBn);
                if(!$retBlack){
                    continue;
                }
            }

            if(!isset($rule_condition['use_type']) || $rule_condition['use_type'] == 'include'){
                $mallIdMatch = false;
                $mallCatIdMatch = false;
                $mallIdIntersect = array_intersect($rule_condition['mall_id_list'],$mallIdList);
                if(in_array('all',$rule_condition['mall_id_list'])){
                    $mallIdMatch = true;
                } else if (!empty($mallIdIntersect)) {
                    $mallIdMatch = true;
                }

                if(in_array('all',$rule_condition['mall_cat_id_list'])){
                    $mallCatIdMatch = true;
                } else  {
                    foreach ($mallCatPathTree[$goodsId] as $mallCatId) {
                        if (in_array($mallCatId, $rule_condition['mall_cat_id_list'])) {
                            $mallCatIdMatch = true;
                        }
                    }
                }

                if ($mallIdMatch && $mallCatIdMatch) {
                    $price += $goods_price * $number;
                    $matchProductBnList[] = $productBn;
                }
            }else if($rule_condition['use_type'] == 'unless'){
                $mallIdMatch = false;
                $mallCatIdMatch = false;
                $mallIdIntersect = array_intersect($rule_condition['mall_id_list'],$mallIdList);
                if(in_array('all',$rule_condition['mall_id_list'])){
                    $mallIdMatch = true;
                } else if (!empty($mallIdIntersect)) {
                    $mallIdMatch = true;
                }

                if(in_array('all',$rule_condition['mall_cat_id_list'])){
                    $mallCatIdMatch = true;
                } else  {
                    foreach ($mallCatPathTree[$goodsId] as $mallCatId) {
                        if (in_array($mallCatId, $rule_condition['mall_cat_id_list'])) {
                            $mallCatIdMatch = true;
                        }
                    }
                }

                if (!$mallIdMatch || !$mallCatIdMatch) {
                    $price += $goods_price * $number;
                    $matchProductBnList[] = $productBn;
                }
            }else{
                return false;
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

        if( ! isset($ruleCondition['mall_id_list']) OR ! isset($ruleCondition['mall_id_list']))
        {
            return false;
        }

        $goodsList = array();
        foreach ($filterData['products'] as $product)
        {
            $goodsList[] = $product['goods_id'];
        }

        // 获取商城 ID
        $mallList = $this->_getMallIdbyGoodsIds($goodsList);

        // 获取商品一级分类
        $mallCatPathTree = $this->getMallCatPathTreeByGoodsIds($goodsList);

        $price = 0;
        $matchProductBnList = array();
        foreach ($filterData['products'] as $product)
        {
            // 检验该商品能不能用内购券
            if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn']))
            {
                continue;
            }

            if ( ! isset($ruleCondition['use_type']))
            {
                $ruleCondition['use_type'] = 'include';
            }
            if (in_array($ruleCondition['use_type'], array('include', 'unless')))
            {
                $mallIdMatch = false;
                $mallCatIdMatch = false;
                $mallIdIntersect = array_intersect($ruleCondition['mall_id_list'], ! empty($mallList[$product['goods_id']]) ? $mallList[$product['goods_id']] : array());
                if (in_array('all', $ruleCondition['mall_id_list']))
                {
                    $mallIdMatch = true;
                }
                else if ( ! empty($mallIdIntersect))
                {
                    $mallIdMatch = true;
                }

                if (in_array('all', $ruleCondition['mall_cat_id_list']))
                {
                    $mallCatIdMatch = true;
                }
                else if ( ! empty($mallCatPathTree[$product['goods_id']]))
                {
                    foreach ($mallCatPathTree[$product['goods_id']] as $mallCatId) {
                        if (in_array($mallCatId, $ruleCondition['mall_cat_id_list'])) {
                            $mallCatIdMatch = true;
                        }
                    }
                }
                if (($ruleCondition['use_type'] == 'include' && $mallIdMatch && $mallCatIdMatch)
                    OR ($ruleCondition['use_type'] == 'unless' && ( ! $mallIdMatch OR ! $mallCatIdMatch)))
                {
                    $price += $product['price'] * $product['quantity'];
                    $matchProductBnList[] = $product['bn'];
                }
            }
            else
            {
                return false;
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

    // --------------------------------------------------------------------

    /**
     * 通过商品 ID 获取商城 ID
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function _getMallIdByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds))
        {
            return $return;
        }

        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id,mall_id from mall_module_mall_goods where goods_id IN ({$goodsIds})";
        $mallGoodsList = $this->_db->select($sql);

        if ($mallGoodsList)
        {
            foreach ($mallGoodsList as $mallGoodsItem)
            {
                $return[$mallGoodsItem->goods_id][] = $mallGoodsItem->mall_id;
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * 通过商品 ID 获取商城一级分类
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function _getOneLevelMallCatByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds))
        {
            return $return;
        }

        // 获取商城分类 ID
        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id,mall_goods_cat from sdb_b2c_goods where goods_id IN ({$goodsIds})";
        $result = $this->_db->select($sql);
        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            if ( ! empty($v->mall_goods_cat))
            {
                $return[$v->goods_id] = $v->mall_goods_cat;
            }
        }

        if (empty($return))
        {
            return $return;
        }

        // 获取分类的信息
        $mallCatIds = implode(',', $return);
        $sql = "select cat_id,cat_path from sdb_b2c_mall_goods_cat where cat_id IN ({$mallCatIds})";
        $result = $this->_db->select($sql);
        $mallsCatPath = array();
        if ( ! empty($result))
        {
            foreach ($result as $v)
            {
                $mallsCatPath[$v->cat_id] = $v->cat_path;
            }
        }

        // 获取商品的商城一级分类
        foreach ($return as $goodsId => $catId)
        {
            if ( ! isset($mallsCatPath[$catId]))
            {
                unset($return[$goodsId]);
                continue;
            }
            $parentIds = array_values(array_filter(explode(',', $mallsCatPath[$catId])));
            if ( ! empty($parentIds))
            {
                $return[$goodsId] = current($parentIds);
            }
        }

        return $return;
    }

    /**
     * 通过商品 ID 获取商城分类路径树
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function getMallCatPathTreeByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds))
        {
            return $return;
        }

        // 获取商城分类 ID
        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id,mall_goods_cat from sdb_b2c_goods where goods_id IN ({$goodsIds})";
        $result = $this->_db->select($sql);
        if (empty($result))
        {
            return $return;
        }

        foreach ($result as $v)
        {
            if ( ! empty($v->mall_goods_cat))
            {
                $return[$v->goods_id] = $v->mall_goods_cat;
            }
        }

        if (empty($return))
        {
            return $return;
        }

        // 获取分类的信息
        $mallCatIds = implode(',', $return);
        $sql = "select cat_id,cat_path from sdb_b2c_mall_goods_cat where cat_id IN ({$mallCatIds})";
        $result = $this->_db->select($sql);
        $mallsCatPath = array();
        if ( ! empty($result))
        {
            foreach ($result as $v)
            {
                $mallsCatPath[$v->cat_id] = $v->cat_path;
            }
        }

        // 获取商品的商城一级分类
        foreach ($return as $goodsId => $catId)
        {
            if ( ! isset($mallsCatPath[$catId]))
            {
                unset($return[$goodsId]);
                continue;
            }
            $goodsMallCatPathTree = array_values(array_filter(explode(',', $mallsCatPath[$catId])));
            $goodsMallCatPathTree[] = $catId;
            if ( ! empty($goodsMallCatPathTree))
            {
                $return[$goodsId] = $goodsMallCatPathTree;
            }
        }

        return $return;
    }

}