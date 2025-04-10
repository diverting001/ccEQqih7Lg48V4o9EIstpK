<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2020-06-09
 * Time: 11:04
 */

namespace App\Api\Logic\Promotion\Matcher;


class Present extends BaseMatcher implements RuleMatcherInterface
{
    private $_processor_name = 'present';

    public function exec($times, $config = array(), $filter_data = array())
    {
        foreach ($config['presents'] as $key=>$item){
            $config['presents'][$key]['nums'] = $item['nums']*$times;
        }

        $ret['present'] = $config['presents'];
        $ret['class'] = $this->_processor_name;

        return $this->output(true,'满足赠品条件',$ret);
    }
}