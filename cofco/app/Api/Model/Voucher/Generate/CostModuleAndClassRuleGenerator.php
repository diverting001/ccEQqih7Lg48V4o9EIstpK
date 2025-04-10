<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 16:30
 */

namespace App\Api\Model\Voucher\Generate;


class CostModuleAndClassRuleGenerator extends BasicRuleGenerator
{
    public function __construct() {
        $this->match_processor_name = 'CostModuleAndClassRuleProcessor';
    }

    public function create($params) {
        if (isset($params['module_class'])) {
            $create_array = array(
                'processor'=>$this->match_processor_name,
                'filter_rule'=>array(
                    'module_class'=>$params['module_class'],
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