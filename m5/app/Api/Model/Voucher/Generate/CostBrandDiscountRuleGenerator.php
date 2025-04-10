<?php

namespace App\Api\Model\Voucher\Generate;

class CostBrandDiscountRuleGenerator
{
    public function __construct()
    {
        $this->match_processor_name = 'CostBrandDiscountRuleProcessor';
    }

    public function create($params)
    {
        $rule = array();
        if (isset($params['brand_discount'])) {
            $rule['brand_discount'] = $params['brand_discount'];
        }
        if (isset($params['limit_cost'])) {
            $rule['limit_cost'] = $params['limit_cost'];
        }

        if ($params['use_url'])
            $rule['use_url'] = $params['use_url'];
        $create_array = array(
            'processor' => $this->match_processor_name,
            'filter_rule' => $rule
        );
        return $create_array;
    }
}
