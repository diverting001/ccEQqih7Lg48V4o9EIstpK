<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/10/20
 * Time: 16:05
 */

namespace App\Api\Model\Voucher\Rule;


class BasicRuleProcessor
{

    /**
     * 验证该商品是否可用内购券
     * @param $ruleBlackList
     * @param $productBn
     * @return bool
     */
    protected function AuthProductBnBlackGoodsList($ruleBlackList,$productBn){

        if(!empty($ruleBlackList)){
            if(in_array($productBn,$ruleBlackList)){
                return false;
            }else{
                return true;
            }
        }else{
            return true;
        }
    }
}