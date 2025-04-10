<?php
/**
 * Created by PhpStorm.
 * User: chuanbin
 * Date: 2017/11/5
 * Time: 16:27
 */

namespace App\Api\Model\Voucher\Generate;


class BasicRuleGenerator
{

    protected $match_processor_name;

    public function __construct() {
        $this->match_processor_name = 'BasicRuleProcessor';
    }

    public function create($params) {
        return true;
    }
}