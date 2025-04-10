<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 11:04
 */

namespace App\Api\Logic\Promotion\Creator;


class LimitBuy implements RuleCreatorInterface
{
    private $_processor_name = 'limit_buy';

    public function generate($data)
    {
        $condition['times'] = $data['times'];//满足方式 ：每次 per ｜一次 once
        $condition['refresh_time'] = $data['refresh_time'];//满足方式 ：每次 per ｜一次 once
        $condition['operator_math'] = $data['operator_math'];//运算符： egt 大于等于
        $condition['operator_type'] = $data['operator_type'];//类型 ：价格 amount ｜数量 nums
        $condition['operator_value'] = $data['operator_value'];;//具体值：¥20 ｜ 20件
        $condition['operator_class'] = $this->_processor_name;//促销类 :赠品 present｜ 免邮 free_shipping｜ 限购 limit_buy
        $condition['extend_data'] = $data['extend_data'];//类匹配规则内容
        return json_encode($condition);
    }



}