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
use App\Api\V3\Service\Pricing\ProductPrice as ProductPrice ;
use App\Api\Common\Common;

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
            $product_price_obj     = new ProductPrice();
            $product_price_nocache = $product_price_obj->GetProductList($null_product_bns);
            if (!empty($product_price_nocache)) {
                foreach ($product_price_nocache as $k => $v) {
                    $products_price[$v->product_bn] = array(
                        'price' => max($v->mktprice,$v->price),
                        'point_price' => $v->point_price,
                        'mktprice' => $v->mktprice,
                        'primitive_price' => $v->price,
                        'primitive_point_price' => $v->point_price,
                    );
                }
            }
            \Neigou\Logger::General('no_products_price_cache', array('bn' => implode(',', $null_product_bns),'data'=>$products_price));
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
        if (empty($price['stage_price'])) {
            return $product_price ;
        }
        $priceCompute = $this->GetProductAllRulePrice($price, $stages, $cache_price_type);
        $priceCompute = $this->mergeStageRule($priceCompute) ;

        foreach ($priceCompute as $rule_id=>$price_info) {
            //  1 => 'price', 2   => 'point_price', 3   => 'mktprice',4   => 'cost',
            // 如果定完价格之后 价格=0 ，则显示销售价
            if($price_info['price_type'] ==1 && $price_info['is_use'] == 1 && bccomp($price_info['data']['price'] ,'0',2) == 1) {
                $product_price['price'] = $price_info['data']['price'] ;
            }
            if($price_info['price_type'] == 3 && $price_info['is_use'] == 1 && bccomp($price_info['data']['price'] ,'0',2) == 1) {
                $product_price['mktprice'] = $price_info['data']['price'] ;
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
    // 根据传入的参数过滤场景
    protected function filterStages($stage_rule_list ,$stages) {
       //追加场景
        $stages['all'] = 'all';
        if(empty($stage_rule_list)) {
            return $stage_rule_list ;
        }
        //场景过滤
        foreach ($stage_rule_list as $stage_key => $stage_item) {
            if (!$stages[$stage_key]) {
                unset($stage_rule_list[$stage_key]);
                continue;
            }
            foreach ($stage_item as $source => $rule_value) {
                if ($source != $stages[$stage_key]) {
                    unset($stage_rule_list[$stage_key][$source]);
                }
            }
        }
        return $stage_rule_list ;
    }

    // 合并多场景下规则
    public function mergeStageRule($stage_all_price) {
        if(empty($stage_all_price)) {
            return [] ;
        }
        $use_priority_type = [] ;
        foreach ($stage_all_price as $rule_id=>$info) {
            $price_type = $info['price_type'] ;
            if(bccomp($info['data']['price'] ,'0' ,3) == 1) {
                $use_priority_type[$price_type][$rule_id] = strval($info['data']['priority']) ;
            }
        }
        foreach ($use_priority_type as $ptype=>$use_priority) {
            arsort($use_priority);
            $high_rule_id = array_keys($use_priority)[0] ;
            $use_money = [] ;
            foreach ($stage_all_price as $rule_id=>$item) {
                if($use_priority[$high_rule_id] == $item['data']['priority'] && $item['price_type'] == $ptype) {
                    $use_money[$rule_id] = $item['data']['price'] ;
                }
            }
            // 优先级相同根据价格判断
            if(count($use_money) > 1) {
                asort($use_money) ;
                $high_rule_id = array_keys($use_money)[0] ;
            }
            $stage_all_price[$high_rule_id]['is_use'] = 1 ;
        }
        return $stage_all_price  ;
    }

    // 根据规则计算出全部的价格
    public function GetProductAllRulePrice($stage_rule ,$stages, $cache_price_type = 2) {
        return $this->GetProductAllRulePriceV2($stage_rule ,$stages, $cache_price_type);

        $rule_mapping = $this->GetTypeMapping($cache_price_type);
        $rule_lib     = new PriceRule();
        $time         = time();
        $product_rule = array() ;
        $stage_rule_list = isset($stage_rule['stage_price']['stage_rule_list']) ? $stage_rule['stage_price']['stage_rule_list']: [] ;
        //场景过滤 过滤掉没有的场景
        $stage_rule_list = $this->filterStages($stage_rule_list ,$stages) ;

        $price_type_dict = array_flip( $rule_lib->getPriceType()) ;
        foreach ($stage_rule_list as $key => $value) {
            foreach ($value as $stage_key => $stage_value) {
                foreach ($stage_value as $rule_id) {
                    $rule = $rule_lib->PriceRuleCompute($stage_rule, $rule_mapping['rule_info_mapping'][$rule_id]);
                    if (empty($rule)) {
                        continue;
                    }
                    $price_type = key($rule);
                    $temp = array(
                        'rule_id' => $rule_id ,
                         // 'stage_info'=> $key . "-" . $stage_key ,
                        'price_type' => $price_type_dict[$price_type] ,
                        'data' => $rule[$price_type] ,
                        'is_use' => 0
                    ) ;
                    $time_is_empty    = empty($rule[$price_type]['start_time']) && empty($rule[$price_type]['end_time']); //时间限制是否为空
                    $use_time_between = $time >= $rule[$price_type]['start_time'] && ($time < $rule[$price_type]['end_time'] || empty($rule[$price_type]['end_time'])); //当前时间是否在限制时间之内
                    //规则不可用
                    if (!($time_is_empty || $use_time_between)) {
                        continue;
                    }
                    $product_rule[$rule_id] = $temp ;
                }
            }
        }
        return $product_rule ;
    }

    /**
     * 根据规则计算出全部的价格-v2
     * 单条获取redis中的定价规则
     * @param array $stage_rule 商品redis中缓存的数据
     * @param array $stages 前端传递的场景信息
     * @param int $cache_price_type 查询的规则类型
     * @return array
     */
    public function GetProductAllRulePriceV2($stage_rule ,$stages, $cache_price_type = 2) {
        //获取全部可用的rule_id
        $tmp = $stage_rule_list = isset($stage_rule['stage_price']['stage_rule_list']) ? $stage_rule['stage_price']['stage_rule_list']: [] ;
        //场景过滤 过滤掉没有的场景
        $stage_rule_list = $this->filterStages($stage_rule_list ,$stages) ;

        //将原本的三层嵌套循环拆解为2个单次循环，并获取最后一层的规则id
        $one_layer = [];
        foreach ($stage_rule_list as $srl_v) {
            $one_layer = array_merge($one_layer,$srl_v);
        }
        $rule_ids = [];
        foreach ($one_layer as $one_layer_v){
            $rule_ids = array_merge($rule_ids,$one_layer_v);
        }
        $rule_ids = array_unique($rule_ids);

        //根据rule_id获取redis中缓存的完整rule规则，这里需要缓存已经查询过的缓存，避免二次获取
        //获取到这些基于rule_id的规则进行金额计算
        $rule_mapping = $this->GetTypeMappingV2($cache_price_type,$rule_ids);
        $rule_lib     = new PriceRule();
        $time         = time();
        $product_rule = array() ;

        $price_type_dict = array_flip( $rule_lib->getPriceType()) ;
        //因为没有用到三层循环的其他元素，只取了最后一层的元素，因此直接使用上面拆解后的循环产生的结果集
//        foreach ($stage_rule_list as $key => $value) {
//            foreach ($value as $stage_key => $stage_value) {
//                foreach ($stage_value as $rule_id) {
        foreach ($rule_ids as $rule_id) {
            $rule = $rule_lib->PriceRuleCompute($stage_rule, $rule_mapping[$rule_id]);
            if (empty($rule)) {
                continue;
            }
            $price_type = key($rule);
            $temp = array(
                'rule_id' => $rule_id,
                // 'stage_info'=> $key . "-" . $stage_key ,
                'price_type' => $price_type_dict[$price_type],
                'data' => $rule[$price_type],
                'is_use' => 0
            );
            $time_is_empty = empty($rule[$price_type]['start_time']) && empty($rule[$price_type]['end_time']); //时间限制是否为空
            $use_time_between = $time >= $rule[$price_type]['start_time'] && ($time < $rule[$price_type]['end_time'] || empty($rule[$price_type]['end_time'])); //当前时间是否在限制时间之内
            //规则不可用
            if (!($time_is_empty || $use_time_between)) {
                continue;
            }
            $product_rule[$rule_id] = $temp;
        }
//                }
//            }
//        }

        return $product_rule ;
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
    public function GetBranchProductPriceCache($product_list, $cache_price_type = 2)
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

    private function GetTypeMapping($type = 2,$rule_ids = [])
    {
        if($rule_ids){
            return $this->GetTypeMappingV2($type,$rule_ids);
        }else{
            //兼容老逻辑
            if (empty($this->rule_mapping[$type])) {
                $this->price_cache_obj->setCachePriceType($type);
                $this->rule_mapping[$type] = $this->price_cache_obj->GetRuleMapping();
            }
            return $this->rule_mapping[$type];
        }
    }

    /**
     * @todo 获取定价规则mapping
     * 按单条查询，并在本次生命周期保存已经请求过的内容
     */
    private function GetTypeMappingV2($type = 2,$rule_ids = [])
    {
        $wait_query_id = [];
        foreach ($rule_ids as $rule_ids_v){
            if (empty($this->rule_mapping[$type][$rule_ids_v])) {
                $wait_query_id[] = $rule_ids_v;
            }
        }
        if ($wait_query_id) {
            $wait_query_id = array_unique($wait_query_id);
            $this->price_cache_obj->setCachePriceType($type);
            $res = $this->price_cache_obj->GetRuleListByRuleIds($wait_query_id);
            foreach ($res as $res_v){
                $this->rule_mapping[$type][$res_v['rule_id']] = $res_v;
            }
        }
        return $this->rule_mapping[$type] ?? [];
    }
}
