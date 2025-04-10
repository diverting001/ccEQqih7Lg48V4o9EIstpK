<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 11:04
 */

namespace App\Api\Logic\Promotion\Matcher;


class FreeShipping extends BaseMatcher implements RuleMatcherInterface
{
    private $_processor_name = 'free_shipping';

    public function exec($times, $config = array(), $filter_data = array())
    {
        $data['free_shipping'] = true;
        $data['class'] = $this->_processor_name;
        return $this->output(true,'满足免邮条件',$data);
    }
}