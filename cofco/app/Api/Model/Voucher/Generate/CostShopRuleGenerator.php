<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 16:29
 */

namespace App\Api\Model\Voucher\Generate;


class CostShopRuleGenerator extends BasicRuleGenerator
{
    public function __construct() {
        $this->match_processor_name = 'CostShopRuleProcessor';
    }

    public function create($params) {
        $rule = array();
        if (isset($params['shop'])) {
            $rule['shop'] = $params['shop'];
        }
        if (isset($params['limit_cost'])) {
            $rule['limit_cost'] = $params['limit_cost'];
        }
        if($params['use_url'])
            $rule['use_url'] = $params['use_url'];
        $create_array = array(
            'processor'=>$this->match_processor_name,
            'filter_rule'=>$rule
        );
        return $create_array;
    }

}