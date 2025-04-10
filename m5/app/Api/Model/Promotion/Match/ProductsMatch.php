<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-02-25
 * Time: 17:06
 */

namespace App\Api\Model\Promotion\Match;

use App\Api\Logic\Promotion\MatcherAdapter;

class ProductsMatch
{
    // egt 大于等于
    // per 每一次
    // once 一次
    public function MatchRule($condition,$filterData){
        if(empty($condition)){
            $result['status'] = false;
            $result['msg'] = '当前规则不可用';
            $result['data'] = [];
            return $result;
        }
        //step1 per or once
        $times = $this->_filter_times($condition,$filterData['goods_list']);
        //step2 amount filter or nums filter
        if($times['times']==0){
            //不满足条件
            $result['status'] = false;
            $result['msg'] = '不满足条件';
            $result['data'] = [];
            return $result;
        }
        //step3 exec class for present free_shipping limit_buy
        $matcherAdapter = new MatcherAdapter($condition['operator_class']);
//        $className = 'App\\Api\\Model\\Promotion\\Rule\\' . ucfirst(camel_case($condition['operator_class'])) . "Exec";
//        $class = new $className();
        $exec_ret = $matcherAdapter->exec($times['times'],$condition['extend_data'],$filterData);
//        $result['result'] = $exec_ret[''];
//        $result['code'] = $exec_ret['code'];
        $result['status'] = $exec_ret['status'];
        $result['msg'] = $exec_ret['msg'];
        $result['data'] = $exec_ret['data'];
        return $result;
        //step4 calc result return
    }

    //解析 满足条件的 说明 per 每一次 once 只有一次
    private function _filter_times($condition,$goods_list){
        $ret = $this->_calc_amount($goods_list);
        $all = $ret[$condition['operator_type']];
        $times = $all/($condition['operator_value']);
        \Neigou\Logger::Debug('gaoygnlong_tesst',[
            'action'=>$goods_list,
            'remark'=>$ret,
            'request_params'=>$condition,
        ]);
//        echo $times;die;
        if($times>=1){
            if($condition['times']=='per'){
                return ['times'=>floor($times)];
            } else {
                return ['times'=>1];
            }
        } else {
            return ['times'=>0];
        }
    }

    //计算商品组合的价格和件数
    private function _calc_amount($products){
        foreach ($products as $key=>$product){
            $products[$key]['amount'] = $product['price']*$product['nums'];
        }
        $ret['amount'] = array_sum(array_column($products,'amount'));
        $ret['nums'] = array_sum(array_column($products,'nums'));
        return $ret;
    }

    public function isBatchLimit($condition)
    {
        if (empty($condition['operator_class']) || empty($condition['extend_data'])) {
            return false;
        }
        return  (new MatcherAdapter($condition['operator_class']))->isBatchLimit($condition['extend_data']);
    }
}
