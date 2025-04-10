<?php


namespace App\Api\V3\Service\Promotion;


use App\Api\Logic\Service;
use App\Api\Model\Promotion\Match\ProductsMatch;
use Neigou\Logger;

class RuleMatchService
{
    //获取商品列表对应的规则信息
    public function ProductsWithRule($filter_data,$allow_ids){
        $param['rule_list'] = $allow_ids;
        $param['filter_data'] = $filter_data;
        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall('product_with_rule', $param);
        if ('SUCCESS' == $res['error_code']) {
            $rule = [];
            $returnData = [];
            foreach ($res['data']['product'] as $ruleId => $data) {
                if ( ! empty($data['product_list']) && is_array($data['product_list'])) {
                    foreach ($data['product_list'] as $goods) {
                        $rule[$goods['product_bn']][] = $ruleId;
                    }
                }
            }
            foreach ($filter_data['product'] as $key=>$value){
                $returnData[$value['product_bn']] = $value;
                $returnData[$value['product_bn']]['goods_rule'] = $rule[$value['product_bn']]??[];
            }
            return $returnData;
        } else {
            Logger::Debug('promotion.v3',[
                'action'=>'ProductWithRule',
                'remark'=>'规则验证失败',
                'request_params'=>$param,
            ]);
            return [];
        }
    }

    //匹配规则对应的商品
    public function FilterMatchProductWithPromotionRule($result){
        if(empty($result)) return [];
        $rules = [];
        foreach ($result as $product => $value){
            if(is_array($value['promotion_rule'])){
                foreach ($value['promotion_rule'] as $rule_id){
                    unset($value['goods_rule']);
                    unset($value['promotion_rule']);
                    $rules[$rule_id]['products'][] = $value;
                }
            }
        }
        return $rules;
    }

    //根据goods_rule_id 获取对应的promotion_id
    public function FilterPromotionIdWithGoodsRule($result,$relation,$rule_list){
        foreach ($result as $key=>$product){
            $match_promotion_rule = [];
            foreach ($product['goods_rule'] as $rule_id){
                $match_promotion_rule = array_merge($match_promotion_rule, $relation[$rule_id]);
            }
            $result[$key]['promotion_rule'] = array_values(array_intersect(array_column($rule_list,'id'),array_unique($match_promotion_rule)));
            unset($result[$key]['goods_rule']);
        }
        return $result;
    }

    //获取商品列表可用规则
    public function GetPromotionRules($result){
        $list = array_column($result,'promotion_rule');
        $return = [];
        foreach ($list as $value){
            if(!empty($value)){
                $return = array_merge($return,$value);
            }
        }
        return array_unique($return);
    }

    //校验 选定的规则是否可用
    public function ValidPromotionRule($use_rule,$allow_rule){
        $deny = [];
        if(is_array($use_rule)){
            foreach ($use_rule as $rule_id){
                if(!in_array($rule_id,$allow_rule)){
                    $deny[] = $rule_id;
                }
            }
        }
        $return['allow'] = $allow_rule;
        $return['deny'] = $deny;
        return $return;
    }

    //过滤不生效的规则
    public function FilterPromotionRuleByAllowIds($rule_list = [],$allow_ids){
        $return = [];
        foreach ($rule_list as $key=>$value){
            if(in_array($value->id,$allow_ids)){
                $return[$value->id] = $value;
            }
        }
        return $return;
    }

    //匹配规则
    public function MatchPromotionRule($products,$member_id,$rule_info,$company_id = 0){
        $matcher = new ProductsMatch();
        $condition = $rule_info->condition;
        $filter['member_id'] = $member_id;
        $filter['company_id'] = $company_id;
        $filter['goods_list'] = $products;
        $filter['scene_id'] = $rule_info->id;
        return $matcher->MatchRule(json_decode($condition,true),$filter);
    }

    //过滤商品规则
    public function FilterGoodsRule($use_product_list,$rule_list){
        foreach ($use_product_list as $product_bn=>$item){
            foreach ($item['promotion_rule'] as $pKey=>$pValue){
                //判断是否是商品维度的促销服务
                if($rule_list[$pValue]->type !='goods'){
                    unset($item['promotion_rule'][$pKey]);
                }
            }
            $use_product_list[$product_bn]['promotion_rule'] = array_values($item['promotion_rule']);
        }
        return $use_product_list;
    }

    //过滤订单规则
    public function FilterOrderRule($use_goods_rule_list,$rule_list){
        foreach ($use_goods_rule_list as $rule_id=>$item){
            //判断是否是订单维度的促销服务
            if($rule_list[$rule_id]->type !='order'){
                unset($use_goods_rule_list[$rule_id]);
            }
        }
        return $use_goods_rule_list;
    }

}
