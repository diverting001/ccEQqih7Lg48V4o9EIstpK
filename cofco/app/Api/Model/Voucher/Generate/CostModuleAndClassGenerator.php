<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 16:29
 */

namespace App\Api\Model\Voucher\Generate;


class CostModuleAndClassGenerator extends BasicRuleGenerator
{
    public function __construct() {
        $this->match_processor_name = 'CostModuleAndClassProcessor';
    }

    public function create($params) {
        if (isset($params['module_class'])) {
            $create_array = array(
                'processor'=>$this->match_processor_name,
                'filter_rule'=>array(
                    'limit_cost'=>$params['module_class']
                )
            );
            return $create_array;
        }
        return false;
    }

}