<?php

namespace App\Api\V3\Service\Pricing;

/**
 * 商品价格
 * @version 0.1
 * @package ectools.lib.api
 */

use App\Api\V3\Service\Pricing\Cache as PriceCache;
use App\Api\V3\Service\Pricing\Setprice as SetPrice;
use App\Api\V3\Service\Pricing\Rule as PriceRule;
use App\Api\V3\Service\Pricing\ProductPrice as ProductPrice;

class GetPrice
{

    private $price_cache_obj = null;
    private $rule_mapping = array();


    public function __construct()
    {
        $this->price_cache_obj = new PriceCache();
    }

    /*
     * @todo 获取商品价格
     * @stages  = array(
     *  'company'   => 1,
     *  'channel'   => 'louxiaoyi'
     * )
     * )
     */
    public function GetPrice($product_list, $stages = array(), $cache_price_type = 2)
    {
        $null_product_bns = array();
        $products_price   = array();
        if (empty($product_list)) {
            return $products_price;
        }

        $product_bn_list = array();
        foreach ($product_list as $product) {
            $product_bn_list[] = $product['product_bn'];
        }
        //获取商品定价规则缓存
        $products_cahe_price = $this->GetBranchProductPriceCache($product_list, $cache_price_type);
        foreach ($product_bn_list as $product_bn) {
            if (isset($products_cahe_price[$product_bn])) {
                //计算合并商品多场景定价规则
                $products_price[$product_bn] = $this->ProductPriceCompute($products_cahe_price[$product_bn], $stages,
                    $cache_price_type);
            } else {
                $null_product_bns[]          = $product_bn;
                $products_price[$product_bn] = array();
            }
        }
        //获取商品缓存失败，从数据库查询价格
        if (!empty($null_product_bns)) {
            \Neigou\Logger::General('no_products_price_cache', array('bn' => implode(',', $null_product_bns)));
            $product_price_obj     = new ProductPrice();
            $product_price_nocache = $product_price_obj->GetProductList($null_product_bns);
            if (!empty($product_price_nocache)) {
                foreach ($product_price_nocache as $k => $v) {
                    $products_price[$v->product_bn] = array(
                        'price'                 => $v->price,
                        'point_price'           => $v->point_price,
                        'mktprice'              => $v->mktprice,
                        'primitive_price'       => $v->price,
                        'primitive_point_price' => $v->point_price,
                    );
                }
            }
        }
        return $products_price;
    }

    /*
     * @todo 获取商品场景价格
     */
    public function ProductPriceCompute($price, $stages, $cache_price_type = 2)
    {
        $product_price = array();
        if (empty($price)) {
            return $product_price;
        }
        //商品价格
        $product_price = array(
            'price'                 => $price['price'],
            'point_price'           => empty($price['point_price']) ? $price['mktprice'] : $price['point_price'],
            'mktprice'              => $price['mktprice'],
            'primitive_price'       => $price['price'],
            'primitive_point_price' => $price['point_price'],
        );

        //追加场景
        $stages['all'] = 'all';

        //场景过滤
        if (isset($price['stage_price']['stage_rule_list']) && $price['stage_price']['stage_rule_list']) {
            foreach ($price['stage_price']['stage_rule_list'] as $stage_key => $stage_item) {
                if (!$stages[$stage_key]) {
                    unset($price['stage_price']['stage_rule_list'][$stage_key]);
                    continue;
                }
                foreach ($stage_item as $source => $rule_value) {
                    if ($source != $stages[$stage_key]) {
                        unset($price['stage_price']['stage_rule_list'][$stage_key][$source]);
                    }
                }
            }
        }

        //如果有场景价格,计算使用场景价格
        if (!empty($price['stage_price'])) {
            $stage_set = array(
                'point_price' => 0,
                'price'       => 0
            );
            //合并每个场景下的多规则,获取场景最低价
            $stage_all_price = $this->GetProductStagePrice($price['stage_price'], false, $price, $cache_price_type);
//            $stages['all'] = 'all';
            $use_priority = array(
                'point_price' => 0,
                'price'       => 0
            );    //当前使用的优先级
            foreach ($stages as $stage_type => $stage_value) {
                if (is_array($stage_value)) { //包含多个值，再次循环
                    foreach ($stage_value as $v) {
                        $stage_price_info = $stage_all_price[$stage_type][$v];
                        if (!empty($stage_price_info)) {
                            $this->SelectPrice($stage_price_info, $use_priority, $stage_set);
                        }
                    }
                } else {
                    $stage_price_info = $stage_all_price[$stage_type][$stage_value];
                    if (!empty($stage_price_info)) {
                        $this->SelectPrice($stage_price_info, $use_priority, $stage_set);
                    }
                }
            }
            if (!empty($stage_set['point_price'])) {
                $product_price['point_price'] = $stage_set['point_price'];
            }
            if (!empty($stage_set['price'])) {
                $product_price['price'] = $stage_set['price'];
            }
            if (!empty($stage_set['mktprice'])) {
                $product_price['mktprice'] = $stage_set['mktprice'];
            }
        }
        return $product_price;
    }


    /*
     * @todo 获取货品所在场景最低价格
     * @$stage_rule 商品场景价格  $persistence 是否只使用持久化规则
     */
    public function GetProductStagePrice($stage_rule, $persistence = false, $product_info = null, $cache_price_type = 2)
    {
        if (empty($stage_rule['stage_rule_list']) || !in_array($cache_price_type, array(1, 2))) {
            return array();
        }
        $rule_mapping = $this->GetTypeMapping($cache_price_type);
        $rule_lib     = new PriceRule();
        $time         = time();
        $stage_price  = array();
        foreach ($stage_rule['stage_rule_list'] as $key => $value) {
            foreach ($value as $stage_key => $stage_value) {
                $use_priority = array(
                    'point_price' => 0,
                    'price'       => 0
                );    //当前使用的优先级
                foreach ($stage_value as $rule_id) {
                    $rule = $rule_lib->PriceRuleCompute($product_info, $rule_mapping['rule_info_mapping'][$rule_id]);
                    if (empty($rule)) {
                        continue;
                    }
                    $price_type = key($rule);
                    if (is_numeric($rule[$price_type]['price']) && $rule[$price_type]['price'] >= 0.001) {
                        //规则优先级 (数字越大优先级越靠前)
                        $self_priority    = $rule[$price_type]['priority'];
                        $time_is_empty    = empty($rule[$price_type]['start_time']) && empty($rule[$price_type]['end_time']); //时间限制是否为空
                        $use_time_between = $time >= $rule[$price_type]['start_time'] && ($time < $rule[$price_type]['end_time'] || empty($rule[$price_type]['end_time'])); //当前时间是否在限制时间之内
                        //规则不可用
                        if (!($time_is_empty || $use_time_between)) {
                            continue;
                        }
                        $is_persistence = !$persistence ? true : 1 == $rule[$price_type]['persistence'];
                        //当前优先级大于已使用优先级清空原记录
                        if ($self_priority > $use_priority[$price_type]) {
                            $stage_price[$key][$stage_key][$price_type] = null;
                            $use_priority[$price_type]                  = $self_priority;
                        }
                        if (($time_is_empty || $use_time_between) && $is_persistence && $self_priority >= $use_priority[$price_type]) {
                            if (!isset($stage_price[$key][$stage_key][$price_type]) || $stage_price[$key][$stage_key][$price_type]['price'] > $rule[$price_type]['price']) {
                                $stage_price[$key][$stage_key][$price_type] = $rule[$price_type];
                            }
                        }
                    }
                }
            }
        }
        return $stage_price;
    }

    /*
     * @todo 获取商品价格缓存
     */
    public function GetProuctCache($products_bn)
    {
        if (empty($products_bn)) {
            return array();
        }
        $products_cahe_price = $this->price_cache_obj->GetCachePrice($products_bn);
        foreach ($products_bn as $product_bn) {
            if (isset($products_cahe_price[$product_bn])) {
                $products_price[$product_bn] = $products_cahe_price[$product_bn];
            } else {
                $null_product_bns[]          = $product_bn;
                $products_price[$product_bn] = array();
            }
        }
        //无缓存数据,重新生成
        if (!empty($null_product_bns)) {
            $set_price_lib = New SetPrice();
            $set_price_lib->CreateCache($null_product_bns);
            $products_cahe_price = $this->price_cache_obj->GetCachePrice($products_bn);
        }
        return $products_cahe_price;
    }


    /*
     * @todo 获取商品微仓价格缓存
     */
    private function GetBranchProductPriceCache($product_list, $cache_price_type = 2)
    {
        if (empty($product_list)) {
            return array();
        }
        $product_bn_list        = array();
        $branch_product_mapping = array();
        foreach ($product_list as $product) {
            $product_bn_list[]                              = $product['product_bn'];
            $branch_product_mapping[$product['product_bn']] = $product['branch_id'];
        }
        $this->price_cache_obj->setCachePriceType($cache_price_type);
        $products_cahe_price = $this->price_cache_obj->GetCachePrice($product_bn_list);
        //微仓价格缓存
//        $branch_cache_key   = array();
//        foreach ($products_cahe_price as $product_bn=>$stage_price){
//            if(!empty($branch_product_mapping[$product_bn])){
//                if(isset($stage_price['branch_cache'][$branch_product_mapping[$product_bn]])){
//                    $branch_cache_key[]   = $stage_price['branch_cache'][$branch_product_mapping[$product_bn]];
//                }
//            }
//        }
//        if(!empty($branch_cache_key)){
//            $branch_cache_list  = $this->price_cache_obj->MGet($branch_cache_key);
//            if(!empty($branch_cache_list)){
//                //用商品微仓价格覆盖原价格
//                foreach ($branch_cache_list as $branch_cache){
//                    if(isset($products_cahe_price[$branch_cache['product_bn']])){
//                        $products_cahe_price[$branch_cache['product_bn']] = $branch_cache;
//                    }
//                }
//            }
//        }
        return $products_cahe_price;
    }

    /*
     * @todo 选取价格
     */
    public function SelectPrice($stage_price_info, &$use_priority, &$stage_set)
    {
        if (empty($stage_price_info)) {
            return;
        }
        foreach ($stage_price_info as $price_type => $price_info) {
            $self_priority = $price_info['priority'];
            if ($self_priority > $use_priority[$price_type]) {
                $use_priority[$price_type] = $self_priority;
                $stage_set[$price_type]    = 0;
            }
            $use_stage_price = $self_priority >= $use_priority[$price_type];
            if ($use_stage_price) {
                $stage_set[$price_type] = $price_info['price'] < $stage_set[$price_type] || empty($stage_set[$price_type]) ? $price_info['price'] : $stage_set[$price_type];
            }
        }
        return;
    }

    /**
     *
     * 货品价格历史
     *
     *
     * @param $productPriceList
     * @param $stages
     *
     * @return array
     */
    public function getProductPriceHistory($productPriceList, $stages)
    {
        $products_price = array();
        foreach ($productPriceList as $product) {
            $priceData                = $this->ProductPriceCompute($product, $stages, 2);
            $priceData['update_time'] = $product['update_time'];
            $products_price[]         = $priceData;
        }

        return $products_price;
    }


    /*
     * @todo 获取定价规则mapping
     */

    private function GetTypeMapping($type = 2)
    {
        if (empty($this->rule_mapping[$type])) {
            $this->price_cache_obj->setCachePriceType($type);
            $this->rule_mapping[$type] = $this->price_cache_obj->GetRuleMapping();
        }
        return $this->rule_mapping[$type];
    }
}
