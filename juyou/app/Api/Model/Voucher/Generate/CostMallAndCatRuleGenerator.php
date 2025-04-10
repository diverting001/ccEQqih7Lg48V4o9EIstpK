<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 16:29
 */

namespace App\Api\Model\Voucher\Generate;


class CostMallAndCatRuleGenerator extends BasicRuleGenerator
{
    public function __construct() {
        $this->match_processor_name = 'CostMallAndCatRuleProcessor';
    }

    public function create($params) {
        if (isset($params['mall_id_list']) && isset($params['mall_cat_id_list'])) {
            $create_array = array(
                'processor'=>$this->match_processor_name,
                'filter_rule'=>array(
                    'mall_id_list'=>$params['mall_id_list'],
                    'mall_cat_id_list'=>$params['mall_cat_id_list'],
                    'limit_cost' => $params['limit_cost'],
                    'use_url' => $params['use_url'],
                    'use_type' => $params['use_type']
                )
            );
            return $create_array;
        }
        return false;
    }

}