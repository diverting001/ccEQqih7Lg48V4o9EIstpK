<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-02-25
 * Time: 11:14
 */

namespace App\Api\V2\Controllers\Promotion;


use App\Api\Common\Controllers\BaseController;
use App\Api\Logic\Service;
use App\Api\Model\Promotion\Match\ProductsMatch;
use App\Api\Model\Promotion\PromotionModel;
use Illuminate\Http\Request;
use Neigou\Logger;

class PromotionController extends BaseController
{


    /**
     * 选择一个排序最高的限购规则
     * @param $rule_list
     * @return mixed
     */
    private function _filter_limit_buy_rule($rule_list){
        $limit_rule_id = false;
        foreach ($rule_list as $item_key => $rule_info){
            $condition = json_decode($rule_info->condition,true);
            if(in_array($condition['operator_class'], array('limit_buy','company_goods_limit_buy'))){
                if(!$limit_rule_id){
                    $limit_rule_id = $rule_info->id;
                } else {
                    unset($rule_list[$item_key]);
                }

            }
        }
        return $rule_list;
    }

    /**
     * 筛选权重高的限额规则
     * @param $rule_list
     * @return mixed
     */
    private function _filter_limit_money_rule($rule_list)
    {
        $limit_rule_id = false;
        foreach ($rule_list as $item_key => $rule_info){
            $condition = json_decode($rule_info->condition,true);
            if(in_array($condition['operator_class'], array('limit_money'))){
                if(!$limit_rule_id){
                    $limit_rule_id = $rule_info->id;
                } else {
                    unset($rule_list[$item_key]);
                }

            }
        }
        return $rule_list;
    }

    private function _exclude_not_limit_money_rule($rule_list)
    {
        foreach ($rule_list as $item_key => $rule_info){
            $condition = json_decode($rule_info->condition,true);
            if(!in_array($condition['operator_class'], array('limit_money'))){
                unset($rule_list[$item_key]);
            }
        }
        return $rule_list;
    }

    private function _product_with_rule($req,$rule_list){
        foreach ($req['filter_data']['product'] as &$product) {
            $product['product_bn'] = $product['product_bn']??($product['bn']??'');
            //兼容price为数组的情况
            if (isset($product['price']) && is_array($product['price'])) {
                $product['price'] = $product['price']['price'] ?? 0;
            }
        }

        $serviceLogic = new Service();
        $res = $serviceLogic->ServiceCall(
            'product_with_rule',
            [
                'rule_list'  => array_unique(array_column($rule_list,'rule_id')),
                'filter_data' => $req['filter_data'],
            ]
        );
        if ('SUCCESS' == $res['error_code']) {
            $returnData = array();

            $rule = [];
            foreach ($res['data']['product'] as $ruleId => $data) {
                if ( ! empty($data['product_list']) && is_array($data['product_list'])) {
                    foreach ($data['product_list'] as $goods) {
                        $rule[$goods['product_bn']][] = $ruleId;
                    }
                }
            }

            foreach ($req['filter_data']['product'] as $key=>$value){
                $bn = $value['product_bn'] ? :$value['bn'];
                $returnData[$bn] = $value;
                $returnData[$bn]['goods_rule'] = $rule[$bn]??[];
            }
            return $returnData;
        } else {
            Logger::Debug('promotion.v2',[
                'action'=>'_product_with_rule',
                'remark'=>'规则验证失败',
                'request_params'=>$req,
            ]);
            return [];
        }
    }

    /**
     * 获取商品的规则
     * @param Request $request
     * @return array
     */
    public function GetGoodsPromotion(Request $request){
        //step1 filter company channel member
        $req = $this->getContentArray($request);

        $model = new PromotionModel();
        $rule_list = $model->getNormalMatchRuleId($req['company_id'],$req['member_id'],$req['channel']);

        $relation = array();
        foreach ($rule_list as $rel){
            $relation[$rel->rule_id][] = $rel->pid;
        }

        $req['filter_data']['product'] = $req['product'];
        $returnData = $this->_product_with_rule($req,$rule_list);
        if(empty($returnData)) {
            foreach ($req['product'] as $key=>$product){
                $req['product'][$key]['promotion_rule'] = false;
            }
            $return['product'] = $req['product'];
            $return['rule_list'] = [];
            return $this->outputFormat($return, 0);
        }
        $tmp_rule_list = [];
        foreach ($returnData as $product_bn => $info){
            //relation
            $promotion_rule = [];
            foreach ($info['goods_rule'] as $rule_id){
                $promotion_rule = array_merge($promotion_rule,$relation[$rule_id]);
            }
            $ruleList = $model->queryRuleByPromotionId($promotion_rule);
            $ruleList = $this->_filter_limit_buy_rule($ruleList);
            $ruleList = $this->_filter_limit_money_rule($ruleList);
            $tmp_rule_list = array_merge($tmp_rule_list,$ruleList);
            $allow = array_intersect(array_column($ruleList,'id'),array_unique($promotion_rule));
            $returnData[$product_bn]['promotion_rule'] = $allow;
            unset($returnData[$product_bn]['goods_rule']);
        }
        $return['product'] = $returnData;
        $return['rule_list'] = $tmp_rule_list;
        Logger::Debug('promotion.v2',[
            'action'=>'GetGoodsPromotion',
            'request_params'=>$req,
            'response_result'=>$returnData
        ]);
        return $this->outputFormat($return, 0);

    }

    public function CheckStock(Request $request)
    {
        $req = $this->getContentArray($request);

        $model = new PromotionModel();
        $rule_list = $model->getNormalMatchRuleId($req['company_id'],$req['member_id'],$req['channel']);

        $relation = array();
        foreach ($rule_list as $rel){
            $relation[$rel->rule_id][] = $rel->pid;
        }

        $req['filter_data']['product'] = $req['product'];
        $returnData = $this->_product_with_rule($req,$rule_list);
        if(empty($returnData)) {

            foreach ($req['product'] as $key=>$product){
                $req['product'][$key]['promotion_rule'] = false;
                $result['status'] = false;
                $result['msg'] = '当前规则不可用';
                $result['data'] = [];
                $req['product'][$key]['limit_res'] = $result;
            }
            $return['product'] = $req['product'];
            $return['rule_list'] = [];
            return $this->outputFormat($return, 0);
        }
        $tmp_rule_list = $product_chosen_validate = [];
        $matcher = new ProductsMatch();
        foreach ($returnData as $product_bn => $info){
            //relation
            $promotion_rule = [];
            foreach ($info['goods_rule'] as $rule_id){
                $promotion_rule = array_merge($promotion_rule,$relation[$rule_id]);
            }
            $ruleList = $model->queryRuleByPromotionId($promotion_rule);
            $ruleList = $this->_filter_limit_buy_rule($ruleList);

            $allow = array_intersect(array_column($ruleList,'id'),array_unique($promotion_rule));

            foreach ($ruleList as $key=>$value){
                if(!in_array($value->id,$allow)){
                    unset($ruleList[$key]);
                }
            }
            $tmp_rule_list = array_merge($tmp_rule_list,$ruleList);

            foreach ($ruleList as $allow_key => $rule){
                $condition = json_decode($rule->condition,true);
                if(!in_array($condition['operator_class'], array('limit_buy','company_goods_limit_buy'))){
                    unset($ruleList[$allow_key]);
                }
            }
            //计算当前规则
            $use_rule = current($ruleList);
            $match['goods_list'] = [$info];
            $match['scene_id'] = $use_rule->id;
            $match['member_id'] = $req['member_id'];
            $match['company_id'] = $req['company_id'];

            $ruleCondition = json_decode($use_rule->condition,true);

            if ($matcher->isBatchLimit($ruleCondition)) {
                $product_chosen_validate[$use_rule->id]['match']['goods_list'][] = $info;
                $product_chosen_validate[$use_rule->id]['match']['scene_id'] = $use_rule->id;
                $product_chosen_validate[$use_rule->id]['match']['member_id'] = $req['member_id'];
                $product_chosen_validate[$use_rule->id]['match']['company_id'] = $req['company_id'];
                $product_chosen_validate[$use_rule->id]['use_rule'] = $use_rule;
                continue;
            }

            $returnData[$product_bn]['limit_res'] = $matcher->MatchRule($ruleCondition,$match);

            $returnData[$product_bn]['promotion_rule'] = $use_rule;
            unset($returnData[$product_bn]['goods_rule']);
        }

        if (!empty($product_chosen_validate)) {
            foreach ($product_chosen_validate as $chosenInfo) {
                $limit_res = $matcher->MatchRule(json_decode($chosenInfo['use_rule']->condition,true), $chosenInfo['match']);
                foreach ($chosenInfo['match']['goods_list'] as $goodsInfo) {
                    $returnData[$goodsInfo['product_bn']]['limit_res'] = $limit_res;
                    $returnData[$goodsInfo['product_bn']]['promotion_rule'] = $chosenInfo['use_rule'];
                    unset($returnData[$goodsInfo['product_bn']]['goods_rule']);
                }
            }
        }

        $return['product'] = $returnData;
        Logger::Debug('promotion.v2',[
            'action'=>'CheckStock',
            'request_params'=>$req,
            'response_result'=>$returnData
        ]);
        return $this->outputFormat($return, 0);
    }

    public function LockStock(Request $request){
        $req = $this->getContentArray($request);
        $model = new PromotionModel();
        $rule_list = $model->getNormalMatchRuleId($req['company_id'],$req['member_id'],$req['channel']);

        $relation = array();
        foreach ($rule_list as $rel){
            $relation[$rel->rule_id][] = $rel->pid;
        }

        $req['filter_data']['product'] = $req['product'];
        $returnData = $this->_product_with_rule($req,$rule_list);
        if(empty($returnData)) {

            foreach ($req['product'] as $key=>$product){
                $req['product'][$key]['limit_rule'] = false;
                $result['status'] = true;
                $result['msg'] = '限购验证通过';
                $result['data'] = [];
                $req['product'][$key]['limit_res'] = $result;
            }
            $return['product'] = $req['product'];
            $return['rule_list'] = [];
            return $this->outputFormat($return, 0);
        }
        $tmp_rule_list = [];
        $matcher = new ProductsMatch();
        foreach ($returnData as $product_bn => $info){
            //relation
            $promotion_rule = [];
            foreach ($info['goods_rule'] as $rule_id){
                $promotion_rule = array_merge($promotion_rule,$relation[$rule_id]);
            }
            $ruleList = $model->queryRuleByPromotionId($promotion_rule);
            $ruleList = $this->_filter_limit_buy_rule($ruleList);

            $allow = array_intersect(array_column($ruleList,'id'),array_unique($promotion_rule));

            foreach ($ruleList as $key=>$value){
                if(!in_array($value->id,$allow)){
                    unset($ruleList[$key]);
                }
            }
            $tmp_rule_list = array_merge($tmp_rule_list,$ruleList);

            foreach ($ruleList as $allow_key => $rule){
                $condition = json_decode($rule->condition,true);
                if( ! in_array($condition['operator_class'], array('limit_buy', 'company_goods_limit_buy'))){
                    unset($ruleList[$allow_key]);
                }
            }
            //计算当前规则
            $use_rule = current($ruleList);
            $match['goods_list'] = [$info];
            $match['scene_id'] = $use_rule->id;
            $match['member_id'] = $req['member_id'];
            $match['company_id'] = $req['company_id'];
            $match['order_id'] = $req['order_id']; // 由于lock会导致has_buy数量添加，所以加上订单维度，在计算has_buy的时候排除该订单
            $res = $matcher->MatchRule(json_decode($use_rule->condition,true),$match);
            if(!$res['status']){
                $returnData[$product_bn]['limit_rule'] = $use_rule;
                $returnData[$product_bn]['limit_res'] = $res;
                $lock_status = false;
            } else {
                //锁定库存
                $lock['bn'] = $info['bn'];
                $lock['goods_id'] = $info['goods_id'];
                $lock['nums'] = $info['nums'];
                $lock['amount'] = bcmul($info['amount'], 100);
                $lock['create_time'] = time();
                $lock['member_id'] = $req['member_id'];
                $lock['company_id'] = $req['company_id'];
                $lock['rule_id'] = $use_rule->id;
                $lock['order_id'] = $req['order_id'];
                $res = $model->LockPromotionStock($lock);
                if(!$res){
                    $lock_status = false;
                    $returnData[$product_bn]['limit_res']['msg'] = '促销服务 锁定库存失败';
                }
                $returnData[$product_bn]['limit_rule'] = $use_rule;
                $returnData[$product_bn]['limit_res']['status'] = true;
                $returnData[$product_bn]['limit_res']['msg'] = '限购验证通过';
            }
            unset($returnData[$product_bn]['goods_rule']);
        }
        $return['product'] = $returnData;
        Logger::Debug('promotion.v2',[
            'action'=>'LockStock',
            'request_params'=>$req,
            'response_result'=>$returnData
        ]);
        return $this->outputFormat($return, 0);
    }

    public function LockMoney(Request $request){
        $req = $this->getContentArray($request);
        $model = new PromotionModel();
        $rule_list = $model->getNormalMatchRuleId($req['company_id'],$req['member_id'],$req['channel']);

        $relation = array();
        foreach ($rule_list as $rel){
            $relation[$rel->rule_id][] = $rel->pid;
        }

        $req['filter_data']['product'] = $req['product'];
        $returnData = $this->_product_with_rule($req,$rule_list);
        if(empty($returnData)) {

            foreach ($req['product'] as $key=>$product){
                $req['product'][$key]['limit_rule'] = false;
                $result['status'] = true;
                $result['msg'] = '限购验证通过';
                $result['data'] = [];
                $req['product'][$key]['limit_res'] = $result;
            }
            $return['product'] = $req['product'];
            $return['rule_list'] = [];
            return $this->outputFormat($return, 0);
        }
        $tmp_rule_list = [];
        $matcher = new ProductsMatch();
        foreach ($returnData as $product_bn => $info){
            //relation
            $promotion_rule = [];
            foreach ($info['goods_rule'] as $rule_id){
                $promotion_rule = array_merge($promotion_rule,$relation[$rule_id]);
            }
            $ruleList = $model->queryRuleByPromotionId($promotion_rule);
            $ruleList = $this->_exclude_not_limit_money_rule($ruleList);

            $allow = array_intersect(array_column($ruleList,'id'),array_unique($promotion_rule));

            foreach ($ruleList as $key=>$value){
                if(!in_array($value->id,$allow)){
                    unset($ruleList[$key]);
                }
            }
            $tmp_rule_list = array_merge($tmp_rule_list,$ruleList);

            foreach ($ruleList as $allow_key => $rule){
                $condition = json_decode($rule->condition,true);
                if( ! in_array($condition['operator_class'], array('limit_money'))){
                    unset($ruleList[$allow_key]);
                }
            }
            //计算当前规则
            $use_rule = current($ruleList);
            $match['goods_list'] = [$info];
            $match['scene_id'] = $use_rule->id;
            $match['member_id'] = $req['member_id'];
            $match['company_id'] = $req['company_id'];
            $match['order_id'] = $req['order_id']; // 由于lock会导致has_buy数量添加，所以加上订单维度，在计算has_buy的时候排除该订单
            $res = $matcher->MatchRule(json_decode($use_rule->condition,true),$match);
            if(!$res['status']){
                $returnData[$product_bn]['limit_rule'] = $use_rule;
                $returnData[$product_bn]['limit_res'] = $res;
                $lock_status = false;
            } else {
                //锁定库存
                $lock['bn'] = $info['bn'];
                $lock['goods_id'] = $info['goods_id'];
                $lock['nums'] = $info['nums'];
                $lock['amount'] = bcmul($info['amount'], 100);
                $lock['create_time'] = time();
                $lock['member_id'] = $req['member_id'];
                $lock['company_id'] = $req['company_id'];
                $lock['rule_id'] = $use_rule->id;
                $lock['order_id'] = $req['order_id'];
                $res = $model->LockPromotionStock($lock);
                if(!$res){
                    $lock_status = false;
                    $returnData[$product_bn]['limit_res']['msg'] = '促销服务 锁定库存失败';
                }
                $returnData[$product_bn]['limit_rule'] = $use_rule;
                $returnData[$product_bn]['limit_res']['status'] = true;
                $returnData[$product_bn]['limit_res']['msg'] = '限购验证通过';
            }
            unset($returnData[$product_bn]['goods_rule']);
        }
        $return['product'] = $returnData;
        Logger::Debug('promotion.v2',[
            'action'=>'LockMoney',
            'request_params'=>$req,
            'response_result'=>$returnData
        ]);
        return $this->outputFormat($return, 0);
    }

    public function CheckRule(Request $request){
        $req = $this->getContentArray($request);

        $model = new PromotionModel();
        $rule_list = $model->getNormalMatchRuleId($req['company_id'],$req['member_id'],$req['channel']);

        $relation = array();
        foreach ($rule_list as $rel){
            $relation[$rel->rule_id][] = $rel->pid;
        }

        $req['filter_data']['product'] = $req['product'];
        $returnData = $this->_product_with_rule($req,$rule_list);
        if(empty($returnData)) {
            foreach ($req['product'] as $key=>$product){
                $req['product'][$key]['calc'] = [];
            }
            $return['product'] = $req['product'];
            $return['rule_list'] = [];
            return $this->outputFormat($return, 0);
        }
        $matcher = new ProductsMatch();
        foreach ($returnData as $product_bn => $info){
            //relation
            $promotion_rule = [];
            foreach ($info['goods_rule'] as $rule_id){
                $promotion_rule = array_merge($promotion_rule,$relation[$rule_id]);
            }
            $ruleList = $model->queryRuleByPromotionId($promotion_rule);
            $allow = array_intersect(array_column($ruleList,'id'),array_unique($promotion_rule));
            //获取当前商品可用的规则ID
            foreach ($info['use_rule'] as $check_rule_id){
                if(!in_array($check_rule_id,$allow)){
                    $this->setErrorMsg('选品规则校验不通过 rule_id:'.$check_rule_id.' allow'.json_encode($allow));
                    return $this->outputFormat([], 0);
                }
            }
            $tmp_rule = [];
            foreach ($ruleList as $rule){
                if(in_array($rule->id,$allow)){
                    if(!is_array($rule->condition)){
                        $rule->condition = json_decode($rule->condition,true);
                    }
                    $tmp_rule[$rule->id] = $rule;
                }
            }

            //计算规则产生的作用
            foreach ($allow as $rule_id){
                $use_rule = $tmp_rule[$rule_id];
                $match['goods_list'] = [$info];
                $match['scene_id'] = $rule_id;
                $match['member_id'] = $req['member_id'];
                $match['company_id'] = $req['company_id'];

                $calc[$rule_id] = $matcher->MatchRule($use_rule->condition,$match);
                $calc[$rule_id]['rule_id'] = $rule_id;
                unset($returnData[$product_bn]['goods_rule']);
            }
            $returnData[$product_bn]['calc'] = $calc;
        }
        $return['product'] = $returnData;
        Logger::Debug('promotion.v2',[
            'action'=>'CheckRule',
            'request_params'=>$req,
            'response_result'=>$returnData
        ]);
        return $this->outputFormat($return, 0);

    }

    public function CheckMoney(Request $request)
    {
        $req = $this->getContentArray($request);
        $model = new PromotionModel();
        // 获取所有规则列表
        $all_rule_list = $model->getNormalMatchRuleId($req['company_id'],$req['member_id'],$req['channel']);

        $all_pid_info = $model->queryRuleByPromotionId(array_unique(array_column($all_rule_list, 'pid')));

        // 过滤后得到限额规则
        $limit_money_pid_list = array_unique(array_column($this->_exclude_not_limit_money_rule($all_pid_info), 'id'));

        $relation = $rule_list = array();

        foreach ($all_rule_list as $rel) {
            if (in_array($rel->pid, $limit_money_pid_list)) {
                $relation[$rel->rule_id][] = $rel->pid;
                $rule_list[] = array(
                    'pid' =>  $rel->pid,
                    'rule_id' => $rel->rule_id
                );
            }
        }

        $req['filter_data']['product'] = $req['product'];
        $returnData = $this->_product_with_rule($req,$rule_list);
        if (empty($returnData)) {
            foreach ($req['product'] as $key=>$product){
                $req['product'][$key]['promotion_rule'] = false;
                $result['status'] = false;
                $result['msg'] = '当前规则不可用';
                $result['data'] = [];
                $req['product'][$key]['limit_res'] = $result;
            }
            $return['product'] = $req['product'];
            $return['rule_list'] = [];
            return $this->outputFormat($return, 0);
        }

        $matcher = new ProductsMatch();
        foreach ($returnData as $product_bn => $info){
            $promotion_rule = [];
            foreach ($info['goods_rule'] as $rule_id){
                $promotion_rule = array_merge($promotion_rule,$relation[$rule_id]);
            }
            $ruleList = $model->queryRuleByPromotionId($promotion_rule);
            //计算当前规则
            $use_rule = current($ruleList);
            $match['goods_list'] = [$info];
            $match['scene_id'] = $use_rule->id;
            $match['member_id'] = $req['member_id'];
            $match['company_id'] = $req['company_id'];
            $returnData[$product_bn]['limit_res'] = $matcher->MatchRule(json_decode($use_rule->condition,true),$match);
            $returnData[$product_bn]['promotion_rule'] = $use_rule;
            unset($returnData[$product_bn]['goods_rule']);
        }

        $return['product'] = $returnData;

        Logger::Debug('promotion.v2',[
            'action'=>'CheckMoney',
            'request_params'=>$req,
            'response_result'=>$returnData
        ]);

        return $this->outputFormat($return, 0);
    }
}
