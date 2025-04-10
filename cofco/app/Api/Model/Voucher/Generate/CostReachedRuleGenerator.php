<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 16:30
 */

namespace App\Api\Model\Voucher\Generate;


class CostReachedRuleGenerator extends BasicRuleGenerator
{
    public function __construct() {
        $this->match_processor_name = 'CostReachedRuleProcessor';
    }

    public function create($params) {
        $rule = array();
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