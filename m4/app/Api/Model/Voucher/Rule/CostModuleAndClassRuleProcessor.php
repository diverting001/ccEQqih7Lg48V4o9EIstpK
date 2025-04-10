<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 16:06
 */

namespace App\Api\Model\Voucher\Rule;


class CostModuleAndClassRuleProcessor extends BasicRuleProcessor
{
    private $_db;

    public function __construct() {
        $this->_db = app('db')->connection('neigou_store');
    }

    private function getOrderCost($filter_data) {
        if (isset($filter_data) && isset($filter_data['total_amount'])) {
            return $filter_data['total_amount'];
        }
        return false;
    }

    private function getModuleIdByCatId($cat_id = 0){
//        $sql = "select * from sdb_b2c_goods_cat where cat_id = $cat_id";
//        $result = $this -> _db -> findOne($sql);
        $result = $this->_db->table('sdb_b2c_goods_cat')->where('cat_id',$cat_id)->first();
        if($result){
            $parent_id = $result->cat_path;
            if($parent_id == ','){
                $parent_id = $result->cat_id;
            }else{
                $parent_id = explode(',', $parent_id);
                $parent_id = array_filter($parent_id);
                $parent_id = current($parent_id);
            }
//            $sql = "select * from sdb_b2c_module_cat where root_cat_id = $parent_id";
//            $result = $this -> _db -> findOne($sql);
            $result = $this->_db->table('sdb_b2c_module_cat')->where('root_cat_id',$parent_id)->first();
            if($result){
                return $result->module_id;
            }
            return false;
        }

        return false;
    }

    private function getOneLevelCatByCatId($cat_id){
        $module_id = $this -> getModuleIdByCatId($cat_id);
        if(!$module_id) return false;

//        $sql = "select * from sdb_b2c_goods_cat where cat_id = $cat_id";
//        $result = $this -> _db -> findOne($sql);
        $result = $this->_db->table('sdb_b2c_goods_cat')->where('cat_id',$cat_id)->first();
        if(!$result) return false;

        $parent_id = $result->cat_path;
        $parent_id = explode(',',$parent_id);
        $parent_id = array_filter($parent_id);
        $parent_id = array_values($parent_id);

        if($module_id == 1){
            if(count($parent_id) == 0){
                return $result['cat_id'];
            }else{
                return current($parent_id);
            }
        }else{
            if(count($parent_id) == 1){
                return $result['cat_id'];
            }else{
                return $parent_id[1];
            }
        }
    }

    public function match ($rule_condition, $filter_data) {
//        print_r($filter_data);die;
        $total_amount = $this->getOrderCost($filter_data);
        if($total_amount <= 0)
            return false;

        $goods_list = $filter_data['cart_objects']['object']['goods'];
        // if(isset($rule_condition['limit_cost'])){
        //     if($rule_condition['limit_cost'] > $total_amount){
        //         return false;
        //     }
        // }
        if(!isset($rule_condition['module_class']))
            return false;

        $price = 0;
        $matchProductBnList = array();
        $cat_id_reflect_cache = array();
        foreach ($goods_list as $k => $goods) {

            //检验该商品能不能用内购券
            $productBn = $goods['obj_items']['products'][0]['bn'];
            if($productBn){
                $retBlack = $this->AuthProductBnBlackGoodsList($rule_condition['ret_black_list'],$productBn);
                if(!$retBlack){
                    continue;
                }
            }

            $cat_id = $goods['obj_items']['products'][0]['cat_id'];
            if (empty($cat_id_reflect_cache[$cat_id])) {
                // if(!$cat_id) return false;
                $module_id = $this -> getModuleIdByCatId($cat_id);
                // if(!$module_id) return false;
                $root_cat_id = $this -> getOneLevelCatByCatId($cat_id);
                $cat_module = array(
                    "module_id" =>$module_id,
                    "root_cat_id" =>$root_cat_id,
                );
                $cat_id_reflect_cache[$cat_id] = $cat_module;
            } else {
                $module_id = $cat_id_reflect_cache[$cat_id]['module_id'];
                $root_cat_id = $cat_id_reflect_cache[$cat_id]['root_cat_id'];
            }
            // if(!$root_cat_id) return false;
            $goods_price = $goods['obj_items']['products'][0]['price']['price'];
            $number = $goods['quantity'];
            foreach ($rule_condition['module_class'] as $module_ids => $class_id) {
                if(!isset($rule_condition['use_type']) || $rule_condition['use_type'] == 'include'){
                    if($module_ids == $module_id && in_array('all',$class_id)){
                        $price += $goods_price * $number;
                        $matchProductBnList[] = $productBn;
                    }
                    if($module_ids == $module_id && in_array($root_cat_id,$class_id)){
                        $price += $goods_price * $number;
                        $matchProductBnList[] = $productBn;
                    }
                }else if($rule_condition['use_type'] == 'unless'){
                    // 排除不需要考虑模块
                    if(!in_array($root_cat_id,$class_id)){
                        $price += $goods_price * $number;
                        $matchProductBnList[] = $productBn;
                    }
                }else{
                    return false;
                }
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
        if (empty($filterData['products']) OR empty($ruleCondition['module_class']))
        {
            return false;
        }

        $goodsList = array();
        foreach ($filterData['products'] as $product)
        {
            $goodsList[] = $product['goods_id'];
        }

        // 获取商品的分类模块信息
        $goodsCatModuleList = $this->_getCatModuleIdByGoodsIds($goodsList);

        $price = 0;
        $matchProductBnList = array();
        foreach ($filterData['products'] as $product)
        {
            // 检验该商品能不能用内购券
            if ( ! $this->AuthProductBnBlackGoodsList($ruleCondition['ret_black_list'], $product['bn']))
            {
                continue;
            }

            if ( ! isset($goodsCatModuleList[$product['goods_id']]))
            {
                continue;
            }

            $goodsCatModule = $goodsCatModuleList[$product['goods_id']];

            foreach ($ruleCondition['module_class'] as $moduleId => $classId)
            {
                if ( ! isset($ruleCondition['use_type']) OR $ruleCondition['use_type'] == 'include')
                {
                    if ($moduleId == $goodsCatModule['module_id'] && (in_array('all', $classId) OR in_array($goodsCatModule['root_cat_id'], $classId)))
                    {
                        $price += $product['price'] * $product['quantity'];
                        $matchProductBnList[] = $product['bn'];
                    }
                }
                else if ($ruleCondition['use_type'] == 'unless')
                {
                    // 排除不需要考虑模块
                    if ( ! in_array($goodsCatModule['root_cat_id'], $classId))
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
                    "limit_cost" => $ruleCondition['limit_cost']
                );
            } else {
                return false;
            }
        }

    }

    // --------------------------------------------------------------------

    /**
     * 通过商品 ID 获取商品的分类模块信息
     *
     * @param   $goodsIds   array   商品 ID
     * @return  array
     */
    private function _getCatModuleIdByGoodsIds($goodsIds)
    {
        $return = array();

        if (empty($goodsIds))
        {
            return $return;
        }

        // 查询商品 cat_id
        $goodsIds = implode(',', array_filter($goodsIds));
        $sql = "select goods_id,cat_id from sdb_b2c_goods where goods_id IN ({$goodsIds})";
        $result = $this->_db->select($sql);

        if (empty($result))
        {
            return $return;
        }

        $goodsCats = array();
        foreach ($result as $v)
        {
            $goodsCats[$v->goods_id] = $v->cat_id;
        }

        // 查询分类的 path

        $catIds = implode(',', $goodsCats);
        $sql = "select cat_id,cat_path from sdb_b2c_goods_cat where cat_id IN ({$catIds})";
        $result = $this->_db->select($sql);

        if (empty($result))
        {
            return $return;
        }

        $catsRoot = array();
        $catsPath = array();
        foreach ($result as $v)
        {
            $catsRoot[$v->cat_id] = $v->cat_path == ',' ? $v->cat_id : current(array_filter(explode(',', $v->cat_path)));
            $catsPath[$v->cat_id] = $v->cat_path;
        }

        // 查询模块 ID
        $rootCatIds = implode(',', $catsRoot);
        $sql = "select root_cat_id,module_id from sdb_b2c_module_cat where root_cat_id IN ({$rootCatIds})";
        $result = $this->_db->select($sql);
        if (empty($result))
        {
            return $return;
        }

        $catsModule = array();
        foreach ($result as $v)
        {
            $catsModule[$v->root_cat_id] = $v->module_id;
        }

        // 获取商品的模块 ID
        foreach ($goodsCats as $goodsId => $catId)
        {
            if (isset($catsRoot[$catId]) && isset($catsModule[$catsRoot[$catId]]))
            {
                $moduleId = $catsModule[$catsRoot[$catId]];
                $parentIds = array_values(array_filter(explode(',', $catsPath[$catId])));
                $return[$goodsId] = array(
                    'module_id'     =>  $moduleId,
                    'root_cat_id'   =>  $moduleId == 1 ? (count($parentIds) == 0 ? $catId : current($parentIds)) : (count($parentIds) == 1 ? $catId : $parentIds[1]),
                );
            }
        }

        return $return;
    }

}