<?php


namespace App\Api\V3\Controllers\Promotion;


use App\Api\Common\Controllers\BaseController;
use App\Api\Model\Promotion\PromotionModel;
use App\Api\V3\Service\Promotion\RuleMatchService;
use App\Api\V3\Service\Promotion\ScopeService;
use Illuminate\Http\Request;
use Neigou\Logger;

class PromotionController extends BaseController
{
    private $_allow_list;
    private $_rule_list;
    private $_req;
    public function __construct(Request $request)
    {
        $this->_req = $this->getContentArray($request);
        //获取用户作用域内满足条件的运营规则
        $service = new ScopeService();
        $this->_allow_list = $service->GetActivePromotion($this->_req['company_id'],$this->_req['member_id'],$this->_req['channel']);

        //获取规则详情
        $rule_mdl = new PromotionModel();
        $this->_rule_list = $rule_mdl->queryRuleByPromotionId($this->_allow_list['pid']);

    }
//    public function __construct($request)
//    {
//        $this->_req = $request;
//        //获取用户作用域内满足条件的运营规则
//        $service = new ScopeService();
//        $this->_allow_list = $service->GetActivePromotion($this->_req['company_id'],$this->_req['member_id'],$this->_req['channel']);
//
//        //获取规则详情
//        $rule_mdl = new PromotionModel();
//        $this->_rule_list = $rule_mdl->queryRuleByPromotionId($this->_allow_list['pid']);
//
//    }

    public function GetPromotion(){
        $req = $this->_req;
        $rule_service = new RuleMatchService();
        //获取商品列表匹配的 商品规则 信息
        $match_list = $rule_service->ProductsWithRule(['product'=>$req['product']],$this->_allow_list['rule_ids']);

        //通过规则信息获取参加的 运营服务 信息
        $use_product_list = $rule_service->FilterPromotionIdWithGoodsRule($match_list,$this->_allow_list['relation'],$this->_rule_list);

        //规则维度 返回 满足条件的商品列表
        $use_goods_rule_list = $rule_service->FilterMatchProductWithPromotionRule($use_product_list);


        //满足规则的运营促销规则列表
        $allow_rule = $rule_service->GetPromotionRules($use_product_list);
        $rule_list = $rule_service->FilterPromotionRuleByAllowIds($this->_rule_list,$allow_rule);

        //【最后过滤】按商品和订单维度规律规则
        $use_goods_rule_list = $rule_service->FilterOrderRule($use_goods_rule_list,$rule_list);
        $use_product_list = $rule_service->FilterGoodsRule($use_product_list,$rule_list);

        $return['product'] = $use_product_list;
        $return['order'] = $use_goods_rule_list;
        $return['rule_list'] = $rule_list;
        Logger::Debug('promotion.v3',[
            'action'=>'GetPromotion',
            'request_params'=>$req,
            'response_result'=>$return
        ]);
        return $this->outputFormat($return, 0);
    }

    public function CheckPromotion(){
        $req = $this->_req;
        $rule_service = new RuleMatchService();
        //获取商品列表匹配的 商品规则 信息
        $match_list = $rule_service->ProductsWithRule(['product'=>$req['product']],$this->_allow_list['rule_ids']);

        //通过规则信息获取参加的 运营服务 信息
        $use_product_list = $rule_service->FilterPromotionIdWithGoodsRule($match_list,$this->_allow_list['relation'],$this->_rule_list);

        //检查所选择的规则是否可用
        foreach ($use_product_list as $key=>$product){
            if(is_array($product['use_rule'])){
                $valid_res = $rule_service->ValidPromotionRule($product['use_rule'],array_values(array_intersect(array_column($this->_rule_list,'id'),$product['promotion_rule'])));
                if(!empty($valid_res['deny'])){
                    $this->setErrorMsg('选品规则校验不通过 rule_id:'.json_encode($valid_res['deny']).' allow'.json_encode($valid_res['allow']));
                    return $this->outputFormat([], 0);
                }
            }
        }

        //满足规则的运营促销规则列表
        $allow_rule = $rule_service->GetPromotionRules($use_product_list);
        $rule_list = $rule_service->FilterPromotionRuleByAllowIds($this->_rule_list,$allow_rule);

        //规则维度 返回 满足条件的商品列表
        $use_goods_rule_list = $rule_service->FilterMatchProductWithPromotionRule($use_product_list);

        //【最后过滤】按商品和订单维度规律规则
        $use_goods_rule_list = $rule_service->FilterOrderRule($use_goods_rule_list,$rule_list);
        $use_product_list = $rule_service->FilterGoodsRule($use_product_list,$rule_list);

        //规则作用结果
        foreach ($use_product_list as $key=>$product){
            $calc = [];
            //计算单品是否满足运营规则和规则结果
            foreach ($product['promotion_rule'] as $rule_id){
                $calc[$rule_id] = $rule_service->MatchPromotionRule([$product],$req['member_id'],$rule_list[$rule_id],$req['company_id']);
                $calc[$rule_id]['rule_id'] = $rule_id;
            }
            $use_product_list[$key]['calc'] = $calc;

        }


        //计算订单维度的规则计算结果
        foreach ($use_goods_rule_list as $rule_id=>$item){
            //判断是否是订单维度的促销服务
            if($rule_list[$rule_id]->type !='order'){
                unset($use_goods_rule_list[$rule_id]);
            } else {
                $use_goods_rule_list[$rule_id]['calc'] = $rule_service->MatchPromotionRule($item['products'],$req['member_id'],$rule_list[$rule_id],$req['company_id']);
                $use_goods_rule_list[$rule_id]['products'] = collect($use_goods_rule_list[$rule_id]['products'])->map(function ($value,$key){
                    unset($value['calc']);
                    unset($value['use_rule']);
                    unset($value['goods_rule']);
                    unset($value['promotion_rule']);
                    return $value;
                })->toArray();
            }
        }

        //检查所选择的 订单 规则是否可用
        $valid_order_res = $rule_service->ValidPromotionRule($req['order_use_rule'],array_keys($use_goods_rule_list));
        if(!empty($valid_order_res['deny'])){
            $this->setErrorMsg('订单规则校验不通过 rule_id:'.json_encode($valid_order_res['deny']).' allow'.json_encode($valid_order_res['allow']));
            return $this->outputFormat([], 0);
        }

        $return['product'] = $use_product_list;
        $return['order'] = $use_goods_rule_list;
        $return['rule_list'] = $rule_list;
        Logger::Debug('promotion.v3',[
            'action'=>'CheckPromotion',
            'request_params'=>$req,
            'response_result'=>$return
        ]);
        return $this->outputFormat($return, 0);
    }

}
