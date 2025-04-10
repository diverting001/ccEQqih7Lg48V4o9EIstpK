<?php

namespace App\Api\Logic\Promotion\Creator;

class CompanyGoodsLimitBuy implements RuleCreatorInterface
{
    private $_processor_name = 'company_goods_limit_buy';

    public function generate($data)
    {
        $condition['times'] = $data['times'];
        $condition['operator_math'] = $data['operator_math'];
        $condition['operator_type'] = $data['operator_type'];
        $condition['operator_value'] = $data['operator_value'];
        $condition['operator_class'] = $this->_processor_name;
        $condition['extend_data'] = $data['extend_data'];
        return json_encode($condition);
    }
}
