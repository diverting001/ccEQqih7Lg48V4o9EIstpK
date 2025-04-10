<?php
namespace App\Api\V3\Service\Pricing;
/**
 * 商品定价规则
 * @version 0.1
 */
use App\Api\V3\Service\Pricing\Cache as PriceCache;

use App\Console\Model\PricingRule ;
use App\Api\V3\Service\Pricing\Expressv2 ;
use App\Console\Model\PricingRange ;
use App\Console\Model\PricingStage ;

class Rule{
    //商品价格规则映射
    private $_ruleMapping  = null;
    private $_ruleMapping2 = null;
    //规则内容映射
    private $_ruleInfoMapping   = array();
    private $_ruleInfoMapping2  = array();
    //定价使用基准价格类型
    private $_pricingPriceType  = array(
        1   => 'price',
        2   => 'point_price',
        3   => 'mktprice',
        4   => 'cost',
    );


    protected $ruleModel ;
    public function __construct()
    {
        $this->ruleModel = new PricingRule() ;
    }

    public function getRuleMapping($type=1){
        if($type==1){
            return $this->_ruleMapping;
        }else{
            return $this->_ruleMapping2;
        }
    }

    public function setRuleMapping($type, $value){
        if($type==1){
            $this->_ruleMapping = $value;
        }else{
            $this->_ruleMapping2 = $value;
        }
    }

    public function getRuleInfoMapping($type){
        if($type==1){
            return $this->_ruleInfoMapping;
        }else{
            return $this->_ruleInfoMapping2;
        }
    }

    public function getPriceType() {
        return $this->_pricingPriceType ;
    }
    public function setRuleInfoMapping($type, $key, $value){
        if($type==1){
            if(is_null($key)){
                $this->_ruleInfoMapping = $value;
            }else{
                $this->_ruleInfoMapping[$key] = $value;
            }
        }else{
            if(is_null($key)){
                $this->_ruleInfoMapping2 = $value;
            }else{
                $this->_ruleInfoMapping2[$key] = $value;
            }
        }
    }

    /*
     * @todo 计算商品价格
     */
    public function PriceCompute($products, $type=1){
        $products_price = array();
        if(empty($products)) return $products_price;
        //$
        $this->CreateRuleTree($type);
        $self_rule_mapping = $this->getRuleMapping($type);
        $is_set_rule_mapping = !empty($self_rule_mapping);
        foreach ($products as $k=>$product){
            $products_price[$product['product_bn']]['product_bn']    = $product['product_bn'];
            $products_price[$product['product_bn']]['goods_id']    = $product['goods_id'];
            $products_price[$product['product_bn']]['price']    = $product['price'];    //商品原价
            $products_price[$product['product_bn']]['point_price']    = $product['point_price'];    //积点价格
            $products_price[$product['product_bn']]['mktprice']    = $product['mktprice'];    //市场价格
            $products_price[$product['product_bn']]['cost']    = $product['cost'];    // 成本价
            $products_price[$product['product_bn']]['stage_price']  = array();  //场景价
            //有可用规则时计算场景价格
            if($is_set_rule_mapping){
                $stage_rule    = array();
                foreach ($this->getRuleMapping($type) as $key=>$rule){
                    switch ($key){
                        case 'product':
                            if(isset($rule[$product['product_bn']])){
                                //合并商品所用规则
                                $this->ArrayMerge($stage_rule,$rule[$product['product_bn']]);
                            }
                            break;
                        case 'brand' :
                            if(isset($rule[$product['brand_id']])){
                                //合并商品所用规则
                                $this->ArrayMerge($stage_rule,$rule[$product['brand_id']]);
                            }
                            break;
                        case 'supplier' :
                            $supplier_info  = explode('-',$product['product_bn']);
                            if(isset($rule[$supplier_info[0]])){
                                //合并商品所用规则
                                $this->ArrayMerge($stage_rule,$rule[$supplier_info[0]]);
                            }
                            break;
                        case 'mall':
                            if(!empty($product['mall_list'])){
                                foreach ($product['mall_list'] as $mall_id){
                                    if(isset($rule[$mall_id])){
                                        //合并商品所用规则
                                        $this->ArrayMerge($stage_rule,$rule[$mall_id]);
                                    }
                                }
                            }
                            break;
                        case 'container':
                            if(!empty($product['container_list'])){
                                foreach ($product['container_list'] as $container_id){
                                    if(isset($rule[$container_id])){
                                        //合并商品所用规则
                                        $this->ArrayMerge($stage_rule,$rule[$container_id]);
                                    }
                                }
                            }
                            break;
                    }
                }
                //规则价格计算
                $rule_price_list   = $this->PriceStageRuleCompute($product,$stage_rule, $type);
                //合并场景规则
                $stage_rule_list    = $this->MergeStagePrice($stage_rule,$rule_price_list);
                //过滤掉未使用的规则
                $rule_price_list    = $this->UnsetNullRule($rule_price_list,$stage_rule_list);
                $products_price[$product['product_bn']]['stage_price']  = array(
                    'stage_rule_list'   => $stage_rule_list,
                    'rule_price_list'   => $rule_price_list,
                );

                //计算仓价格
                if(!empty($product['branch_list'])){
                    foreach ($product['branch_list'] as $key=>$branch_price){
                        $branch_price['price']['product_bn'] = $product['product_bn'];
                        //规则价格计算
                        $rule_price_list   = $this->PriceStageRuleCompute($branch_price['price'],$stage_rule_list, $type);
                        $products_price[$product['product_bn']]['branch']['price_data'][$key]  = array(
                            'product_bn'    => $product['product_bn'],
                            'price' => $branch_price['price']['price'],
                            'point_price' => $branch_price['price']['mktprice'],
                            'mktprice' => $branch_price['price']['mktprice'],
                            'stage_price'   => array(
                                'stage_rule_list'   => $stage_rule_list,
                                'rule_price_list'   => $rule_price_list,
                            ),
                        );
                        //对应关系
                        foreach ($branch_price['branch_list'] as $branch_id){
                            $products_price[$product['product_bn']]['branch']['cache_key'][$branch_id]  = $key;
                        }
                    }
                }
            }
        }
        //
//        print_r($products_price);exit;
        return $products_price;
    }


    /*
     * @todo 合并场景下规则
     */
    public function MergeStagePrice($stage_rule,$rule_price_list){
        if(empty($stage_rule) || empty($rule_price_list)) return;
        //临时规则价格
        foreach ($stage_rule as $stage_type=>$stage_list){
            foreach ($stage_list as $stage_value=>$rule_list){
                $temp_rule_price_list    = array();
                foreach ($rule_list as $rule_id){
                    $rule_price     = $rule_price_list[$rule_id];
                    $price_type = key($rule_price);
                    $rule_price['rule_id']   = $rule_id;
                    $temp_key   = $price_type.'_'.$rule_price[$price_type]['start_time'].'_'.$rule_price[$price_type]['end_time'];
                    if(isset($temp_rule_price_list[$temp_key])){
                        //使用优先级更高的规则
                        if($rule_price[$price_type]['priority'] > $temp_rule_price_list[$temp_key][$price_type]['priority']){
                            $temp_rule_price_list[$temp_key]    = $rule_price;
                        }else if($rule_price[$price_type]['priority'] == $temp_rule_price_list[$temp_key][$price_type]['priority']){
                            //优先级相同使用更低的价格
                            if($rule_price[$price_type]['price'] < $temp_rule_price_list[$temp_key][$price_type]['price']){
                                $temp_rule_price_list[$temp_key]    = $rule_price;
                            }
                        }
                    }else{
                        $temp_rule_price_list[$temp_key]    = $rule_price;
                    }
                }
                //合并后可有规则
                if(!empty($temp_rule_price_list)){
                    //清空原有规则
                    $stage_rule[$stage_type][$stage_value]  = array();
                    foreach ($temp_rule_price_list as $v){
                        $stage_rule[$stage_type][$stage_value][]    = $v['rule_id'];
                    }
                }
                $temp_rule_price_list    = array();
            }
        }
        return $stage_rule;
    }

    /*
     * @todo 价格规则解析计算
     */
    public function PriceRuleCompute($product,$rule){
        $product_price  = array();
        if(empty($product) || empty($rule)) return $product_price;
        $rule['rule']  = json_decode($rule['rule'],true);
        if(empty($rule['rule'])) return $product_price;
        //需要修改的价格类型
        $price_field  = isset($this->_pricingPriceType[$rule['price_type']])?$this->_pricingPriceType[$rule['price_type']]:'';
        $benchmark_price_field  = isset($this->_pricingPriceType[$rule['benchmark_price_type']])?$this->_pricingPriceType[$rule['benchmark_price_type']]:'';

        $product_price[$price_field]  = array(
            'price' => $product[$price_field],
            'start_time'    => $rule['start_time'],
            'end_time'  => $rule['end_time'],
//            'tag_id'  => $rule['product_tag'],
            'persistence'  => $rule['persistence'], //是否持久化 1:是 2:否
//            'exclusive'  => $rule['exclusive'], //是否排除当前场景 0:不除 1:排除
            'priority'  => $rule['priority'], //规则优先级 （数越大优先级越高）
        );
        if(!empty($price_field)){
            switch ($rule['rule']['type']){
                case 1: //对商品立减,或是涨价
                    if($rule['rule']['data']['type'] == 1){
                        $product_price[$price_field]['price'] = $product[$benchmark_price_field]-$rule['rule']['data']['PriceCompute'] >0?$product[$benchmark_price_field]-$rule['rule']['data']['value']:0;

                    }else if($rule['rule']['data']['type'] == 2){
                        $product_price[$price_field]['price'] = $product[$benchmark_price_field]+$rule['rule']['data']['value'];
                    }
                    break;
                case 2: //对商品定价
                    if(isset($rule['rule']['data'][$product['product_bn']])){
                        $product_price[$price_field]['price'] = $rule['rule']['data'][$product['product_bn']];
                    }
                    break;
                case 3: //对商品打折
                    if(!empty($product[$benchmark_price_field])){
                        $product_price[$price_field]['price'] = $product[$benchmark_price_field]*($rule['rule']['data']['off']/100);
                    }
                    break;
                case 4: //表达式定价
                    if(isset($rule['rule']['data']['express'])){
                        $expressStr = $this->_replacePrice($rule['rule']['data']['express'], $product);
                        if($expressStr !== false){
                            try{
                                //解析判断三元运算符
                                $ectools_express_obj = new Expressv2() ;
                                $lastPrice = $ectools_express_obj->calculate($expressStr);
                                $product_price[$price_field]['price'] = $lastPrice;
                            }catch(Exception $e){}
                        }
                    }
                    break;
            }
            if(isset($product_price[$price_field]['price'])){
                $product_price[$price_field]['price'] = sprintf("%.2f", $product_price[$price_field]['price']);
            }
        }
        return $product_price;
    }

    /**
     * 价格替换
     * @param $evalStr
     * @param $product
     * @return bool
     */
    protected function _replacePrice($expressStr, $product){
        $prices  = array(
            'mktprice',
            'point_price',
            'price',
            'cost',
        );
        foreach ($prices as $field){
            if (isset($product[$field])){
                $expressStr = str_replace($field, $product[$field], $expressStr);
            }else{
                if($field == 'point_price'){
                    continue;
                }
                return false;
            }
        }
        return $expressStr;
    }


    /**
     *  @todo 创建商品规则树
     * @param int $type 类型
     * @param int $force 是否强制更新 1是 0否
     * @return void
     */
    public function CreateRuleTree($type,$force = 0){
        //无数据时重新创建
        $self_rule_mapping = $this->getRuleMapping($type);
        if(is_null($self_rule_mapping)){
            $cache_obj  = new PriceCache();
            //获取缓存
            $cache_obj->setCachePriceType($type);
            $rule_mapping_cache = null;
            if(!$force){
                $rule_mapping_cache = $cache_obj->GetRuleMapping();
            }

            //规则版本
            $version    = $this->GetRuleVersion();
            if(empty($rule_mapping_cache) || $rule_mapping_cache['version'] != $version){
                $this->setRuleMapping($type, array());
                //获取现有规则
                $rule_list  = $this->ruleModel->GetAllRule(1,time(), $type);
                if(!empty($rule_list)){
                    //批量获取范围信息，并进行预处理后，重新赋给对应的rule
                    $range_ids = array_column($rule_list,'range_id','rule_id');
                    $range_data = $this->GetRangeArrayByRangeIds($range_ids);
                    //批量获取场景信息，并进行预处理后，重新赋给对应的rule
                    $stage_ids =  array_column($rule_list,'stage_id','rule_id');
                    $stage_data = $this->GetStageArrayByStageIds($stage_ids);
//                    dump($range_data,$stage_data);die;
                    foreach ($rule_list as $k=>$rule){
                        if(empty($rule['exclusive'])){
                            $this->setRuleInfoMapping($type, $rule['rule_id'], $rule);
//                            $this->SetNodeRuel($rule, $type); //原方法，每触发一次都会查询多次数据库
                            $this->SetNodeRuleV2($rule, $type,$range_data,$stage_data);
                        }
                    }
                }
                //更新设置缓存
                $cach_data  = array(
                    'rule_info_mapping' => $this->getRuleInfoMapping($type),
                    'rule_mapping' => $this->getRuleMapping($type),
                    'rule_list' => $rule_list,
                    'version' => $version,
                );
                $cache_obj->SetRuleMapping($cach_data);
            }else{
                $this->setRuleInfoMapping($type, null, $rule_mapping_cache['rule_info_mapping']);
                $this->setRuleMapping($type, $rule_mapping_cache['rule_mapping']);
            }
        }
    }

    /*
     * @todo 设置结点规则
     */
    private function SetNodeRuel($rule_info, $type){
        $rule_mapping   = array();
        if(empty($rule_info)) return;
        //获取规则使用商品范围
        $product_range  = $this->GetProdcutRange($rule_info['range_id']);
        //获取规则使用场景 (使用场景)
        $stage_list  = $this->GetStage($rule_info['stage_id']);
        if(empty($product_range) || empty($stage_list)) return ;
        foreach ($product_range['data'] as $range){
            foreach ($stage_list['data'] as $stage){
                $rule_mapping[$product_range['type']][$range][$stage_list['type']][$stage][]  = $rule_info['rule_id'];
            }
        }
        //合并规则
        if($type == 1){
            $this->ArrayMerge($this->_ruleMapping,$rule_mapping);
        }else{
            $this->ArrayMerge($this->_ruleMapping2,$rule_mapping);
        }
    }

    /**
     * @todo 设置结点规则-修改原来的每一条单独查询逻辑为批量获取
     * @param array $rule_info 规则列表
     * @param int $type 类型
     * @param array $range_data 范围批量预处理数据
     * @param array $stage_data 场景批量预处理数据
     * @return void
     */
    private function SetNodeRuleV2($rule_info, $type,$range_data,$stage_data){
        $rule_mapping   = array();
        if(empty($rule_info)) return;

        if(!isset($range_data[$rule_info['rule_id']]) || !isset($stage_data[$rule_info['rule_id']])){
            return ;
        }
        $product_range = $range_data[$rule_info['rule_id']];
        $stage_list = $stage_data[$rule_info['rule_id']];

        foreach ($product_range['data'] as $range){
            foreach ($stage_list['data'] as $stage){
                $rule_mapping[$product_range['type']][$range][$stage_list['type']][$stage][]  = $rule_info['rule_id'];
            }
        }
        //合并规则
        if($type == 1){
            $this->ArrayMerge($this->_ruleMapping,$rule_mapping);
        }else{
            $this->ArrayMerge($this->_ruleMapping2,$rule_mapping);
        }
    }

    /*
     * @todo 合并数组
     */
    public function ArrayMerge(&$data,$new_data){
        if(empty($new_data)) return ;
        if(is_array($new_data)){
            foreach ($new_data as $k=>$v){
                if(isset($data[$k])){
                    if(is_array($v)){
                        $this->ArrayMerge($data[$k],$v);
                    }else{
                        if(!in_array($v,$data)){
                            $data[] = $v;
                        }
                    }
                }else{
                    $data[$k]   = $v;
                }
            }
        }
        return ;
    }

    /*
     * @todo 获取商品范围
     */
    protected function GetProdcutRange($range_id){
        $range_data = array();
        if(empty($range_id)) return $range_data;
        $pricing_range_model  =  new PricingRange() ;
        $range_info  = $pricing_range_model->GetRangeInfo($range_id);
        if(empty($range_info)) return $range_data;
        switch ($range_info['type']){
            case '1' :
                break;
            case '2' :
                $range_brand_list   = $pricing_range_model->GetRangeBrand($range_id);
                if(!empty($range_brand_list)){
                    $range_data['type'] = 'brand';
                    foreach ($range_brand_list as $brand){
                        $range_data['data'][] = $brand['brand_id'];
                    }
                }
                break;
            case '4':
                $range_brand_list   = $pricing_range_model->GetRangeProduct($range_id);
                if(!empty($range_brand_list)){
                    $range_data['type'] = 'product';
                    foreach ($range_brand_list as $brand){
                        $range_data['data'][] = $brand['product_bn'];
                    }
                }
                break;
            case '5' :
                $range_supplier_list   = $pricing_range_model->GetRangeSupplier($range_id);
                if(!empty($range_supplier_list)){
                    $range_data['type'] = 'supplier';
                    foreach ($range_supplier_list as $brand){
                        $range_data['data'][] = $brand['supplier_bn'];
                    }
                }
                break;
            case '6' :
                $range_supplier_list   = $pricing_range_model->GetRangeMall($range_id);
                if(!empty($range_supplier_list)){
                    $range_data['type'] = 'mall';
                    foreach ($range_supplier_list as $brand){
                        $range_data['data'][] = $brand['mall_id'];
                    }
                }
                break;
            case '7' :
                $range_container_list   = $pricing_range_model->GetRangeContainer($range_id);
                if(!empty($range_container_list)){
                    $range_data['type'] = 'container';
                    foreach ($range_container_list as $brand){
                        $range_data['data'][] = $brand['container_id'];
                    }
                }
                break;
        }
        return $range_data;
    }

    /*
     * @todo 获取商品使用场景
     */
    protected function GetStage($stage_id){
        $stage_data = array();
        if(empty($stage_id)) return $stage_data;
        $pricing_stage_model = new PricingStage ;

        $stage_info  = $pricing_stage_model->GetStageInfo($stage_id);
        if(empty($stage_info)) return $stage_data;
        switch ($stage_info['type']){
            case '1':
                $stage_data = array(
                    'type'  => 'all',
                    'data'  => array('all')
                );
                break;
            case  '3':
                $stage_channel_list   = $pricing_stage_model->GetStageChannel($stage_id);
                if(!empty($stage_channel_list)){
                    $stage_data['type'] = 'channel';
                    foreach ($stage_channel_list as $brand){
                        $stage_data['data'][] = $brand['channel_type'];
                    }
                }
                break;
            case '4':
                $stage_company_list   = $pricing_stage_model->GetStageCompany($stage_id);
                if(!empty($stage_company_list)){
                    $stage_data['type'] = 'company';
                    foreach ($stage_company_list as $brand){
                        $stage_data['data'][] = $brand['company_id'];
                    }
                }
                break;
            case '5':
                $stage_company_tag_list = $pricing_stage_model->GetStageCompanyTag($stage_id);
                if(!empty($stage_company_tag_list)){
                    $stage_data['type'] = 'company_tag';
                    foreach ($stage_company_tag_list as $company_tag){
                        $stage_data['data'][] = $company_tag['company_tag'];
                    }
                }
                break;
        }
        return $stage_data;
    }

    /**
     * 获取商品使用范围
     * @param array $range_ids
     * @return array
     */
    protected function GetRangeArrayByRangeIds(array $range_ids)
    {
        if(!$range_ids){
            return [];
        }

        //批量查询获取范围数据
        $pricing_range_model = new PricingRange();
        $range_data= $pricing_range_model->GetRangeInfoByRangeIds($range_ids);

        //将rule_id组合进入range数组
        $range_data_array = array_column($range_data,NULL, "range_id");
        foreach ($range_ids as $range_ids_k => $range_ids_v){
            if(isset($range_data_array[$range_ids_v])){
                $range_data_array[$range_ids_v]['rule_id'][] = $range_ids_k;
            }
        }

        //将查询结果按照type进行分类
        $range_type_data = [];
        foreach ($range_data_array as $range_data_array_v){
            $range_type_data[$range_data_array_v['type']][] = $range_data_array_v['range_id'];
        }

        //根据type分组查询不同类型范围的对应数据
        $range_data = [];
        foreach ($range_type_data as $range_type_data_k => $range_type_data_v){
            switch ($range_type_data_k){
                case '1' :
                    break;
                case '2' :
                    $range_brand_list   = $pricing_range_model->GetRangeBrandByRangeIds($range_type_data_v);
                    $type = 'brand';
                    foreach ($range_brand_list as $brand){
                        $tmp = $range_data_array[$brand['range_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $range_data[$tmp_v]['type'] = $type;
                            $range_data[$tmp_v]['data'][] = $brand['brand_id'];
                        }
                    }
                    break;
                case '4':
                    $range_product_list   = $pricing_range_model->GetRangeProductByRangeIds($range_type_data_v);
                    $type = 'product';
                    foreach ($range_product_list as $product){
                        $tmp = $range_data_array[$product['range_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $range_data[$tmp_v]['type'] = $type;
                            $range_data[$tmp_v]['data'][] = $product['product_bn'];
                        }
                    }
                    break;
                case '5' :
                    $range_supplier_list   = $pricing_range_model->GetRangeSupplierByRangeIds($range_type_data_v);
                    $type = 'supplier';
                    foreach ($range_supplier_list as $supplier){
                        $tmp = $range_data_array[$supplier['range_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $range_data[$tmp_v]['type'] = $type;
                            $range_data[$tmp_v]['data'][] = $supplier['supplier_bn'];
                        }
                    }
                    break;
                case '6' :
                    $range_mall_list   = $pricing_range_model->GetRangeMallByRangeIds($range_type_data_v);
                    $type = 'mall';
                    foreach ($range_mall_list as $mall){
                        $tmp = $range_data_array[$mall['range_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $range_data[$tmp_v]['type'] = $type;
                            $range_data[$tmp_v]['data'][] = $mall['mall_id'];
                        }
                    }
                    break;
                case '7' :
                    $range_container_list   = $pricing_range_model->GetRangeContainerByRangeIds($range_type_data_v);
                    $type = 'container';
                    foreach ($range_container_list as $container){
                        $tmp = $range_data_array[$container['range_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $range_data[$tmp_v]['type'] = $type;
                            $range_data[$tmp_v]['data'][] =  $container['container_id'];
                        }
                    }
                    break;
            }
        }
        return $range_data;
    }

    /**
     * 获取商品使用场景
     * @param array $stage_ids
     * @return array
     */
    protected function GetStageArrayByStageIds(array $stage_ids)
    {
        if(!$stage_ids){
            return [];
        }
        $pricing_stage_model = new PricingStage();
        $stage_infos = $pricing_stage_model->GetStageInfoByStageIds($stage_ids);

        //将rule_id组合进入stage数组
        $stage_data_array = array_column($stage_infos,NULL, "stage_id");
        foreach ($stage_ids as $stage_ids_k => $stage_ids_v){
            if(isset($stage_data_array[$stage_ids_v])){
                $stage_data_array[$stage_ids_v]['rule_id'][] = $stage_ids_k;
            }
        }

        ////将查询结果按照type进行分类
        $stage_type_data = [];
        foreach ($stage_infos as $stage_infos_v){
            $stage_type_data[$stage_infos_v['type']][] = $stage_infos_v['stage_id'];
        }

        $stage_data = [];
        foreach ($stage_type_data as $stage_type_data_k => $stage_type_data_v){
            switch ($stage_type_data_k){
                case '1':
                    foreach ($stage_type_data_v as $stage_type_vv){
                        $tmp = $stage_data_array[$stage_type_vv]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $stage_data[$tmp_v]['type'] = 'all';
                            $stage_data[$tmp_v]['data'][] = 'all';
                        }
                    }
                    break;
                case  '3':
                    $stage_channel_list   = $pricing_stage_model->GetStageChannelByStageIds($stage_type_data_v);
                    $type = 'channel';
                    foreach ($stage_channel_list as $brand){
                        $tmp = $stage_data_array[$brand['stage_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $stage_data[$tmp_v]['type'] = $type;
                            $stage_data[$tmp_v]['data'][] =  $brand['channel_type'];
                        }
                    }
                    break;
                case '4':
                    $stage_company_list   = $pricing_stage_model->GetStageCompanyByStageIds($stage_type_data_v);
                    $type= 'company';
                    foreach ($stage_company_list as $brand){
                        $tmp = $stage_data_array[$brand['stage_id']]['rule_id'];
                        foreach ($tmp as $tmp_v){
                            $stage_data[$tmp_v]['type'] = $type;
                            $stage_data[$tmp_v]['data'][] =  $brand['company_id'];
                        }
                    }
                    break;
                case '5':
                    $stage_company_tag_list = $pricing_stage_model->GetStageCompanyTagByStageIds($stage_type_data_v);
                    $type = 'company_tag';
                        foreach ($stage_company_tag_list as $company_tag){
                            $tmp = $stage_data_array[$company_tag['stage_id']]['rule_id'];
                            foreach ($tmp as $tmp_v){
                                $stage_data[$tmp_v]['type'] = $type;
                                $stage_data[$tmp_v]['data'][] =  $company_tag['company_tag'];
                            }
                        }
                    break;
            }
        }
        return $stage_data;

    }

    /*
     * @todo 清除商品价格规则映射
     */
    public function UnsetRuleMapping(){
        $this->setRuleMapping(1, null);
        $this->setRuleInfoMapping(1, null, array());
        $this->setRuleMapping(2, null);
        $this->setRuleInfoMapping(2, null, array());
        //删除规则数缓存
        $cache_obj  = new PriceCache();
        $cache_obj->setCachePriceType(1);
        $cache_obj->DelRuleMapping();
        $cache_obj->setCachePriceType(2);
        $cache_obj->DelRuleMapping();
    }

    /*
     * @todo 获取当前价格规则版本
     */
    public function GetRuleVersion(){
        $rule_version  = $this->ruleModel->GetRuleVersion();
        return $rule_version;
    }


    /*
     * @todo 计算场景下规则价格
     */
    private function PriceStageRuleCompute($product,$stage_rule_list, $type){
        if(empty($stage_rule_list) || empty($product)) return array();
        $stage_price_list    = array();
        foreach ($stage_rule_list as $stage_type=>$stage_value){
            foreach ($stage_value as $rule_list){
                foreach ($rule_list as $rule_id){
                    $ruleInfoMapping = $this->getRuleInfoMapping($type);
                    $stage_price_list[$rule_id] = $this->PriceRuleCompute($product,$ruleInfoMapping[$rule_id]);
                }
            }
        }
        return $stage_price_list;
    }

    /*
     * @todo 过滤掉未使用的规则
     */
    private function UnsetNullRule($rule_price_list,$use_urle){
        if(empty($rule_price_list)) return array();
        $new_rule_price_list    = array();
        foreach ($use_urle as $stage_type=>$stage_list){
            foreach ($stage_list as $rule_list){
                foreach ($rule_list as $urle_id){
                    $new_rule_price_list[$urle_id]  = $rule_price_list[$urle_id];
                }
            }
        }
        return $new_rule_price_list;
    }
}
