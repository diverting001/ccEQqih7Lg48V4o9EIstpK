<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 18:16
 */

namespace App\Api\Logic\Promotion\Matcher;


use App\Api\Model\Goods\Promotion;

class OrderDiscount extends BaseMatcher implements RuleMatcherInterface
{
    private $_processor_name = 'order_discount';

    private $_model;

    public function __construct()
    {
        $this->_model = new Promotion();

    }
    public function exec($times, $config = array(), $filter_data = array())
    {
        $data['order_discount'] = $config['discount']*$times;
        $data['class'] = $this->_processor_name;
        return $this->output(true,'满足订单满减条件',$data);
    }
}
